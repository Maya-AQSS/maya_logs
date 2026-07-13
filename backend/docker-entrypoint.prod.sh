#!/bin/sh
# Entrypoint de producción para maya_logs (backend Laravel).
# Selecciona el comando final según CONTAINER_ROLE: api | worker | scheduler | reverb.
# NO ejecuta `composer install` ni `php artisan migrate` — eso se hace en build
# o en un Job/hook de Helm (pre-upgrade).
set -eu

cd /var/www/html

# ─── Secretos vía Vault Agent Injector (si el sidecar/init los inyectó) ───────
# El Vault Agent escribe /vault/secrets/config con líneas `export VAR="..."`.
# Lo cargamos ANTES de cachear config para que artisan vea las credenciales
# (APP_KEY, DB_PASSWORD, KEYCLOAK_CLIENT_SECRET, ...). Sin fichero (dev / Secret
# k8s clásico), este bloque es un no-op y la env llega por envFrom.
if [ -f /vault/secrets/config ]; then
    set -a
    . /vault/secrets/config
    set +a
fi

ROLE="${CONTAINER_ROLE:-api}"

# Limpiar cachés del build (config.php/route.php pueden tener env distinta).
# Tolera readOnlyRootFilesystem si los directorios están como emptyDir
# (recomendado en el chart).
rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php \
      bootstrap/cache/packages.php bootstrap/cache/services.php 2>/dev/null || true

# Regenerar caché de paquetes (tras copiar vendor en build).
# `package:discover` puede no encontrar nada nuevo — tolerable.
php artisan package:discover --ansi >/dev/null 2>&1 || true

# Cachear config y rutas con la env final del pod. Un fallo aquí indica env
# inválido o credenciales incompletas: abortar antes de servir tráfico.
if ! php artisan config:cache --ansi; then
    echo "[entrypoint] config:cache FAILED — aborting" >&2
    exit 1
fi
if ! php artisan route:cache --ansi; then
    echo "[entrypoint] route:cache FAILED — aborting" >&2
    exit 1
fi

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
