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

# Apply PHP-FPM pm.max_children from environment variable (if set)
if [ -n "${PHP_FPM_PM_MAX_CHILDREN:-}" ]; then
    if ! [[ "${PHP_FPM_PM_MAX_CHILDREN}" =~ ^[0-9]+$ ]]; then
        echo "ERROR: PHP_FPM_PM_MAX_CHILDREN must be a positive integer, got: '${PHP_FPM_PM_MAX_CHILDREN}'"
        exit 1
    fi
    # Target zz-prod.conf (production override) since it loads after zz-docker.conf alphabetically
    FPM_CONF="/usr/local/etc/php-fpm.d/zz-prod.conf"
    if [ ! -f "$FPM_CONF" ]; then
        FPM_CONF="/usr/local/etc/php-fpm.d/zz-docker.conf"
    fi
    if [ -f "$FPM_CONF" ]; then
        sed -i "s/^pm\.max_children = .*/pm.max_children = ${PHP_FPM_PM_MAX_CHILDREN}/" "$FPM_CONF"
    fi
fi

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

    # PHP-FPM master process needs root for /proc/self/fd/2 (stderr) access.
    # Workers run as appuser via pool config (user/group directive).
    if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
        exec "$@"
    else
        exec gosu appuser "$@"
    fi
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
