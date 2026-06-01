#!/bin/sh
set -e

# ─── Log Management Dashboard entrypoint ───────────────────────
cd /var/www/html

# Asegurar que bootstrap/cache existe (gitignored, no llega en clones limpios)
mkdir -p bootstrap/cache
chmod -R 775 bootstrap/cache
chown -R www-data:www-data bootstrap/cache 2>/dev/null || true

# Limpiar cache de bootstrap obsoleto
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
# config.php cacheado congela env() — eliminarlo permite que tests/bootstrap.php
# imponga sqlite ANTES de Laravel cargar config. Sin esto, pest --coverage ejecuta
# contra la BD pgsql cacheada.
rm -f bootstrap/cache/config.php

# Composer dependencies (volumen bind monta packages/ en runtime)
if [ ! -f "vendor/autoload.php" ] || [ "composer.json" -nt "vendor/autoload.php" ]; then
    echo "[entrypoint] Installing composer dependencies..."
    # Sync only maya/* path packages in lock (handles stale lock when new shared package is added)
    composer update "maya/*" --no-install --no-interaction --ignore-platform-reqs --no-scripts 2>/dev/null || true
    composer install --optimize-autoloader --no-interaction --no-scripts
else
    echo "[entrypoint] Composer deps up to date"
fi

# Fix laravel-queue-rabbitmq Consumer::$currentJob visibility (Laravel 13 compat)
# Worker::$currentJob is public in Laravel 13; the package declares it protected → FatalError.
sed -i 's/protected \$currentJob;/public \$currentJob;/' \
  vendor/vladimir-yuldashev/laravel-queue-rabbitmq/src/Consumer.php 2>/dev/null || true

# Storage y permisos
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chmod -R 775 storage
chown -R www-data:www-data storage 2>/dev/null || true

# Package discovery
php artisan package:discover --ansi 2>/dev/null || true

# NOTE: config:cache eliminado a propósito. Cachear config hace que
# `php artisan test --coverage` corra con env vars cacheadas (DB_CONNECTION del
# shell), pisando el tests/bootstrap.php que fuerza sqlite. El coste de no
# cachear es <50ms por request — aceptable en desarrollo.

# Devolver al UID/GID del host los archivos generados por composer (que corre
# como root en este entrypoint). Detectamos el UID del host mirando el owner
# del composer.json bind-mounted (siempre presente, conserva UID original).
# Sin esto, `composer update` desde el host falla con "Permission denied"
# porque vendor/ y composer.lock quedan root:root tras este script.
HOST_UID="$(stat -c %u /var/www/html/composer.json 2>/dev/null || echo 0)"
HOST_GID="$(stat -c %g /var/www/html/composer.json 2>/dev/null || echo 0)"
if [ "$HOST_UID" != "0" ]; then
    chown -R "${HOST_UID}:${HOST_GID}" \
        /var/www/html/vendor \
        /var/www/html/composer.lock \
        /var/www/html/bootstrap/cache \
        2>/dev/null || true
fi

exec php artisan serve --host=0.0.0.0 --port=8000
