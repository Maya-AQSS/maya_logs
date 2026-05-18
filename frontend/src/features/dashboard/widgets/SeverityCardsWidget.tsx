import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { fetchDashboard } from '../../../api/dashboard';
import { SeverityCard } from '../../../components/dashboard';
import { severityLabel } from '../../../components/severity';

function hrefForSeverity(key: string): string {
  if (key === 'all') return '/logs';
  return `/logs?severity=${encodeURIComponent(key)}`;
}

/** Severity grid widget — uses shared React Query cache to avoid duplicate fetches. */
function SeverityCardsWidget() {
  const { t } = useTranslation('dashboard');
  const { data, status } = useQuery({
    queryKey: ['logs', 'dashboard'],
    queryFn: fetchDashboard,
    refetchInterval: 30_000,
  });

  if (status === 'pending') {
    return (
      <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
        {[1, 2, 3].map((n) => (
          <div
            key={n}
            className="h-20 bg-ui-border-l dark:bg-ui-dark-border rounded-lg animate-pulse"
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

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
      {data.severity_cards.map((card) => (
        <SeverityCard
          key={card.key}
          title={severityLabel(card.key)}
          href={hrefForSeverity(card.key)}
          severityKey={card.key}
          unresolvedCount={card.unresolvedCount}
          resolvedCount={card.resolvedCount}
          unresolvedLabel={t('unresolvedLabel')}
          resolvedLabel={t('resolvedLabel')}
        />
      ))}
    </div>
  );
}

export default SeverityCardsWidget;
