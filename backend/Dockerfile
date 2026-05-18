FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash git curl libpng-dev libxml2-dev zip unzip \
    postgresql-dev oniguruma-dev linux-headers \
    libzip-dev

RUN docker-php-ext-install \
    pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip sockets

# redis: caché del JWKS de Keycloak en JwtMiddleware (paquete shared-auth-laravel).
# pcov: coverage rápido en `php artisan test --coverage` (más rápido que xdebug).
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis pcov \
    && docker-php-ext-enable redis pcov \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy app (vendor excluded via .dockerignore)
COPY . .

RUN rm -f bootstrap/cache/services.php bootstrap/cache/packages.php 2>/dev/null || true
RUN chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

EXPOSE 8000

# Dev entrypoint: installs composer deps if needed, starts artisan serve
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
