# Auditoría i18n — maya_logs (frontend)

## Resumen
- Archivos revisados: 65
- Archivos con strings sin traducir: 0
- Total de hallazgos: 0
- Paridad de locales (es/en/va): OK — los 7 namespaces (archivedLogs, auth, comments, dashboard, errorCodes, logs, nav) tienen exactamente las mismas claves en los tres idiomas (sin claves faltantes en ningún locale).
- Severidad global: none

## Hallazgos por archivo
No se han detectado strings de cara al usuario sin internacionalizar. Todos los textos visibles se resuelven mediante `useTranslation()` / `t()` / `tCommon()` o llegan como datos/props ya traducidos.

## Archivos revisados sin incidencias

### Páginas (`src/pages/`)
- src/pages/ArchivedLogDetailPage.tsx — usa `t()`; el único atributo literal es `placeholder="https://…"` (ejemplo técnico de URL, no traducible).
- src/pages/ArchivedLogsPage.tsx — filtros con `tCommon('filters.*')`, `tCommon('severity.all')`, labels vía `severityLabel()`.
- src/pages/DashboardPage.tsx — re-export puro de la página feature-based (sin texto).
- src/pages/ErrorCodeCreatePage.tsx
- src/pages/ErrorCodeDetailPage.tsx
- src/pages/ErrorCodesPage.tsx
- src/pages/LogDetailPage.tsx
- src/pages/LogsPage.tsx — placeholders y options vía `tCommon`/`severityLabel`.
- src/pages/index.ts — solo re-exports.

### Componentes (`src/components/`)
- src/components/archived-logs/ArchivedLogDetailView.tsx
- src/components/archived-logs/ArchivedLogsFilters.tsx — `tCommon('filters.dateFrom'|'dateTo')`.
- src/components/archived-logs/index.ts
- src/components/comments/CommentThread.tsx — todo vía `t()`; los literales son únicamente `className` (Tailwind).
- src/components/comments/index.ts
- src/components/dashboard/SeverityCard.tsx — labels/title llegan por props (`unresolvedLabel`, `resolvedLabel`, `title`).
- src/components/dashboard/index.ts
- src/components/error-codes/ErrorCodeForm.tsx
- src/components/error-codes/ErrorCodesFilters.tsx
- src/components/error-codes/index.ts
- src/components/filters/ApplicationSelect.tsx
- src/components/filters/fieldStyles.ts — solo clases CSS.
- src/components/filters/FilterSelect.tsx
- src/components/filters/index.ts
- src/components/filters/ResolvedFilter.tsx — labels de options vía `t()`.
- src/components/filters/SearchInput.tsx
- src/components/filters/SeverityFilterCheckboxes.tsx
- src/components/layout/index.ts
- src/components/layout/navItems.tsx — todos los labels vía `t('nav.*'|'logs'|...)`.
- src/components/layout/PermissionGate.tsx
- src/components/logs/LogDetailView.tsx — `Field({label})` recibe label traducido por el llamador.
- src/components/logs/LogsFilters.tsx
- src/components/logs/index.ts
- src/components/severity/SeverityBadge.tsx — etiqueta derivada del dato (`severity.toUpperCase()`), no texto de UI.
- src/components/severity/palette.ts — solo clases CSS.
- src/components/severity/index.ts

### Features (`src/features/`)
- src/features/dashboard/index.ts
- src/features/dashboard/pages/DashboardPage.tsx
- src/features/dashboard/widgets/ApplicationTotalsWidget.tsx
- src/features/dashboard/widgets/errorCount.ts — normalización de datos, sin UI.
- src/features/dashboard/widgets/ErrorCountWidget.tsx
- src/features/dashboard/widgets/RecentLogsWidget.tsx
- src/features/dashboard/widgets/registry.ts
- src/features/dashboard/widgets/SeverityCardsWidget.tsx — `title={severityLabel(card.key)}` (traducido).
- src/features/user-profile/index.ts

### Hooks (`src/hooks/`)
- src/hooks/useLogStream.ts — `setError(message)` propaga el mensaje del stream/red, no literal de UI.
- src/hooks/index.ts

### API (`src/api/`) — clientes, solo constantes técnicas y tipos
- src/api/applications.ts
- src/api/archivedLogs.ts
- src/api/auth.ts
- src/api/comments.ts
- src/api/dashboard.ts
- src/api/errorCodes.ts
- src/api/http.ts
- src/api/logs.ts

### Auth, schemas, types, raíz
- src/auth/oidcAdapter.ts
- src/schemas/archivedLog.ts
- src/schemas/errorCode.ts
- src/types/dashboard.ts
- src/types/logs.ts
- src/types/users.ts
- src/App.tsx
- src/main.tsx
- src/permissions.ts

## Gaps de paridad de locales
Ninguno. Verificación por comparación de conjuntos de claves (no solo conteo) en los 7 namespaces:

| Namespace | es | en | va | Estado |
|-----------|----|----|----|--------|
| archivedLogs | 30 | 30 | 30 | OK |
| auth | 2 | 2 | 2 | OK |
| comments | 14 | 14 | 14 | OK |
| dashboard | 13 | 13 | 13 | OK |
| errorCodes | 26 | 26 | 26 | OK |
| logs | 33 | 33 | 33 | OK |
| nav | 3 | 3 | 3 | OK |

No hay claves presentes en un locale y ausentes en otro.

## Recomendaciones
1. No se requiere acción correctiva: el frontend de maya_logs está completamente internacionalizado y con paridad total es/en/va.
2. Mantener la disciplina actual: textos siempre vía `t()`/`tCommon()`, nunca literales en JSX o atributos visibles.
3. Considerar un test/lint de paridad de locales en CI (p. ej. un script que compare los conjuntos de claves de los 7 namespaces) para prevenir regresiones de paridad en futuros cambios.
4. (Opcional, fuera de alcance estricto) Si se quiere blindar contra strings sueltos, añadir la regla `i18next/no-literal-string` de eslint-plugin-i18next o equivalente en Biome para captura en tiempo de desarrollo.
