FROM php:8.5-fpm-bookworm AS base

ARG WWWGROUP=1000
ARG WWWUSER=1000

WORKDIR /var/www/html

# Install system dependencies
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
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        gd \
        bcmath \
        opcache \
        pcntl \
        sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install MongoDB extension
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create app user
RUN groupadd --force -g $WWWGROUP appuser \
    && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u $WWWUSER appuser

# PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# ---- Development Stage ----
FROM base AS development

# Install xdebug for development
RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY . /var/www/html

RUN chown -R appuser:appuser /var/www/html

USER appuser

RUN composer install --no-interaction --optimize-autoloader

EXPOSE 9000
CMD ["php-fpm"]

# ---- Production Stage ----
FROM base AS production

COPY . /var/www/html

RUN chown -R appuser:appuser /var/www/html

USER appuser

RUN composer install --no-dev --no-interaction --optimize-autoloader \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 9000
CMD ["php-fpm"]
