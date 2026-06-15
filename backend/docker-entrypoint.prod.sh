#!/bin/sh
# Entrypoint de producción para maya_logs (backend Laravel).
# Selecciona el comando final según CONTAINER_ROLE: api | worker | scheduler | reverb.
# NO ejecuta `composer install` ni `php artisan migrate` — eso se hace en build
# o en un Job/hook de Helm (pre-upgrade).
set -eu

cd /var/www/html

ROLE="${CONTAINER_ROLE:-api}"

# Limpiar cachés del build (config.php/route.php pueden tener env distinta).
# Tolera readOnlyRootFilesystem si los directorios están como emptyDir
# (recomendado en el chart).
rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php \
      bootstrap/cache/packages.php bootstrap/cache/services.php 2>/dev/null || true

# Regenerar caché de paquetes (tras copiar vendor en build).
php artisan package:discover --ansi >/dev/null 2>&1 || true

# Cachear config y rutas con la env final del pod.
php artisan config:cache --ansi >/dev/null 2>&1 || true
php artisan route:cache --ansi >/dev/null 2>&1 || true

case "$ROLE" in
    api)
        # php-fpm en foreground; el sidecar nginx del chart hace proxy_pass.
        exec php-fpm --nodaemonize
        ;;
    worker)
        # Worker AMQP del bus de logs: consume eventos de `logs.ingest`
        # y persiste en `log_mgmt_db`. La cola la define maya_platform/shared-messaging.
        # Permite override por args desde el chart (worker.command); por defecto
        # ejecuta el comando logs:consume hardcoded para este servicio.
        if [ "$#" -gt 0 ]; then
            exec php artisan "$@"
        fi
        exec php artisan logs:consume
        ;;
    scheduler)
        # Loop del scheduler de Laravel. maya_logs ejecuta `EvaluateNotificationRules`
        # de forma programada cuando se habilite el scheduler en el chart.
        exec php artisan schedule:work --no-interaction
        ;;
    reverb)
        # Servidor WebSocket de Laravel Reverb (notificaciones en tiempo real).
        exec php artisan reverb:start --host=0.0.0.0 --port=8080
        ;;
    *)
        # Fallback: ejecuta el comando arbitrario pasado como CMD/args.
        exec "$@"
        ;;
esac
