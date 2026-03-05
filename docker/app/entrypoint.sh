#!/bin/bash
set -e

# Runtime caching (depends on environment variables available only at runtime)
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
    echo "Warming up caches..."
    php artisan config:cache || { echo "WARNING: config:cache failed, continuing..."; }
    php artisan event:cache || { echo "WARNING: event:cache failed, continuing..."; }
    php artisan route:cache || { echo "WARNING: route:cache failed, continuing..."; }
    php artisan view:cache || { echo "WARNING: view:cache failed, continuing..."; }
    echo "Cache warmup complete."
fi

exec "$@"
