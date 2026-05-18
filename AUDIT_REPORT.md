# Audit — maya_logs

Generated: 2026-05-15
Auditor: maya-architecture-auditor

## Compliance summary

| Layer | Total checks | Passing | Failing |
|-------|-------------|---------|---------|
| Backend B1–B9 | 9 | 6 | 3 (+ 2 partial) |
| Frontend F1–F5 | 5 | 3 | 2 |
| Events E1–E5 | 5 | 2 | 2 (+ 1 N/A, 1 PREREQ-blocked) |

## Critical bug status (reported 2026-05-13)

**CONFIRMED FIXED.** `CommentController::storeForErrorCode` (referencing `$this->comments` and undeclared `$author`) no longer exists. The current `CommentController.php` contains only `update()` and `destroy()` (47 LOC). Store functionality was split into dedicated `ErrorCodeCommentController` and `ArchivedLogCommentController`, both clean.

## Backend violations

### B2 — Missing FormRequest / inline validate

- `app/Http/Controllers/Api/ArchivedLogController.php:67-70` — inline `$request->validate([...])` for `description` and `url_tutorial` fields. A dedicated `UpdateArchivedLogRequest` FormRequest is missing. CRITICAL.
- `app/Http/Controllers/Api/LogController.php` — `archive()` extracts `$request->attributes->get('jwt_user')` inline; JWT resolution is middleware-injected but the downstream authorization decision is embedded in controller body. LOW/borderline.

### B4 — Services return Eloquent models, not DTOs

`app/Dtos/` directory does not exist. Every service method returns Eloquent models or paginators of models (`Log`, `ArchivedLog`, `Comment`, `ErrorCode`). Resources access Eloquent relations directly via `$this->whenLoaded(...)`. Full DTO layer is absent. HIGH — large refactor scope.

### B7 — Raw JSON responses bypassing Resources

- `app/Http/Controllers/Api/LogController.php` — `archive()`, `resolve()`, and `stream()` return `response()->json([...])` with hand-built arrays alongside manually resolved Resources. HIGH.
- `app/Http/Controllers/Api/DashboardController.php` — returns inline `response()->json([...])` from service data. MEDIUM.
- `app/Http/Controllers/Api/ApplicationController.php` — returns inline JSON instead of `ApplicationRefResource::collection(...)`. MEDIUM.

### AppServiceProvider — duplicate singleton registrations (HIGH bug, not a B-check)

`app/Providers/AppServiceProvider.php` registers `CommentRepositoryInterface` and `CommentServiceInterface` twice (lines ~50-51 and ~56-58). Additionally, line ~57 references `CommentContentSanitizerInterface` without a `use` import statement — this will throw a PHP `BindingResolutionException` at runtime when the container resolves it. Fix priority: HIGH.

### B5 — DB access outside Repositories (from prior audit, verify still applies)

- `app/Services/LogIngestionService.php` — uses `DB::table('logs')->insert(...)` and `DB::table('error_codes')->insertOrIgnore(...)` directly, bypassing the repository layer. Should be encapsulated in a `LogIngestionRepository`. HIGH.

## Frontend violations

### F1 — useEffect + useState + fetch (no SWR/TanStack Query)

Neither `swr` nor `@tanstack/react-query` is a dependency. The entire frontend uses manual fetch orchestration. Affected files:

- `frontend/src/pages/LogsPage.tsx` — two `useEffect` blocks (`fetchApplications` + `fetchLogs`)
- `frontend/src/pages/ErrorCodesPage.tsx` — two `useEffect` blocks (`fetchApplications` + `fetchErrorCodes`)
- `frontend/src/pages/ArchivedLogsPage.tsx` — two `useEffect` blocks (`fetchApplications` + `fetchArchivedLogs`)
- `frontend/src/pages/ArchivedLogDetailPage.tsx` — `useEffect` + `useState` calling `fetchArchivedLog`
- `frontend/src/pages/LogDetailPage.tsx` — `useEffect` + `useState` calling `fetchLog`
- `frontend/src/pages/ErrorCodeCreatePage.tsx` — `useEffect` + `useState` for applications fetch
- `frontend/src/features/dashboard/widgets/RecentLogsWidget.tsx` — `useEffect` + `useState` calling `fetchLogs`
- `frontend/src/components/comments/CommentThread.tsx` — `useEffect` + `useState` + `fetchComments`

Note: `frontend/src/hooks/useLogStream.ts` intentionally uses `useEffect` for SSE polling — this is the correct abstraction (a dedicated custom hook wrapping the concern), not a violation.

CRITICAL — 8 files affected, structural gap requiring a phased migration.

### F2 — Inline form state instead of React Hook Form

`react-hook-form` is not a dependency. Forms use raw `useState`:

- `frontend/src/pages/ArchivedLogDetailPage.tsx` — edit form uses `useState<EditForm>` with manual `onChange` handlers
- `frontend/src/components/comments/CommentThread.tsx` — comment create/edit uses `useState` for `newContent` and `editingContent`

`frontend/src/pages/ErrorCodeCreatePage.tsx` uses manual form state for the create form. HIGH.

## Eventos / Audit (E1–E5)

### E-PREREQ — Missing package infrastructure (BLOCKER for E2 and E5)

`maya-shared-messaging-laravel` is missing two required artifacts:

- `packages/maya-shared-messaging-laravel/src/Contracts/AuditableEvent.php` — interface does not exist (only `MessagePublisher.php` is present in Contracts/)
- `packages/maya-shared-messaging-laravel/src/Listeners/RecordAuditableEvent.php` — the Listeners directory does not exist

This is an `maya_infra` task. All E2 and E5 full compliance is blocked until these are added. maya_logs cannot complete E2 until the package ships the interface.

### E1 — Missing Observer for ErrorCode model (FAILING)

No `app/Observers/` directory exists. `ErrorCode` model has no `#[ObservedBy]` attribute and no Observer registered in any ServiceProvider. Per `events.md`, `ErrorCode` CRUD must be audited via an `ErrorCodeObserver` calling `AuditPublisher::publish()` inside `DB::afterCommit()`. This is not blocked by E-PREREQ and can be implemented immediately. HIGH.

`ArchivedLog` is exempt: it is covered by domain Events (`LogWasArchived`, `ArchivedLogWasDeleted`).

### E2 — Domain Events do not implement AuditableEvent (FAILING — PREREQ-blocked)

All three domain Events implement `Dispatchable` only:

- `app/Events/LogWasArchived.php`
- `app/Events/ArchivedLogFieldsWereUpdated.php`
- `app/Events/ArchivedLogWasDeleted.php`

None implement `AuditableEvent`. Once the package adds the interface, all three must add `implements AuditableEvent`. This work is ready to do as soon as E-PREREQ is resolved.

### E3 — Services do not call AuditPublisher directly (PASSING)

`app/Services/ArchivedLogService.php` dispatches domain Events (`LogWasArchived::dispatch()`, etc.) and does not reference `AuditPublisher`. Correct pattern.

### E4 — Observer guards with wasChanged() (N/A)

No Observers exist, so this check is not applicable.

### E5 — Listeners do not duplicate package wildcard listener (PASSING — conditional)

Local Listeners (`RecordArchivedLogArchiveAudit`, `RecordArchivedLogUpdateAudit`, `RecordArchivedLogDeleteAudit`) call `$this->auditPublisher->publish()` directly and implement `ShouldHandleEventsAfterCommit`. This is correct given the package wildcard `RecordAuditableEvent` listener does not exist yet. Once the package listener is added, these local listeners must be removed and replaced by the package wildcard.

## Extraction candidates

### Cross-project patterns (strong extraction signal)

- `frontend/src/api/http.ts` — structurally identical to `maya_authorization/frontend/src/api/http.ts` and `maya_audit/frontend/src/api/http.ts` (confirmed in ≥4 projects). Candidate: extract a `createMayaApiClient(baseUrlEnv, defaultUrl)` factory into `@maya/shared-auth-react`. Each app becomes a 3-line wrapper.
- `frontend/src/components/layout/navItems.tsx` — structurally identical across `maya_logs`, `maya_authorization`, `maya_audit`, `maya_dashboard` (same imports from `@maya/shared-layout-react`, same `NavItem[]` memo). Candidate: parameterize as `useNavItemsBuilder(items)` in `@maya/shared-layout-react`.
- `fetchApplications` + `ApplicationRef` DTO — independently defined in `maya_logs`, `maya_authorization`, `maya_audit`. Candidate: ship `useApplications()` in `@maya/shared-auth-react` or a dedicated package.
- `frontend/src/components/filters/ApplicationSelect.tsx` and `ResolvedFilter.tsx` — bespoke styled selects matching the pattern of `@maya/shared-ui-react`'s `FilterField`. Candidate: extend shared component, remove local copies.

### Already extracted — consuming local copy (verify)

None confirmed; `SearchInput` wrapper is an acceptable adapter for i18n translation keys, not a true local duplicate.

### Local-only patterns (no extraction needed)

- `app/Services/LogIngestionService.php`, `ResilientLogPublisher.php` — log ingestion pipeline, specific to maya_logs
- `app/Http/Controllers/Api/LogController.php::stream()` — SSE streaming endpoint, maya_logs-specific
- `frontend/src/hooks/useLogStream.ts` — SSE polling hook, specific to this service
- `frontend/src/components/severity/*` (`SeverityBadge`, `palette.ts`) — log-domain specific
- `frontend/src/components/comments/CommentThread.tsx` — comments feature is exclusive to maya_logs
- All `Log*`, `ArchivedLog*`, `ErrorCode*` domain components — stay local

## Statistics

- Controllers audited: 9 (`LogController`, `ErrorCodeController`, `ApplicationController`, `CommentController`, `ErrorCodeCommentController`, `ArchivedLogCommentController`, `ArchivedLogController`, `HealthController`, `DashboardController`)
- Services audited: 6+ (`LogService`, `ErrorCodeService`, `ArchivedLogService`, `CommentService`, `LogIngestionService`, `ApplicationService`, `PanelUserService`)
- Repositories audited: 6
- DTOs found: 0 (directory absent)
- FormRequests found: ~10 (4 canonical in `app/Http/Requests/Api/`, plus duplicates under `app/Http/Requests/`)
- Frontend pages: 8
- Frontend components: ~15
- Frontend hooks: 2 (`useLogStream`, plus inline)
- F1 violation files: 8
- `any` in non-test code: 0
- `React.FC` usages: 0

## Notes

- **Laravel 12**: This project runs Laravel 12; the other Maya services are on Laravel 13. Not a violation but relevant for cross-project parity planning.
- **AppServiceProvider duplicates**: Almost certainly a merge artifact from the Comment feature addition. The PHP container silently overwrites, so `CommentRepository`/`CommentService` resolve correctly — but the missing `use` import for `CommentContentSanitizerInterface` is a latent runtime error. Fix immediately.
- **Stale FormRequests**: Two duplicate FormRequest files under `app/Http/Requests/` (non-canonical namespace) should be removed to avoid confusion.
- **F1 migration path**: Given 8 affected files, recommend a phased SWR migration: (1) install `swr`, (2) migrate page-level data fetches (`LogsPage`, `ErrorCodesPage`, `ArchivedLogsPage`), (3) detail pages, (4) widgets and CommentThread.
- **E1 is actionable now**: Creating `app/Observers/ErrorCodeObserver.php` with `AuditPublisher` calls inside `DB::afterCommit()` and registering it with `#[ObservedBy(ErrorCodeObserver::class)]` on the `ErrorCode` model is fully unblocked and should be done independently of E-PREREQ.
- **E5 future work**: Once `maya-shared-messaging-laravel` ships `RecordAuditableEvent`, maya_logs must (1) remove its three local Listeners, (2) remove their `EventServiceProvider` mappings, and (3) implement `AuditableEvent` on the three Events.
