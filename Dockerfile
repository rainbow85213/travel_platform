# ----------------------------------------
# Stage 1: Composer dependencies
# ----------------------------------------
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ----------------------------------------
# Stage 2: Production image
# ----------------------------------------
FROM php:8.2-fpm-bookworm AS production

# Install system packages + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        unzip \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        bcmath \
        mbstring \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy app (with vendor from stage 1)
COPY --from=composer /app /var/www/html

# Copy Docker config files
COPY docker/nginx.conf       /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini          /usr/local/etc/php/conf.d/99-production.ini
COPY docker/start.sh         /start.sh

# Permissions
RUN chmod +x /start.sh \
    && mkdir -p storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && mkdir -p /var/log/supervisor

EXPOSE 80

CMD ["/start.sh"]
