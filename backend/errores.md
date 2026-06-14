# Errores de arquitectura — maya_logs/backend

> Auditoría automática contra el flujo Controller -> Service (DTOs) -> Repository -> Model + Policies.

## Resumen

- Archivos revisados: **97**
- Violaciones: **12** — CRITICAL: 0 · HIGH: 0 · MEDIUM: 3 · LOW: 9

## Violaciones por severidad

| Severidad | Regla | Archivo | Línea | Problema | Corrección sugerida |
|---|---|---|---|---|---|
| MEDIUM | R1 | `app/Http/Controllers/Api/ArchivedLogController.php` | 25-38 | index() contiene lógica de parsing/normalización de input en el controller: explode/trim/array_filter del campo severity, casting condicional de application_id, per_page con fallback (>0 ? : 15) y lectura de filtros vía $request->input()/string()/integer() en vez de $request->validated(). Esta normalización debería vivir en el FormRequest (como ListLogsRequest::getParsedSeverity / toFilterDto) o en un FilterDto, no inline en el controller. El FormRequest ListArchivedLogsRequest ya existe pero no expone los valores normalizados. | Mover el parsing de severity y la normalización de per_page/application_id a ListArchivedLogsRequest (p.ej. getParsedSeverity() y un toFilterDto()), igual que ListLogsRequest, y que el controller solo pase el DTO/valores ya saneados al service. |
| MEDIUM | R2 | `app/Notifications/Rules/ErrorSpikeRule.php` | 25-28 | Esta regla de dominio (evaluador de notificaciones) accede directamente a Eloquent con `Log::query()->whereIn('severity',...)->where('created_at','>=',$windowStart)->count()`. Es lógica de acceso a datos fuera de la capa Repository: la única capa autorizada a usar query builder/Eloquent debería ser app/Repositories. No es un Service formal pero ejecuta una query de conteo de negocio que debería encapsularse en un método del LogRepository (p.ej. countBySeveritiesSince()). | Añadir `LogRepositoryInterface::countBySeveritiesSince(array $severities, CarbonInterface $since): int` y consumirlo por inyección en ErrorSpikeRule, eliminando `Log::query()` de la clase. |
| MEDIUM | R2 | `app/Services/SeverityRankingService.php` | 33-50 | El Service manipula directamente el query builder de Eloquent (orderByRaw/orderByDesc sobre Illuminate\Database\Eloquent\Builder), responsabilidad que segun R4 pertenece a la capa Repository. No hace data fetching ni usa Model::where/find/create/DB:: (no es violacion CRITICAL del flujo), y de hecho es invocado por ArchivedLogRepository::applyRankOrder (Repositories/Eloquent/ArchivedLogRepository.php:91-93) como delegado de logica de dominio, por lo que el acoplamiento esta encapsulado en el repo. Aun asi, la construccion del CASE/ORDER BY (SQL) vive en app/Services en lugar de app/Repositories. | Mover la construccion del fragmento de ordenacion (applyRankOrder) a un metodo/trait del Repository o a un Eloquent scope en el modelo ArchivedLog, dejando en el Service solo la regla de dominio pura (la jerarquia de severidad y validateSortDirection), que no toca el Builder. |
| LOW | R2 | `app/Dtos/DashboardSummaryDto.php` | 13-16 | El DTO transporta arrays asociativos crudos (severityCards y applicationTotals) provenientes del LogService en lugar de DTOs anidados. El DTO existe y está bien tipado vía phpdoc, pero los agregados del dashboard viajan como arrays asociativos, lo que diluye la regla R2 (Services devuelven DTOs, no arrays crudos). Nota: la fuente de esos arrays está en LogService, fuera de este lote. | Si se quiere endurecer R2, modelar SeverityCardDto / ApplicationTotalDto y que el service los devuelva; de lo contrario dejar como excepción documentada para datos agregados. |
| LOW | R1 | `app/Http/Controllers/Api/ArchivedLogController.php` | 23-42 | index() lee la request mediante $request->input('severity'), $request->integer('per_page'), $request->string('date_from') etc. en lugar de $request->validated(), pese a tener FormRequest. No es input sin validar (las reglas existen en ListArchivedLogsRequest), pero contradice la convención 'siempre $request->validated()'. | Consumir los valores desde $request->validated() o mediante accesores del FormRequest. |
| LOW | R8 | `app/Http/Controllers/Api/DashboardController.php` | 25-27 | index() construye la respuesta con response()->json(['data' => (new DashboardSummaryResource($dto))->resolve($request)]) en vez de usar el helper de envelope estándar (RespondsWithEnvelope::okData) que sí usan ArchivedLogController/ErrorCodeController. Sigue devolviendo un Resource (cumple R8) pero introduce inconsistencia de wire-format/envelope. | Usar el trait RespondsWithEnvelope y $this->okData(new DashboardSummaryResource($dto)) para uniformar el envelope con el resto de endpoints. |
| LOW | R1 | `app/Http/Controllers/Api/ErrorCodeController.php` | 28-32 | index() lee filtros con $request->string('search')/$request->input('application_id') en vez de $request->validated(); las reglas existen en ListErrorCodesRequest (PaginatedFilterRequest) así que el input está validado, pero rompe la convención 'siempre validated()'. | Leer los filtros desde validated() o exponer accesores tipados en el FormRequest. |
| LOW | R7 | `app/Http/Requests/Api/StoreCommentRequest.php` | 11-14 | authorize() devuelve true. Es aceptable porque la autorización de quién puede comentar se delega al middleware JWT de la ruta y no hay regla de ownership en creación, pero conviene documentarlo como los List requests (comentario 'Auth delegada al middleware JWT'). | Añadir comentario explicando la delegación al middleware JWT, consistente con ListArchivedLogsRequest. |
| LOW | R2 | `app/Services/ArchivedLogService.php` | 66-80,89,132 | El Service expone `findModelOrFail(int): ArchivedLog` y acepta el modelo Eloquent `ArchivedLog` en `updateArchivedFields()`/`delete()`, devolviendo/transportando el modelo a la capa Controller. Estricta lectura de R2 (Service nunca devuelve modelos). Mitigado: está documentado y el modelo solo se usa para el policy gate `$this->authorize($action, $model)` en el controlador (verificado en ArchivedLogController:53-55,73-75), no para lógica de negocio; el flujo Controller->Service->Repository se preserva. Excepción pragmática estándar de Laravel (la Policy requiere la instancia del modelo). | Opcional: resolver la autorización dentro del Service (pasando el id/contexto JWT a la Policy) para no exponer el modelo, o documentar formalmente la excepción como patrón aceptado del proyecto. |
| LOW | R2 | `app/Services/CommentService.php` | 46-60 | El Service expone `findModelOrFail(int): Comment` devolviendo un modelo Eloquent al Controller. El modelo se consume únicamente en `GateFacade::forUser($userModel)->authorize('update'\|'delete', $comment)` (verificado en CommentController:28-39,50-61). Misma excepción pragmática de policy gate; el flujo de capas se mantiene y findOrFail() devuelve DTO para el read path. | Opcional: mover la decisión de autorización al Service o documentar formalmente el patrón de excepción para el gate. |
| LOW | R2 | `app/Services/ErrorCodeService.php` | 61-75 | El Service expone `findModelOrFail(int): ErrorCode` devolviendo un modelo Eloquent al Controller. Igual que en ArchivedLogService: el modelo solo alimenta el policy gate `$this->authorize('update'\|'delete', $errorCode)` (verificado en ErrorCodeController:57-59,69-71). Desviación leve de R2 sin impacto en seguridad ni en el flujo de capas (el read path real usa DTO vía findOrFail()). | Opcional: encapsular la autorización en el Service o estandarizar/documentar la excepción del policy-gate a nivel de proyecto. |
| LOW | R2 | `app/Services/PanelUserService.php` | 47-50 | `resolveAuthenticatable(string): ?User` devuelve un modelo Eloquent User. Está explícitamente acotado en docblock a `Gate::forUser` (requiere Authenticatable) y advierte que el modelo no debe usarse para nada más. Desviación menor y consciente de R2, sin impacto. | Aceptable como excepción documentada; sin acción salvo querer aislarlo en un colaborador de auth dedicado. |

## Archivos revisados (97)

| Archivo | Capa | Cumple |
|---|---|---|
| `app/Console/Commands/ConsumeLogs.php` | Other | ✅ |
| `app/Console/Commands/EvaluateNotificationRules.php` | Other | ✅ |
| `app/Console/Commands/SeedRuleData.php` | Other | ✅ |
| `app/Dtos/ApplicationRefDto.php` | DTO | ✅ |
| `app/Dtos/ArchivedLogDto.php` | DTO | ✅ |
| `app/Dtos/CommentDto.php` | DTO | ✅ |
| `app/Dtos/DashboardSummaryDto.php` | DTO | ✅ |
| `app/Dtos/ErrorCodeDto.php` | DTO | ✅ |
| `app/Dtos/ErrorCodeRefDto.php` | DTO | ✅ |
| `app/Dtos/LogDto.php` | DTO | ✅ |
| `app/Dtos/LogFilterDto.php` | DTO | ✅ |
| `app/Dtos/UserRefDto.php` | DTO | ✅ |
| `app/Enums/ApplicationPluckScope.php` | Other | ✅ |
| `app/Enums/Severity.php` | Other | ✅ |
| `app/Events/ArchivedLogFieldsWereUpdated.php` | Other | ✅ |
| `app/Events/ArchivedLogWasDeleted.php` | Other | ✅ |
| `app/Events/LogWasArchived.php` | Other | ✅ |
| `app/Http/Controllers/Api/AbstractCommentController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/ApplicationController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/ArchivedLogCommentController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/ArchivedLogController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/CommentController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/Concerns/ResolvesCommentActor.php` | Other | ✅ |
| `app/Http/Controllers/Api/DashboardController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/ErrorCodeCommentController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/ErrorCodeController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/HealthCheckController.php` | Controller | ✅ |
| `app/Http/Controllers/Api/LogController.php` | Controller | ✅ |
| `app/Http/Controllers/Controller.php` | Controller | ✅ |
| `app/Http/Middleware/SetLocaleFromAcceptLanguage.php` | Other | ✅ |
| `app/Http/Requests/Api/ListArchivedLogsRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Api/ListErrorCodesRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Api/ListLogsRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Api/StoreCommentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Api/StoreErrorCodeRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Api/UpdateArchivedLogRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Api/UpdateCommentRequest.php` | FormRequest | ✅ |
| `app/Http/Requests/Api/UpdateErrorCodeRequest.php` | FormRequest | ✅ |
| `app/Http/Resources/ApplicationRefResource.php` | Resource | ✅ |
| `app/Http/Resources/ArchivedLogResource.php` | Resource | ✅ |
| `app/Http/Resources/ArchiveLogResultResource.php` | Resource | ✅ |
| `app/Http/Resources/CommentResource.php` | Resource | ✅ |
| `app/Http/Resources/DashboardSummaryResource.php` | Resource | ✅ |
| `app/Http/Resources/ErrorCodeResource.php` | Resource | ✅ |
| `app/Http/Resources/LogResource.php` | Resource | ✅ |
| `app/Http/Resources/ResolveLogResultResource.php` | Resource | ✅ |
| `app/Models/Application.php` | Model | ✅ |
| `app/Models/ArchivedLog.php` | Model | ✅ |
| `app/Models/Comment.php` | Model | ✅ |
| `app/Models/ErrorCode.php` | Model | ✅ |
| `app/Models/Log.php` | Model | ✅ |
| `app/Models/NotificationRule.php` | Model | ✅ |
| `app/Models/NotificationRuleRun.php` | Model | ✅ |
| `app/Models/User.php` | Model | ✅ |
| `app/Notifications/Rules/ErrorSpikeRule.php` | Other | ❌ |
| `app/Notifications/Rules/ScheduledNotificationRule.php` | Other | ✅ |
| `app/Observers/CommentObserver.php` | Other | ✅ |
| `app/Observers/ErrorCodeObserver.php` | Other | ✅ |
| `app/Policies/ArchivedLogPolicy.php` | Policy | ✅ |
| `app/Policies/CommentPolicy.php` | Policy | ✅ |
| `app/Policies/Concerns/ResolvesJwtContext.php` | Other | ✅ |
| `app/Policies/ErrorCodePolicy.php` | Policy | ✅ |
| `app/Providers/AppServiceProvider.php` | Other | ✅ |
| `app/Providers/AuthServiceProvider.php` | Other | ✅ |
| `app/Providers/EventServiceProvider.php` | Other | ✅ |
| `app/Repositories/Contracts/ApplicationRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/ArchivedLogRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/CommentRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/ErrorCodeRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/LogIngestionRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/LogRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Contracts/UserRepositoryInterface.php` | Repository | ✅ |
| `app/Repositories/Eloquent/ApplicationRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/ArchivedLogRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/CommentRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/ErrorCodeRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/LogIngestionRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/LogRepository.php` | Repository | ✅ |
| `app/Repositories/Eloquent/UserRepository.php` | Repository | ✅ |
| `app/Rules/AcceptableTutorialUrl.php` | Other | ✅ |
| `app/Services/ApplicationService.php` | Service | ✅ |
| `app/Services/ArchivedFieldsValidator.php` | Service | ✅ |
| `app/Services/ArchivedLogService.php` | Service | ✅ |
| `app/Services/CommentContentSanitizer.php` | Service | ✅ |
| `app/Services/CommentService.php` | Service | ✅ |
| `app/Services/Contracts/ApplicationServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/ArchivedLogServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/CommentContentSanitizerInterface.php` | Service | ✅ |
| `app/Services/Contracts/CommentServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/ErrorCodeServiceInterface.php` | Service | ✅ |
| `app/Services/Contracts/LogServiceInterface.php` | Service | ✅ |
| `app/Services/ErrorCodeService.php` | Service | ✅ |
| `app/Services/LogIngestionService.php` | Service | ✅ |
| `app/Services/LogPayload.php` | DTO | ✅ |
| `app/Services/LogService.php` | Service | ✅ |
| `app/Services/PanelUserService.php` | Service | ✅ |
| `app/Services/SeverityRankingService.php` | Service | ✅ |

