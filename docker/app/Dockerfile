FROM php:8.5.3-fpm-bookworm@sha256:ad188f9cf56df666090845cab4b7df74bb401c326b77c0eac52efdab5fa75bed AS base

LABEL maintainer="MaisVendas <dev@maisvenda.store>" \
      org.opencontainers.image.title="API MaisVendas" \
      org.opencontainers.image.description="Whitelabel SaaS API - PHP-FPM Application" \
      org.opencontainers.image.vendor="MaisVendas" \
      org.opencontainers.image.source="https://github.com/marcosbuganeme/api-maisvendas" \
      org.opencontainers.image.licenses="Proprietary"

ARG WWWGROUP=1000
ARG WWWUSER=1000

WORKDIR /var/www/html

# Install gnupg first (needed for GPG key imports below)
RUN apt-get update && apt-get install -y --no-install-recommends gnupg curl \
    && rm -rf /var/lib/apt/lists/*

# Add GPG keys and external repos
RUN curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
        | gpg --dearmor -o /etc/apt/trusted.gpg.d/pgdg.gpg \
    && echo "deb http://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list \
    && curl -fsSL https://www.mongodb.org/static/pgp/server-8.0.asc \
        | gpg --dearmor -o /etc/apt/trusted.gpg.d/mongodb.gpg \
    && echo "deb [ arch=amd64 ] https://repo.mongodb.org/apt/debian bookworm/mongodb-org/8.0 main" \
        > /etc/apt/sources.list.d/mongodb-org-8.0.list \
    && apt-get update && apt-get install -y --no-install-recommends \
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
    pkg-config \
    procps \
    gosu \
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
# Dependabot cannot track PECL versions. Check manually:
#   https://pecl.php.net/package/redis
#   https://pecl.php.net/package/mongodb
#   https://pecl.php.net/package/amqp
RUN pecl install redis-6.3.0 mongodb-2.2.1 amqp-2.2.0 \
    && docker-php-ext-enable redis mongodb amqp

# Composer
COPY --from=composer:2.9.5@sha256:f0809732b2188154b3faa8e44ab900595acb0b09cd0aa6c34e798efe4ebc9021 /usr/bin/composer /usr/bin/composer

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

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    postgresql-client-17 \
    mongodb-database-tools \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install xdebug && docker-php-ext-enable xdebug
COPY docker/app/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Layer caching: copy dependency files first
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-scripts

COPY --chown=appuser:appuser . /var/www/html
RUN composer run-script post-autoload-dump \
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

# Production-specific PHP and PHP-FPM config (baked into image for standalone deploys)
COPY docker/app/conf.d/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini
COPY docker/app/conf.d/php-fpm-prod.conf /usr/local/etc/php-fpm.d/zz-prod.conf

# Layer caching: copy dependency files first
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

COPY --chown=appuser:appuser . /var/www/html
RUN composer run-script post-autoload-dump \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Bake Laravel caches into the image (immutable containers principle).
# config:cache and route:cache depend on env vars, but Laravel reads .env at build
# time only for structure - runtime values come from actual env vars via entrypoint.
# event:cache and view:cache are purely code-derived and safe to bake.
RUN php artisan event:cache \
    && php artisan view:cache

# Entrypoint: runs as root, fixes ownership, drops to appuser via gosu
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD gosu appuser php-fpm-healthcheck || exit 1

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
