#!/bin/bash
set -e

# Ensure storage directories exist and are writable (named volumes may be empty on first run)
dirs=(
    /var/www/html/storage/framework/views
    /var/www/html/storage/framework/cache/data
    /var/www/html/storage/framework/sessions
    /var/www/html/storage/logs
    /var/www/html/bootstrap/cache
)

for dir in "${dirs[@]}"; do
    mkdir -p "$dir" 2>/dev/null || true
done

# Fix ownership if volumes were mounted as root (common with named volumes on first deploy)
if [ ! -w /var/www/html/storage ]; then
    echo "WARNING: storage not writable by appuser. Attempting fix..."
    echo "Run 'chown -R $(id -u):$(id -g) /var/www/html/storage' from a root shell if this persists."
fi

# Runtime caching (depends on environment variables available only at runtime)
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    echo "Running optimizations for $APP_ENV (role: ${CONTAINER_ROLE:-app})..."

    case "${CONTAINER_ROLE:-app}" in
        app)
            php artisan config:cache
            php artisan event:cache
            php artisan route:cache
            php artisan view:cache
            ;;
        horizon|rabbitmq-worker)
            php artisan config:cache
            php artisan event:cache
            ;;
        scheduler)
            php artisan config:cache
            php artisan event:cache
            ;;
    esac
fi

exec "$@"
