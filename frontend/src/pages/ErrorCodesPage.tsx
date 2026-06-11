import { useMemo } from 'react';
import {
  Alert,
  Card,
  DataTable,
  FilterField,
  PageTitle,
  Pagination,
  Select,
  TextInput,
  useTablePreferences,
  type ColumnDef,
} from '@ceedcv-maya/shared-ui-react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { buildBackState, useServerTable } from '@ceedcv-maya/shared-hooks-react';
import { PermissionGate } from '../components/layout/PermissionGate';
import { useUserProfile } from '../features/user-profile';
import { LOGS_PERMISSIONS } from '../permissions';
import { fetchApplications, type ApplicationScope } from '../api/applications';
import { fetchErrorCodes, type ErrorCodesFilters as ApiErrorCodesFilters } from '../api/errorCodes';
import { createDataHook, type PaginatedResponse } from '@ceedcv-maya/shared-auth-react';
import type { ApplicationRef, ErrorCode } from '../types/logs';

interface ErrorCodesTableFilters {
  search: string;
  application_id: string;
}

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

export function ErrorCodesPage() {
  const { t } = useTranslation('errorCodes');
  const { t: tCommon } = useTranslation('common');
  const { hasPermission } = useUserProfile();
  const canCreate = hasPermission(LOGS_PERMISSIONS.errorCodeCreate);
  const navigate = useNavigate();
  const location = useLocation();
  const { hiddenIds, toggleHidden } = useTablePreferences({
    storageKey: 'maya:logs:error-codes-table',
  });

  const table = useServerTable<ErrorCodesTableFilters>({
    defaults: { search: '', application_id: '' },
    sortableColumns: ['code', 'application', 'name', 'file', 'line'],
    storageKey: 'maya:logs:error-codes',
  });

  const applicationsQuery = useApplicationsQuery('all');
  const applications = applicationsQuery.data ?? [];

  const apiFilters: ApiErrorCodesFilters = {
    search: table.filters.search || null,
    application_id: table.filters.application_id ? Number(table.filters.application_id) : null,
    page: table.page,
    per_page: table.pageSize,
    sort_by: table.sortBy?.columnId ?? null,
    sort_dir: table.sortBy?.direction ?? null,
  };

  const errorCodesQuery = useErrorCodesListQuery(apiFilters);

  const columns: ColumnDef<ErrorCode>[] = useMemo(
    () => [
      {
        id: 'code',
        header: t('columns.code'),
        sortable: true,
        cell: (ec) => <span className="font-mono whitespace-nowrap">{ec.code}</span>,
      },
      {
        id: 'application',
        header: t('filters.applicationLabel'),
        sortable: true,
        cell: (ec) => ec.application?.name ?? '-',
      },
      {
        id: 'name',
        header: t('tables.name'),
        sortable: true,
        cell: (ec) => <span className="break-words">{ec.name}</span>,
      },
      {
        id: 'file',
        header: t('columns.file'),
        sortable: true,
        cell: (ec) => (
          <span className="font-mono text-xs break-all">{ec.file ?? '-'}</span>
        ),
      },
      {
        id: 'line',
        header: t('columns.line'),
        sortable: true,
        cell: (ec) => <span className="whitespace-nowrap">{ec.line ?? '-'}</span>,
      },
    ],
    [t],
  );

  const pagination = errorCodesQuery.data;
  const errorCodes = pagination?.data ?? [];
  const meta = pagination ? { current_page: pagination.current_page, last_page: pagination.last_page, from: pagination.from, to: pagination.to, total: pagination.total } : null;
  const startIndex = meta && meta.from != null ? meta.from : 0;
  const endIndex = meta && meta.to != null ? meta.to : 0;
  const total = meta?.total ?? 0;
  const errorMessage = errorCodesQuery.error
    ? (errorCodesQuery.error instanceof Error ? errorCodesQuery.error.message : String(errorCodesQuery.error))
    : null;

  const filtersPanel = (
    <>
      <FilterField label={tCommon('filters.searchLabel')} htmlFor="error-codes-filter-search">
        <TextInput
          id="error-codes-filter-search"
          type="search"
          value={table.filters.search}
          placeholder={t('filters.searchPlaceholder')}
          onChange={(e) => table.setFilter('search', e.target.value)}
        />
      </FilterField>
      <FilterField label={tCommon('filters.applicationLabel')} htmlFor="error-codes-filter-application">
        <Select
          id="error-codes-filter-application"
          value={table.filters.application_id ?? ''}
          onChange={(e) => table.setFilter('application_id', e.target.value === '' ? undefined : e.target.value)}
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
    <PermissionGate permission={LOGS_PERMISSIONS.errorCodeIndex}>
      <div className="px-4 py-6 sm:px-6 lg:px-8">
        <PageTitle
          title={t('nav.dashboard')}
          actions={
            canCreate ? (
              <Link
                to="/error-codes/create"
                state={buildBackState(location)}
                className="inline-flex items-center bg-odoo-purple dark:bg-odoo-dark-purple text-text-inverse border-odoo-purple dark:border-odoo-dark-purple hover:bg-odoo-purple-d dark:hover:bg-odoo-dark-purple-d hover:border-odoo-purple-d dark:hover:border-odoo-dark-purple-d px-4 py-1.5 rounded-md text-sm font-semibold transition-colors cursor-pointer border shadow-sm"
              >
                {t('actions.new')}
              </Link>
            ) : undefined
          }
        />

        {errorCodesQuery.isError && errorMessage && (
          <Alert tone="danger" className="mt-4">
            {t('loadError')}: {errorMessage}
          </Alert>
        )}

        {errorCodesQuery.isLoading && !pagination && (
          <Card padding="lg" className="mt-4 text-center text-sm text-text-muted dark:text-text-dark-muted">
            {t('status.loading')}
          </Card>
        )}

        {pagination && (
          <>
            <div className="mt-3">
              <DataTable
                title={t('nav.dashboard')}
                columns={columns}
                rows={errorCodes}
                rowKey={(ec) => ec.id}
                loading={errorCodesQuery.isLoading || errorCodesQuery.isFetching}
                hiddenColumnIds={hiddenIds}
                onToggleHiddenColumn={toggleHidden}
                filtersStorageKey="maya:logs:error-codes-table"
                filtersPanel={filtersPanel}
                filtersActiveCount={table.filtersActiveCount}
                onClearFilters={table.resetFilters}
                filtersDefaultOpen={false}
                sortBy={table.sortBy}
                onSortChange={table.onSortChange}
                pageSize={table.pageSize}
                onPageSizeChange={table.onPageSizeChange}
                onRowClick={(ec) => navigate(`/error-codes/${ec.id}`, { state: buildBackState(location) })}
                emptyMessage={t('emptyFiltered')}
              />
            </div>
            {meta && (
              <div className="mt-4">
                <Pagination
                  currentPage={meta.current_page}
                  totalPages={meta.last_page}
                  onChange={table.onPageChange}
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
