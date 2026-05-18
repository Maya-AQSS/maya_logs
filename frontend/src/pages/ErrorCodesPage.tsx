import { useCallback, useMemo } from 'react';
import {
  Alert,
  DataTable,
  FilterField,
  PageTitle,
  Pagination,
  Select,
  TextInput,
  useTablePreferences,
  type ColumnDef,
} from '@maya/shared-ui-react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { fetchApplications, type ApplicationScope } from '../api/applications';
import { fetchErrorCodes, type ErrorCodesFilters as ApiErrorCodesFilters } from '../api/errorCodes';
import type { ErrorCodesFiltersState } from '../components/error-codes';
import { createDataHook, type PaginatedResponse } from '@maya/shared-auth-react';
import type { ApplicationRef, ErrorCode } from '../types/logs';

const useApplicationsQuery = createDataHook<ApplicationScope, ApplicationRef[]>({
  queryKey: (scope) => ['applications', scope],
  fetcher: (scope) => fetchApplications(scope),
  defaultOptions: { staleTime: 60_000 },
});

const useErrorCodesListQuery = createDataHook<ApiErrorCodesFilters, PaginatedResponse<ErrorCode>>({
  queryKey: (filters) => ['error-codes', filters],
  fetcher: (filters) => fetchErrorCodes(filters),
  defaultOptions: {
    placeholderData: (prev) => prev,
    staleTime: 0,
  },
});

function parseFiltersFromUrl(params: URLSearchParams): {
  filters: ErrorCodesFiltersState;
  page: number;
} {
  const search = params.get('search') ?? '';

  const applicationIdRaw = params.get('application_id');
  const applicationIdNum = applicationIdRaw ? Number(applicationIdRaw) : null;
  const applicationId =
    applicationIdNum != null && Number.isFinite(applicationIdNum) ? applicationIdNum : null;

  const pageRaw = params.get('page');
  const pageNum = pageRaw ? Number(pageRaw) : 1;
  const page = Number.isFinite(pageNum) && pageNum > 0 ? Math.floor(pageNum) : 1;

  return {
    filters: { search, applicationId },
    page,
  };
}

function writeFiltersToUrl(filters: ErrorCodesFiltersState, page: number): URLSearchParams {
  const qs = new URLSearchParams();
  if (filters.search.trim() !== '') qs.set('search', filters.search);
  if (filters.applicationId != null) qs.set('application_id', String(filters.applicationId));
  if (page > 1) qs.set('page', String(page));
  return qs;
}

function toApiFilters(filters: ErrorCodesFiltersState, page: number, perPage?: number): ApiErrorCodesFilters {
  return {
    search: filters.search.trim() === '' ? null : filters.search,
    application_id: filters.applicationId ?? null,
    page,
    per_page: perPage ?? null,
  };
}

function countActiveFilters(f: ErrorCodesFiltersState): number {
  let n = 0;
  if (f.search.trim() !== '') n += 1;
  if (f.applicationId != null) n += 1;
  return n;
}

export function ErrorCodesPage() {
  const { t } = useTranslation('errorCodes');
  const { t: tCommon } = useTranslation('common');
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { hiddenIds, toggleHidden, pageSize, setPageSize } = useTablePreferences({
    storageKey: 'maya:logs:error-codes-table',
  });

  const { filters, page } = useMemo(() => parseFiltersFromUrl(searchParams), [searchParams]);

  const applicationsQuery = useApplicationsQuery('all');
  const applications = applicationsQuery.data ?? [];

  const errorCodesQuery = useErrorCodesListQuery(toApiFilters(filters, page, pageSize));

  const updateFilters = useCallback(
    (patch: Partial<ErrorCodesFiltersState>) => {
      const next = { ...filters, ...patch };
      setSearchParams(writeFiltersToUrl(next, 1));
    },
    [filters, setSearchParams],
  );

  const resetFilters = useCallback(() => {
    const emptyFilters: ErrorCodesFiltersState = {
      search: '',
      applicationId: null,
    };
    setSearchParams(writeFiltersToUrl(emptyFilters, 1));
  }, [setSearchParams]);

  const changePage = useCallback(
    (nextPage: number) => {
      setSearchParams(writeFiltersToUrl(filters, nextPage));
    },
    [filters, setSearchParams],
  );

  const columns: ColumnDef<ErrorCode>[] = useMemo(
    () => [
      {
        id: 'code',
        header: t('columns.code'),
        cell: (ec) => <span className="font-mono whitespace-nowrap">{ec.code}</span>,
      },
      {
        id: 'application',
        header: t('columns.application'),
        cell: (ec) => ec.application?.name ?? '-',
      },
      {
        id: 'name',
        header: t('columns.name'),
        cell: (ec) => <span className="break-words">{ec.name}</span>,
      },
      {
        id: 'file',
        header: t('columns.file'),
        cell: (ec) => (
          <span className="font-mono text-xs break-all">{ec.file ?? '-'}</span>
        ),
      },
      {
        id: 'line',
        header: t('columns.line'),
        cell: (ec) => <span className="whitespace-nowrap">{ec.line ?? '-'}</span>,
      },
    ],
    [t],
  );

  const pagination = errorCodesQuery.data;
  const errorCodes = pagination?.data ?? [];
  const meta = pagination?.meta;
  const startIndex = meta && meta.from != null ? meta.from : 0;
  const endIndex = meta && meta.to != null ? meta.to : 0;
  const total = meta?.total ?? 0;
  const activeCount = countActiveFilters(filters);
  const errorMessage = errorCodesQuery.error
    ? (errorCodesQuery.error instanceof Error ? errorCodesQuery.error.message : String(errorCodesQuery.error))
    : null;

  const filtersPanel = (
    <>
      <FilterField label={tCommon('filters.searchLabel')} htmlFor="error-codes-filter-search">
        <TextInput
          id="error-codes-filter-search"
          type="search"
          value={filters.search}
          placeholder={t('filters.searchPlaceholder')}
          onChange={(e) => updateFilters({ search: e.target.value })}
        />
      </FilterField>
      <FilterField label={tCommon('filters.applicationLabel')} htmlFor="error-codes-filter-application">
        <Select
          id="error-codes-filter-application"
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
    </>
  );

  return (
    <div className="px-4 py-6 sm:px-6 lg:px-8">
      <PageTitle
        title={t('title')}
        actions={
          <Link
            to="/error-codes/create"
            className="inline-flex items-center bg-odoo-purple dark:bg-odoo-dark-purple text-text-inverse border-odoo-purple dark:border-odoo-dark-purple hover:bg-odoo-purple-d dark:hover:bg-odoo-dark-purple-d hover:border-odoo-purple-d dark:hover:border-odoo-dark-purple-d px-4 py-1.5 rounded-md text-sm font-semibold transition-colors cursor-pointer border shadow-sm"
          >
            {t('actions.new')}
          </Link>
        }
      />

      {errorCodesQuery.isError && errorMessage && (
        <Alert tone="danger" className="mt-4">{t('loadError')}: {errorMessage}
        </Alert>
      )}

      {errorCodesQuery.isLoading && !pagination && (
        <div className="mt-4 rounded-lg border border-ui-border bg-ui-card p-6 text-center text-sm text-text-muted dark:border-ui-dark-border dark:bg-ui-dark-card dark:text-text-dark-muted">
          {t('loading')}
        </div>
      )}

      {pagination && (
        <>
          <div className="mt-3">
            <DataTable
              title={t('title')}
              columns={columns}
              rows={errorCodes}
              rowKey={(ec) => ec.id}
              loading={errorCodesQuery.isLoading || errorCodesQuery.isFetching}
              hiddenColumnIds={hiddenIds}
              onToggleHiddenColumn={toggleHidden}
              filtersStorageKey="maya:logs:error-codes-table"
              filtersPanel={filtersPanel}
              filtersActiveCount={activeCount}
              onClearFilters={resetFilters}
              filtersDefaultOpen={false}
              pageSize={pageSize}
              onPageSizeChange={(size) => {
                setPageSize(size)
                setSearchParams(writeFiltersToUrl(filters, 1))
              }}
              onRowClick={(ec) => navigate(`/error-codes/${ec.id}`)}
              emptyMessage={t('emptyFiltered')}
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
  );
}
