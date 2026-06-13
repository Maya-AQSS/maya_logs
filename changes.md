# changes.md — refactor/unify-cross-app (maya_logs)

Registro de cambios funcionales observables introducidos al adoptar los
paquetes compartidos 0.16.0 de maya_platform (PLAN-UNIFY-CROSS-APP §7).
Los refactors que preservan comportamiento NO se registran aquí.

---

## [UNIFY-LOGS-1] Render JSON uniforme para excepciones no-AuthorizationException

- **Fecha**: 2026-06-12
- **Severidad**: LOW
- **Qué cambió**: las excepciones en rutas `api/*` distintas de
  `AuthorizationException` pasan del render JSON por defecto de Laravel al
  envelope uniforme de `Maya\Http\Exceptions\JsonExceptionRenderer`
  (`{"message": "..."}`, más `{"errors": {...}}` en ValidationException).
  Diferencias observables menores: (1) el mensaje genérico de un 500 en
  producción pasa de `"Server Error"` (Laravel) a `"Internal Server Error"`
  (Symfony statusTexts); (2) los 5xx en producción ya no exponen el mensaje
  crudo de la excepción (antes dependía del handler por defecto). El renderable
  propio de logs para `AuthorizationException` (shape `{error:{code,message}}`)
  se CONSERVA registrándolo ANTES del catch-all: Laravel evalúa los renderables
  en orden de registro y gana la primera respuesta no-null, por lo que el
  "override registrándolo después" indicado en el plan no es ejecutable — el
  orden invertido logra la misma semántica y queda documentado en
  `backend/bootstrap/app.php`.
- **Por qué**: unificación del render de errores API en las 5 apps Maya
  (shared-http-laravel 0.16.0).
- **Endpoint(s) afectado(s)**: todos los `api/*` cuando lanzan excepciones
  distintas de AuthorizationException.
- **Impacto en cliente**: bajo — el frontend ya consumía `{message}` para
  validación/404; solo cambian textos genéricos de 5xx.
- **Decidido por**: agente unify-cross-app (fase logs), según PLAN §7 cambio
  previsto nº 2.

## [UNIFY-LOGS-2] Búsqueda accent-insensitive en logs y códigos de error

- **Fecha**: 2026-06-12
- **Severidad**: LOW (mejora funcional)
- **Qué cambió**: la búsqueda de texto libre (logs: message/code/name/file;
  error_codes: code/name) pasa de `ILIKE` literal a comparación accent-folded
  con `Maya\Search\AccentFold`: el needle se pliega en PHP (lowercase + sin
  acentos) y las columnas se pliegan en SQL driver-aware
  (`sqlFoldedLowerColumn`: pgsql `translate(lower(...))` + replace de
  ligaduras; sqlite `lower(...)`). Ahora "facturacion" encuentra
  "Facturación" (antes no). Cambio mecánico asociado: el escape de comodines
  LIKE pasa de `!` (LikeEscaper local, requería `ESCAPE '!'` en cada query) a
  backslash (estándar SQL, sin cláusula ESCAPE). El input del usuario se
  trataba y se sigue tratando como literal — sin cambio observable por el
  escape en sí. En el fallback sqlite de logs, `INSTR(LOWER(...))` se sustituye
  por `LIKE` con needle escapado (misma semántica literal).
- **Por qué**: PLAN §7 — LikeEscaper local → Maya\Search\AccentFold
  (shared-http-laravel 0.16.0), con accent-folding como cambio funcional
  previsto.
- **Endpoint(s) afectado(s)**: `GET /api/v1/logs` (param `search`),
  `GET /api/v1/error-codes` (param `search`).
- **Impacto en cliente**: positivo — más resultados relevantes con términos
  sin acentos; ningún cambio de shape.
- **Decidido por**: agente unify-cross-app (fase logs), según PLAN §7.

## [UNIFY-LOGS-3] Prefijo de log del consumer AMQP pasa a FQCN

- **Fecha**: 2026-06-12
- **Severidad**: INFO
- **Qué cambió**: `logs:consume` ahora extiende
  `Maya\Messaging\Console\ConsumeQueueCommand`; la política de clasificación
  de errores (Unrecoverable→drop, QueryException→retry, resto→report+drop) es
  idéntica (logs era la implementación de referencia), pero los mensajes de
  log internos del consumer usan `static::class` como prefijo
  (`App\Console\Commands\ConsumeLogs: ...`) en lugar del literal
  `ConsumeLogs: ...`. `App\Exceptions\UnrecoverableIngestionException` se
  sustituye por la del paquete (`Maya\Messaging\Exceptions\...`).
- **Por qué**: PLAN §7 — unificación de consumers AMQP en
  shared-messaging-laravel 0.16.0.
- **Endpoint(s) afectado(s)**: ninguno (worker CLI); solo afecta a quien
  grepee los logs internos del worker por el prefijo literal.
- **Impacto en cliente**: ninguno.
- **Decidido por**: agente unify-cross-app (fase logs), según PLAN §7.

## [UNIFY-LOGS-4] Telemetría LAR-LOG-010 en 404 de error-codes update/destroy/comments

- **Fecha**: 2026-06-12
- **Severidad**: INFO
- **Qué cambió**: al pasar los lookups de `ErrorCodeController.update/destroy`
  y la verificación del padre en `ErrorCodeCommentController` por
  `ErrorCodeService` (cumplimiento de capas F4-B2), un id inexistente ahora
  publica telemetría `LAR-LOG-010` a `maya.logs` (vía `ResilientLogPublisher`),
  igual que ya hacía `GET /error-codes/{id}`. Antes el `findOrFail` directo del
  controlador no publicaba nada. La respuesta HTTP (404, mismo JSON) no cambia.
- **Por qué**: F4-B2 — Controller→Service→Repository estricto; el path de
  lookup por id del service es el canónico e incluye telemetría.
- **Endpoint(s) afectado(s)**: `PUT/PATCH|DELETE /api/v1/error-codes/{id}`,
  `GET|POST /api/v1/error-codes/{id}/comments` (solo cuando el id no existe).
- **Impacto en cliente**: ninguno en el wire format; solo más eventos de
  telemetría interna ante 404.
- **Decidido por**: agente F4-B2 (maya_logs), excepción de capas aceptada
  "modelo solo para authorize vía service".

## [V2.logs] Validación de audiencia JWT por defecto (JWT_AUDIENCE)

- **Fecha**: 2026-06-13
- **Severidad**: MEDIUM (seguridad)
- **Qué cambió**: `backend/.env.example` pasa `JWT_AUDIENCE=` (vacío) a
  `JWT_AUDIENCE=maya-logs`. Vacío equivalía a aceptar tokens emitidos para
  cualquier client del realm (sin validar el claim `aud`); el nuevo valor exige
  que el token vaya dirigido al client `maya-logs`, rechazando tokens emitidos
  para otros clients. Solo cambia el ejemplo de entorno; los despliegues que ya
  fijen `JWT_AUDIENCE` por env no se ven afectados. Despliegues nuevos que
  copien `.env.example` empezarán a validar audiencia (postura fail-closed).
- **Por qué**: defensa en profundidad — un token robado de otro client del
  realm no debe ser válido contra la API de logs (`shared-auth-laravel`).
- **Endpoint(s) afectado(s)**: todos los `api/*` protegidos por validación JWT
  (la audiencia se comprueba en el middleware de auth del paquete compartido).
- **Impacto en cliente**: ninguno para el SPA legítimo (su token va dirigido a
  `maya-logs`); tokens de otros clients dejarían de ser aceptados.
- **Decidido por**: agente V2 dedup/seguridad (fase logs).
