import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { fetchDashboard } from '../../../api/dashboard';
import { ApplicationTile } from '@maya/shared-ui-react';

function hrefForApplication(id: number): string {
  return `/logs?application_id=${id}`;
}

/** Per-application totals widget — uses shared React Query cache to avoid duplicate fetches. */
function ApplicationTotalsWidget() {
  const { t } = useTranslation('dashboard');
  const { data, status } = useQuery({
    queryKey: ['logs', 'dashboard'],
    queryFn: fetchDashboard,
    refetchInterval: 30_000,
  });

  if (status === 'pending') {
    return (
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        {[1, 2].map((n) => (
          <div
            key={n}
            className="h-16 bg-ui-border-l dark:bg-ui-dark-border rounded-lg animate-pulse"
          />
        ))}
      </div>
    );
  }

  if (status === 'error' || !data) {
    return (
      <p className="text-sm text-danger-dark dark:text-danger text-center py-4">
        {t('error')}
      </p>
    );
  }

  if (data.application_totals.length === 0) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary text-center py-4">
        {t('widgets.applicationTotals.empty')}
      </p>
    );
  }

  return (
    <ul className="grid grid-cols-1 sm:grid-cols-2 gap-3" role="list">
      {data.application_totals.map((row) => (
        <li key={row.application_id}>
          <ApplicationTile
            href={hrefForApplication(row.application_id)}
            name={row.name}
            total={row.total}
            totalLabel={t('logsTotalLabel')}
            ariaLabel={t('openFilteredLogs', { app: row.name })}
          />
        </li>
      ))}
    </ul>
  );
}

export default ApplicationTotalsWidget;
