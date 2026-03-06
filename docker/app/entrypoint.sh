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

# Fix ownership if running as root (production entrypoint)
if [ "$(id -u)" = "0" ]; then
    chown -R appuser:appuser /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

    # Runtime caching (depends on environment variables available only at runtime)
    if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
        echo "Running optimizations for $APP_ENV (role: ${CONTAINER_ROLE:-app})..."

        case "${CONTAINER_ROLE:-app}" in
            app)
                gosu appuser php artisan config:cache
                gosu appuser php artisan event:cache
                gosu appuser php artisan route:cache
                gosu appuser php artisan view:cache
                gosu appuser php artisan storage:link --force 2>/dev/null || true
                ;;
            horizon|rabbitmq-worker|scheduler)
                gosu appuser php artisan config:cache
                gosu appuser php artisan event:cache
                ;;
        esac
    fi

    exec gosu appuser "$@"
fi

# Running as non-root (development) - run caching directly
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    echo "Running optimizations for $APP_ENV (role: ${CONTAINER_ROLE:-app})..."

    case "${CONTAINER_ROLE:-app}" in
        app)
            php artisan config:cache
            php artisan event:cache
            php artisan route:cache
            php artisan view:cache
            php artisan storage:link --force 2>/dev/null || true
            ;;
        horizon|rabbitmq-worker|scheduler)
            php artisan config:cache
            php artisan event:cache
            ;;
    esac
fi

exec "$@"
