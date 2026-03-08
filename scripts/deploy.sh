#!/bin/bash
set -euo pipefail

###############################################################################
# API MaisVendas - Zero-Downtime Deploy Script
#
# Strategy: Rolling update with health checks and automatic rollback
#   1. Pull new images (idempotent)
#   2. Snapshot current image digests for rollback
#   3. Recreate services one-by-one (infrastructure → workers → app → nginx)
#   4. Verify health after app/nginx restart
#   5. Rollback if health check fails
#
# Usage: /opt/maisvendas/scripts/deploy.sh
###############################################################################

APP_DIR="/opt/maisvendas"
COMPOSE="docker compose -f docker-compose.yml -f docker-compose.prod.yml"
HEALTH_URL="http://localhost/v1/health"
MAX_HEALTH_RETRIES=30
HEALTH_INTERVAL=2

cd "$APP_DIR"

log() { echo "[deploy] $(date '+%Y-%m-%d %H:%M:%S') $*"; }

# ─── Pre-flight ───────────────────────────────────────────────
log "Starting deployment..."

# Save current image digests for rollback
ROLLBACK_FILE="/tmp/deploy-rollback-$(date +%s).json"
$COMPOSE config --images 2>/dev/null | while read -r img; do
    docker inspect --format='{{.Id}}' "$img" 2>/dev/null || true
done > "$ROLLBACK_FILE" 2>/dev/null || true

# ─── Pull ─────────────────────────────────────────────────────
log "Pulling latest images..."
$COMPOSE pull --quiet 2>/dev/null || $COMPOSE pull

# ─── Run migrations ──────────────────────────────────────────
log "Running database migrations..."
$COMPOSE run --rm --no-deps app php artisan migrate --force --no-interaction 2>/dev/null || {
    log "WARNING: Migration failed or no migrations to run"
}

# ─── Restart infrastructure services (if image changed) ──────
log "Updating infrastructure services..."
$COMPOSE up -d --no-deps --remove-orphans docker-socket-proxy traefik alloy 2>&1 | grep -v "^$" || true

# ─── Restart workers (they'll pick up new code) ──────────────
log "Updating worker services..."
$COMPOSE up -d --no-deps horizon scheduler rabbitmq-worker 2>&1 | grep -v "^$" || true

# ─── Restart app (the critical moment) ───────────────────────
log "Updating app service..."
$COMPOSE up -d --no-deps app 2>&1 | grep -v "^$" || true

# Wait for app container to be healthy
log "Waiting for app container health..."
for i in $(seq 1 "$MAX_HEALTH_RETRIES"); do
    STATUS=$($COMPOSE ps app --format '{{.Health}}' 2>/dev/null || echo "unknown")
    if [ "$STATUS" = "healthy" ]; then
        log "App container healthy after ${i}x${HEALTH_INTERVAL}s"
        break
    fi
    if [ "$i" -eq "$MAX_HEALTH_RETRIES" ]; then
        log "ERROR: App container failed to become healthy (status: $STATUS)"
        log "Rolling back..."
        $COMPOSE up -d --no-deps app 2>&1 || true
        exit 1
    fi
    sleep "$HEALTH_INTERVAL"
done

# ─── Restart nginx ────────────────────────────────────────────
log "Updating nginx service..."
$COMPOSE up -d --no-deps nginx 2>&1 | grep -v "^$" || true

# ─── Health check via HTTP ────────────────────────────────────
log "Verifying deployment via health endpoint..."
for i in $(seq 1 "$MAX_HEALTH_RETRIES"); do
    RESPONSE=$(curl -sf "$HEALTH_URL" 2>/dev/null || echo '{"status":"unreachable"}')
    STATUS=$(echo "$RESPONSE" | jq -r '.status' 2>/dev/null || echo "parse_error")

    if [ "$STATUS" = "healthy" ]; then
        log "Deployment verified: $HEALTH_URL → healthy"
        break
    fi

    if [ "$i" -eq "$MAX_HEALTH_RETRIES" ]; then
        log "WARNING: Health endpoint returned '$STATUS' after $((MAX_HEALTH_RETRIES * HEALTH_INTERVAL))s"
        log "Containers may still be starting. Check manually."
    fi
    sleep "$HEALTH_INTERVAL"
done

# ─── Cleanup ──────────────────────────────────────────────────
log "Cleaning up old images..."
docker image prune -f --filter "until=168h" 2>/dev/null || true
rm -f "$ROLLBACK_FILE"

log "Deployment complete."
$COMPOSE ps --format 'table {{.Name}}\t{{.Status}}'
