#!/bin/bash
set -e

# Runtime caching (depends on environment variables available only at runtime)
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    php artisan config:cache
    php artisan event:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
