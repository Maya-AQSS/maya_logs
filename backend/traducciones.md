# Auditoría lang/i18n — maya_logs (backend)

## Estado de infraestructura
- Directorio lang/: **PARCIAL — existe `es/` y `en/`, FALTA `va/` (valenciano)**. Además, `config/app.php` declara `'supported_locales' => ['es', 'en']` (línea 98) y el middleware `SetLocaleFromAcceptLanguage` usa ese mismo default — el valenciano (`va`) no está contemplado en ningún punto del backend.
- Archivos de idioma presentes: solo `api.php` y `logs.php` en cada locale. **FALTAN los archivos `error_codes.php`, `comments.php`, `archived_logs.php` y `validation.php`** que el código SÍ referencia vía `__()` (ver hallazgo crítico T-01).
- Helper de traducción en uso: **sí**, `__()` se usa correctamente en 10 archivos (LogController, ResolvesCommentActor, StoreErrorCodeRequest, UpdateErrorCodeRequest, ErrorCodePolicy, ArchivedLogPolicy, PanelUserService, AcceptableTutorialUrl, CommentContentSanitizer, SetLocaleFromAcceptLanguage). El código está bien internacionalizado en su mayoría; el problema es la infraestructura de archivos lang incompleta, no strings hardcodeados.

## Resumen
- Archivos revisados: 95
- Archivos con strings sin traducir: 1 (CommentController.php — 2 `abort()` literales)
- Total de hallazgos: 5 (1 string hardcodeado en 2 sitios + 3 hallazgos de infraestructura de claves lang ausentes + locale `va` ausente)
- Paridad de locales (es/en/va): **`va` NO EXISTE**. Entre `es` y `en` hay paridad de archivos (`api.php`, `logs.php`) pero ambos están incompletos respecto a las claves referenciadas por el código.
- Severidad global: **high** (claves lang referenciadas pero inexistentes → el usuario ve la clave cruda en vez del mensaje; agravado por `va` ausente pese a ser proyecto CEEDCV trilingüe)

## Hallazgos por archivo

### app/Http/Controllers/Api/CommentController.php
| Línea | String hardcodeado | Contexto | Clave lang sugerida |
|------|--------------------|----------|---------------------|
| 36 | "Unauthorized" | `abort(403, 'Unauthorized')` en `update()` — usuario no resoluble en directorio | `api.auth.forbidden` (ya existe) |
| 58 | "Unauthorized" | `abort(403, 'Unauthorized')` en `destroy()` — usuario no resoluble en directorio | `api.auth.forbidden` (ya existe) |

### Hallazgos de infraestructura (claves referenciadas sin archivo lang)
| Clave referenciada | Archivo(s) que la usan | Archivo lang esperado | Estado |
|--------------------|------------------------|------------------------|--------|
| `logs.not_authorized` | LogController.php:82 | `lang/{es,en}/logs.php` | **AUSENTE** (existe `archived_log_forbidden` pero el código pide `not_authorized`) → T-02 |
| `error_codes.validation.*` (11 claves: application_id_required/invalid, code_required/max/unique, name_required/max, description_max, file_max, line_integer/min) | StoreErrorCodeRequest.php, UpdateErrorCodeRequest.php | `lang/{es,en}/error_codes.php` | **ARCHIVO INEXISTENTE** → T-01 |
| `comments.editor.comment_too_large`, `comments.editor.image_invalid_type`, `comments.editor.image_too_large` | CommentContentSanitizer.php:48,65,71,77 | `lang/{es,en}/comments.php` | **ARCHIVO INEXISTENTE** → T-01 |
| `archived_logs.validation.url_tutorial` | AcceptableTutorialUrl.php:27,36,44,64 | `lang/{es,en}/archived_logs.php` | **ARCHIVO INEXISTENTE** → T-01 |
| `validation.required` | CommentContentSanitizer.php:37 | `lang/{es,en}/validation.php` (estándar Laravel) | **ARCHIVO INEXISTENTE** (no se publicó el `validation.php` de Laravel) → T-01 |

> Impacto: cuando `__('clave.inexistente')` no encuentra traducción, Laravel devuelve la **cadena de la clave literal** (ej. `error_codes.validation.code_required`) al cliente como mensaje de validación/error. Es un fallo funcional visible al usuario, no solo cosmético.

## Archivos revisados sin incidencias

**Controllers**: AbstractCommentController, ApplicationController, ArchivedLogCommentController, ArchivedLogController, CommentController (salvo los 2 abort), ErrorCodeCommentController, ErrorCodeController, DashboardController, HealthCheckController, LogController, Controller, Concerns/ResolvesCommentActor (usa `__()` correctamente).

**Requests**: ListArchivedLogsRequest, ListErrorCodesRequest, ListLogsRequest, StoreCommentRequest, StoreErrorCodeRequest (usa `__()`), UpdateArchivedLogRequest, UpdateCommentRequest, UpdateErrorCodeRequest (usa `__()`).

**Resources**: ApplicationRefResource, ArchivedLogResource, ArchiveLogResultResource, CommentResource, DashboardSummaryResource, ErrorCodeResource, LogResource, ResolveLogResultResource (los `'message' =>` son datos del dominio, no texto de UI).

**Services**: ApplicationService, ArchivedFieldsValidator, ArchivedLogService, CommentContentSanitizer (usa `__()`), CommentService, ErrorCodeService, LogIngestionService (excepciones `UnrecoverableIngestionException`/`InvalidArgumentException` son técnicas de desarrollador, no expuestas al usuario), LogPayload, LogService, PanelUserService (usa `__()`), SeverityRankingService, + todos los Contracts.

**Policies**: ArchivedLogPolicy (usa `__()`), CommentPolicy, ErrorCodePolicy (usa `__()`), Concerns/ResolvesJwtContext.

**Rules**: AcceptableTutorialUrl (usa `__()`).

**Middleware**: SetLocaleFromAcceptLanguage.

**Notifications**: Rules/ErrorSpikeRule, Rules/ScheduledNotificationRule (solo `Log::error()` interno, no traducible).

**Console Commands**: ConsumeLogs, EvaluateNotificationRules, SeedRuleData (`'message' => 'QA error-spike seed'` es dato de seeder, no UI).

**Resto** (sin texto de cara al usuario): Dtos/*, Enums/*, Events/*, Models/*, Observers/*, Providers/*, Repositories/* (el `'message'` en ArchivedLogRepository:156 y LogService:90 es campo de dominio del log).

## Recomendaciones (priorizadas)

1. **[CRÍTICO — T-01] Crear los archivos lang ausentes** en `lang/es/` y `lang/en/` con todas las claves referenciadas por el código, o el usuario verá claves crudas:
   - `error_codes.php` con el sub-array `validation.*` (11 claves).
   - `comments.php` con `editor.comment_too_large`, `editor.image_invalid_type`, `editor.image_too_large`.
   - `archived_logs.php` con `validation.url_tutorial`.
   - Publicar el `validation.php` estándar de Laravel: `php artisan lang:publish` (o copiar el de un servicio hermano del ecosistema).

2. **[ALTO — T-02] Corregir el desajuste de clave en LogController.php:82**: el código pide `logs.not_authorized` pero `logs.php` define `archived_log_forbidden`. Unificar: añadir la clave `not_authorized` a `logs.php` (es/en) o cambiar la referencia en el controlador.

3. **[ALTO] Crear `lang/va/`** (valenciano) replicando `api.php`, `logs.php` y los nuevos archivos del punto 1, y añadir `'va'` a `config/app.php` → `supported_locales` (línea 98). Es un proyecto CEEDCV trilingüe es/en/va; el backend hoy ignora el valenciano por completo.

4. **[MEDIO] Sustituir los 2 `abort(403, 'Unauthorized')`** de CommentController.php:36 y :58 por `abort(403, __('api.auth.forbidden'))` (la clave ya existe en `api.php`).

5. **[BAJO] Añadir un test de cobertura de claves lang** (Pest) que recorra los `__()` del código y verifique que cada clave resuelve a una traducción existente en es/en/va, para evitar regresiones de este tipo.
