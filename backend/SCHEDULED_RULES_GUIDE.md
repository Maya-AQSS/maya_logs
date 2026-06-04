# Scheduled Notification Rules — Guía de Uso

## Descripción General

Sistema de reglas programadas que se ejecutan automáticamente vía scheduler de Laravel. Las reglas evalúan condiciones sobre los logs en tiempo real y publican alertas al dashboard sin intervención manual.

## Configuración

### Variables de Entorno

Agregadas a `.env`:

```bash
# Ventana de análisis en segundos (default: 60 segundos)
LOGS_ERROR_SPIKE_WINDOW_SECONDS=60

# Umbral mínimo de errores para disparar la alerta (default: 10)
LOGS_ERROR_SPIKE_THRESHOLD=10
```

### Ajuste Dinámico

Cambiar el umbral a 5 errores en 2 minutos:

```bash
LOGS_ERROR_SPIKE_WINDOW_SECONDS=120
LOGS_ERROR_SPIKE_THRESHOLD=5
```

Reiniciar el scheduler para que los cambios tengan efecto (sin necesidad de reiniciar el contenedor).

## Ejecución Manual

Probar la regla sin esperar al scheduler:

```bash
# En local
php artisan notifications:evaluate-rules

# En Docker
docker compose exec logs-backend php artisan notifications:evaluate-rules --help
docker compose exec logs-backend php artisan notifications:evaluate-rules
```

**Output esperado:**

```
Published 1 notification(s)
```

Si no hay spike, no hay output pero el exit code es 0.

## Monitoring

Ver los logs de ejecución de la regla:

```bash
# Last 50 lines
tail -50 storage/logs/laravel.log | grep notifications:evaluate-rules

# O filtrar por "ErrorSpikeRule"
grep "ErrorSpikeRule\|error_spike" storage/logs/laravel.log
```

## Extensión: Agregar Nuevas Reglas

### 1. Implementar la Interfaz

```php
<?php
namespace App\Notifications\Rules;

use Maya\Messaging\Publishers\NotificationPublisher;

class MyCustomRule implements ScheduledNotificationRule
{
    public function evaluate(NotificationPublisher $publisher): int
    {
        // Tu lógica aquí
        return 0; // o 1 si publicó una notificación
    }
}
```

### 2. Registrar en el Comando

Modificar `EvaluateNotificationRules.php`:

```php
$rules = [
    new ErrorSpikeRule(...),
    new MyCustomRule(...),
];

foreach ($rules as $rule) {
    $published += $rule->evaluate($publisher);
}
```

### 3. Traducir

Agregar claves en `lang/es/notifications.php` y `lang/en/notifications.php`.

## Estructura del Payload

Toda notificación publica un JSON hacia RabbitMQ:

```json
{
  "app": "maya-logs",
  "type": "logs.error_spike",
  "recipient_keycloak_id": "",
  "title": "Aumento anómalo de errores detectado",
  "body": ":count críticos/altos en período de análisis",
  "channels": ["app"],
  "metadata": {
    "count": 15
  },
  "scope": "dashboard",
  "is_critical": true,
  "created_at": "2026-06-04T10:30:00Z"
}
```

El campo `recipient_keycloak_id` está vacío porque scope es `dashboard` (alerta global).

## Deduplicación

Cada publish genera un UUID único (`message_id`). El servicio de ingestión de notificaciones del dashboard lo usa para evitar duplicados.

Si la regla se ejecuta 3 veces seguidas con la misma condición, se publican 3 notificaciones con 3 message_ids diferentes.

## Troubleshooting

### La regla no se ejecuta

1. Verificar que el scheduler está corriendo:
   ```bash
   ps aux | grep "schedule:work"
   ```

2. Si no lo está, iniciar:
   ```bash
   php artisan schedule:work
   ```

3. En producción, cron debe estar configurado:
   ```bash
   crontab -l | grep "schedule:run"
   ```

### La notificación no llega al dashboard

1. Verificar que `NotificationPublisher` está correctamente resuelto en el contenedor
2. Ver logs de RabbitMQ/publisher en `storage/logs/laravel.log`
3. Verificar que el tipo `logs.error_spike` está suscrito en el dashboard

### El umbral es muy sensible

Ajustar env vars:

```bash
LOGS_ERROR_SPIKE_WINDOW_SECONDS=300  # 5 minutos
LOGS_ERROR_SPIKE_THRESHOLD=50         # 50 errores
```

## Archivos Modificados

- `app/Notifications/Rules/ScheduledNotificationRule.php` — interfaz
- `app/Notifications/Rules/ErrorSpikeRule.php` — implementación
- `app/Console/Commands/EvaluateNotificationRules.php` — comando
- `routes/console.php` — registro del scheduler
- `config/logs.php` — configuración
- `lang/es/notifications.php` — traducciones ES
- `lang/en/notifications.php` — traducciones EN
- `.env.example` — variables de entorno
