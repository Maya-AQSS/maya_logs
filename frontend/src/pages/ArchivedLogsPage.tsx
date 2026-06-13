import { useCallback, useMemo } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { buildBackState } from '@ceedcv-maya/shared-hooks-react';
import {
  Alert,
  Card,
  DataTable,
  DatePicker,
  FilterField,
  MultiSelect,
  PageTitle,
  Pagination,
  Select,
  useTablePreferences,
  type ColumnDef,
  type SortState,
} from '@ceedcv-maya/shared-ui-react';
import { useTranslation } from 'react-i18next';
import { useLocale } from '@ceedcv-maya/shared-i18n-react';
import { useSearchParams } from 'react-router-dom';
import { fetchApplications, type ApplicationScope } from '../api/applications';
import {
  fetchArchivedLogs,
  type ArchivedLogsFilters as ApiArchivedLogsFilters,
  type ArchivedLogsSortBy,
} from '../api/archivedLogs';
import type { ArchivedLogsFiltersState } from '../components/archived-logs';
import { PermissionGate } from '../components/layout/PermissionGate';
import { SeverityBadge, severityLabel } from '../components/severity';
import { useUserProfile } from '../features/user-profile';
import { LOGS_PERMISSIONS } from '../permissions';
import { createDataHook, type PaginatedResponse, type SortDir } from '@ceedcv-maya/shared-auth-react';
import type { ApplicationRef, ArchivedLog } from '../types/logs';
import { LOG_SEVERITY_KEYS } from '../types/logs';
import { formatDateTime } from '@ceedcv-maya/shared-ui-react';

const useApplicationsQuery = createDataHook<ApplicationScope, ApplicationRef[]>({
  queryKey: (scope) => ['applications', scope],
  fetcher: (scope) => fetchApplications(scope),
  defaultOptions: { staleTime: 60_000 },
});

const useArchivedLogsListQuery = createDataHook<ApiArchivedLogsFilters, PaginatedResponse<ArchivedLog>>({
  queryKey: (filters) => ['archived-logs', filters],
  fetcher: (filters) => fetchArchivedLogs(filters),
  defaultOptions: {
    placeholderData: (prev) => prev,
    staleTime: 0,
  },
});

export type ArchivedLogsSortKey =
  | 'application'
  | 'severity'
  | 'archived_at'
  | 'original_created_at';

const VALID_SORT_COLUMNS: readonly ArchivedLogsSortKey[] = [
  'application',
  'severity',
  'archived_at',
  'original_created_at',
];
const VALID_SORT_DIRS: readonly SortDir[] = ['asc', 'desc'];

function truncate(text: string | null | undefined, max = 120): string {
  if (!text) return '-';
  if (text.length <= max) return text;
  return `${text.slice(0, max)}…`;
}

function parseFiltersFromUrl(params: URLSearchParams): {
  filters: ArchivedLogsFiltersState;
  sortBy: ArchivedLogsSortKey | null;
  sortDir: SortDir | null;
  page: number;
} {
  const severityRaw = params.get('severity');
  const severity = severityRaw
    ? severityRaw
        .split(',')
        .map((s) => s.trim())
        .filter((s) => (LOG_SEVERITY_KEYS as readonly string[]).includes(s))
    : [];

  const applicationIdRaw = params.get('application_id');
  const applicationId = applicationIdRaw ? Number(applicationIdRaw) : null;

  const sortByRaw = params.get('sort_by');
  const sortBy = (VALID_SORT_COLUMNS as readonly string[]).includes(sortByRaw ?? '')
    ? (sortByRaw as ArchivedLogsSortKey)
    : null;

  const sortDirRaw = params.get('sort_dir');
  const sortDir = (VALID_SORT_DIRS as readonly string[]).includes(sortDirRaw ?? '')
    ? (sortDirRaw as SortDir)
    : null;

  const pageRaw = params.get('page');
  const pageNum = pageRaw ? Number(pageRaw) : 1;
  const page = Number.isFinite(pageNum) && pageNum > 0 ? Math.floor(pageNum) : 1;

  return {
    filters: {
      severity,
      applicationId: applicationId != null && !Number.isNaN(applicationId) ? applicationId : null,
      dateFrom: params.get('date_from'),
      dateTo: params.get('date_to'),
    },
    sortBy,
    sortDir,
    page,
  };
}

function writeFiltersToUrl(
  filters: ArchivedLogsFiltersState,
  sortBy: ArchivedLogsSortKey | null,
  sortDir: SortDir | null,
  page: number,
): URLSearchParams {
  const qs = new URLSearchParams();
  if (filters.severity.length > 0) qs.set('severity', filters.severity.join(','));
  if (filters.applicationId != null) qs.set('application_id', String(filters.applicationId));
  if (filters.dateFrom) qs.set('date_from', filters.dateFrom);
  if (filters.dateTo) qs.set('date_to', filters.dateTo);
  if (sortBy) qs.set('sort_by', sortBy);
  if (sortDir) qs.set('sort_dir', sortDir);
  if (page > 1) qs.set('page', String(page));
  return qs;
}

function toApiFilters(
  filters: ArchivedLogsFiltersState,
  sortBy: ArchivedLogsSortKey | null,
  sortDir: SortDir | null,
  page: number,
  perPage?: number,
): ApiArchivedLogsFilters {
  return {
    severity: filters.severity.length > 0 ? filters.severity : null,
    application_id: filters.applicationId ?? null,
    date_from: filters.dateFrom,
    date_to: filters.dateTo,
    sort_by: sortBy ? (sortBy as ArchivedLogsSortBy) : null,
    sort_dir: sortDir,
    page,
    per_page: perPage ?? null,
  };
}

function countActiveFilters(f: ArchivedLogsFiltersState): number {
  let n = 0;
  if (f.severity.length > 0) n += 1;
  if (f.applicationId != null) n += 1;
  if (f.dateFrom) n += 1;
  if (f.dateTo) n += 1;
  return n;
}

export function ArchivedLogsPage() {
  const { t } = useTranslation('archivedLogs');
  const { t: tCommon } = useTranslation('common');
  const { dateLocale } = useLocale();
  const { hasPermission } = useUserProfile();
  const navigate = useNavigate();
  const location = useLocation();
  const canIndex = hasPermission(LOGS_PERMISSIONS.archivedLogsIndex);
  const canShow = hasPermission(LOGS_PERMISSIONS.archivedLogsShow);
  const [searchParams, setSearchParams] = useSearchParams();
  const { hiddenIds, toggleHidden, pageSize, setPageSize } = useTablePreferences({
    storageKey: 'maya:logs:archived-logs-table',
  });

  const { filters, sortBy, sortDir, page } = useMemo(
    () => parseFiltersFromUrl(searchParams),
    [searchParams],
  );

  const applicationsQuery = useApplicationsQuery('with_archived_logs');
  const applications = applicationsQuery.data ?? [];

  const archivedLogsQuery = useArchivedLogsListQuery(
    toApiFilters(filters, sortBy, sortDir, page, pageSize),
    { enabled: canIndex },
  );

  const updateFilters = useCallback(
    (patch: Partial<ArchivedLogsFiltersState>) => {
      const next = { ...filters, ...patch };
      setSearchParams(writeFiltersToUrl(next, sortBy, sortDir, 1));
    },
    [filters, sortBy, sortDir, setSearchParams],
  );

  const resetFilters = useCallback(() => {
    const emptyFilters: ArchivedLogsFiltersState = {
      severity: [],
      applicationId: null,
      dateFrom: null,
      dateTo: null,
    };
    setSearchParams(writeFiltersToUrl(emptyFilters, sortBy, sortDir, 1));
  }, [setSearchParams, sortBy, sortDir]);

  const changePage = useCallback(
    (nextPage: number) => {
      setSearchParams(writeFiltersToUrl(filters, sortBy, sortDir, nextPage));
    },
    [filters, sortBy, sortDir, setSearchParams],
  );

  const onSortChange = useCallback(
    (next: SortState) => {
      const column = next.columnId as ArchivedLogsSortKey;
      if (!(VALID_SORT_COLUMNS as readonly string[]).includes(column)) return;
      setSearchParams(writeFiltersToUrl(filters, column, next.direction, 1));
    },
    [filters, setSearchParams],
  );

  const sortState: SortState | null = useMemo(
    () => (sortBy && sortDir ? { columnId: sortBy, direction: sortDir } : null),
    [sortBy, sortDir],
  );

  const columns: ColumnDef<ArchivedLog>[] = useMemo(
    () => [
      {
        id: 'application',
        header: t('filters.applicationLabel'),
        cell: (l) => l.application?.name ?? '-',
        sortable: true,
      },
      {
        id: 'severity',
        header: t('filters.severityLabel'),
        cell: (l) => <SeverityBadge severity={l.severity} />,
        sortable: true,
      },
      {
        id: 'message',
        header: t('columns.message'),
        cell: (l) => (
          <span className="block break-words max-w-md">{truncate(l.message, 120)}</span>
        ),
      },
      {
        id: 'archived_at',
        header: t('states.archived'),
        cell: (l) => formatDateTime(l.archived_at, dateLocale),
        sortable: true,
      },
      {
        id: 'original_created_at',
        header: t('columns.originalCreatedAt'),
        cell: (l) => formatDateTime(l.original_created_at, dateLocale),
        sortable: true,
      },
    ],
    [t, dateLocale],
  );

  const pagination = archivedLogsQuery.data;
  const logs = pagination?.data ?? [];
  const meta = pagination ? { current_page: pagination.current_page, last_page: pagination.last_page, from: pagination.from, to: pagination.to, total: pagination.total } : null;
  const startIndex = meta && meta.from != null ? meta.from : 0;
  const endIndex = meta && meta.to != null ? meta.to : 0;
  const total = meta?.total ?? 0;
  const activeCount = countActiveFilters(filters);
  const errorMessage = archivedLogsQuery.error
    ? archivedLogsQuery.error instanceof Error
      ? archivedLogsQuery.error.message
      : String(archivedLogsQuery.error)
    : null;

  const filtersPanel = (
    <>
      <FilterField label={tCommon('filters.dateFrom')}>
        <DatePicker
          value={filters.dateFrom}
          onChange={(d) => updateFilters({ dateFrom: d })}
          placeholder={tCommon('filters.dateFrom')}
          ariaLabel={tCommon('filters.dateFrom')}
          max={filters.dateTo ?? undefined}
        />
      </FilterField>
      <FilterField label={tCommon('filters.dateTo')}>
        <DatePicker
          value={filters.dateTo}
          onChange={(d) => updateFilters({ dateTo: d })}
          placeholder={tCommon('filters.dateTo')}
          ariaLabel={tCommon('filters.dateTo')}
          min={filters.dateFrom ?? undefined}
        />
      </FilterField>
      <FilterField label={tCommon('filters.applicationLabel')} htmlFor="archived-logs-filter-application">
        <Select
          id="archived-logs-filter-application"
          value={filters.applicationId ?? ''}
          onChange={(e) => {
            const v = e.target.value;
            updateFilters({ applicationId: v === '' ? null : Number(v) });
          }}
        >
          <option value="">{t('filters.applicationAll')}</option>
          {applications.map((app) => (
            <option key={app.id} value={app.id}>
              {app.name}
            </option>
          ))}
        </Select>
      </FilterField>
      <FilterField label={tCommon('filters.severityLabel')}>
        <MultiSelect
          options={LOG_SEVERITY_KEYS.map((key) => ({ value: key, label: severityLabel(key) }))}
          value={filters.severity}
          onChange={(next) => updateFilters({ severity: next })}
          placeholder={tCommon('severity.all')}
          ariaLabel={tCommon('filters.severityLabel')}
        />
      </FilterField>
    </>
  );

  return (
    <PermissionGate permission={LOGS_PERMISSIONS.archivedLogsIndex}>
    <div className="px-4 py-6 sm:px-6 lg:px-8">
      <PageTitle title={t('nav.dashboard')} />

      {archivedLogsQuery.isError && errorMessage && (
        <Alert tone="danger" className="mt-4">{t('listLoadError', { message: errorMessage })}
        </Alert>
      )}

      {archivedLogsQuery.isLoading && !pagination && (
        <Card padding="lg" className="mt-4 text-center text-sm text-text-muted dark:text-text-dark-muted">
          {t('status.loading')}
        </Card>
      )}

      {pagination && (
        <>
          <div className="mt-3">
            <DataTable
              title={t('table.title', { defaultValue: 'Logs archivados' })}
              columns={columns}
              rows={logs}
              rowKey={(l) => l.id}
              loading={archivedLogsQuery.isLoading || archivedLogsQuery.isFetching}
              hiddenColumnIds={hiddenIds}
              onToggleHiddenColumn={toggleHidden}
              filtersStorageKey="maya:logs:archived-logs-table"
              filtersPanel={filtersPanel}
              filtersActiveCount={activeCount}
              onClearFilters={resetFilters}
              filtersDefaultOpen={false}
              sortBy={sortState}
              onSortChange={onSortChange}
              pageSize={pageSize}
              onPageSizeChange={(size) => {
                setPageSize(size)
                setSearchParams(writeFiltersToUrl(filters, sortBy, sortDir, 1))
              }}
              onRowClick={
                canShow
                  ? (l) => navigate(`/archived-logs/${l.id}`, { state: buildBackState(location) })
                  : undefined
              }
              emptyMessage={t('columns.emptyText')}
            />
          </div>
          {meta && (
            <div className="mt-4">
              <Pagination
                currentPage={meta.current_page}
                totalPages={meta.last_page}
                onChange={changePage}
                ariaLabel={tCommon('pagination.ariaLabel')}
                prevLabel={tCommon('pagination.previous')}
                nextLabel={tCommon('pagination.next')}
                info={tCommon('pagination.rangeOf', { from: startIndex, to: endIndex, total })}
              />
            </div>
          )}
        </>
      )}
    </div>
    </PermissionGate>
  );
}
