FROM php:8.5.3-fpm-bookworm AS base

LABEL maintainer="MaisVendas <molavosbdeveloper@gmail.com>"

ARG WWWGROUP=1000
ARG WWWUSER=1000

WORKDIR /var/www/html

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    librabbitmq-dev \
    libfcgi-bin \
    procps \
    pkg-config \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# php-fpm-healthcheck (pinned to v0.5.0)
COPY docker/app/scripts/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck

# PHP extensions (opcache is built-in since PHP 8.5 - do not install separately)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        gd \
        bcmath \
        pcntl \
        sockets

# PECL extensions (pinned versions for reproducible builds, PHP 8.5 compatible)
RUN pecl install redis-6.3.0 mongodb-2.2.1 amqp-2.2.0 \
    && docker-php-ext-enable redis mongodb amqp

# Composer
COPY --from=composer:2.9.5 /usr/bin/composer /usr/bin/composer

# Create app user
RUN groupadd --force -g $WWWGROUP appuser \
    && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u $WWWUSER appuser

# PHP config
COPY docker/app/conf.d/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/app/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/app/conf.d/php-fpm.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# ─────────────────────────────────────────────
# Development
# ─────────────────────────────────────────────
FROM base AS development

RUN pecl install xdebug && docker-php-ext-enable xdebug
COPY docker/app/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Layer caching: copy dependency files first
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-scripts

COPY . /var/www/html
RUN composer run-script post-autoload-dump \
    && chown -R appuser:appuser /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

USER appuser

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

EXPOSE 9000
CMD ["php-fpm"]

# ─────────────────────────────────────────────
# Production
# ─────────────────────────────────────────────
FROM base AS production

# Production-specific PHP config
COPY docker/app/conf.d/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

# Layer caching: copy dependency files first
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

COPY . /var/www/html
RUN composer run-script post-autoload-dump \
    && chown -R appuser:appuser /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Entrypoint for runtime caching (route, view, config, event)
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER appuser

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# ─────────────────────────────────────────────
# Horizon (queue worker)
# ─────────────────────────────────────────────
FROM production AS horizon

USER appuser
HEALTHCHECK --interval=60s --timeout=10s --start-period=15s --retries=3 \
    CMD php artisan horizon:status | grep -q running || exit 1

CMD ["php", "artisan", "horizon"]

# ─────────────────────────────────────────────
# RabbitMQ Worker (heavy processing)
# ─────────────────────────────────────────────
FROM production AS rabbitmq-worker

USER appuser
CMD ["php", "artisan", "queue:work", "rabbitmq", \
     "--queue=processing,data_crossref,reports", \
     "--max-jobs=1000", \
     "--max-time=3600", \
     "--memory=256", \
     "--tries=3", \
     "--sleep=3"]
