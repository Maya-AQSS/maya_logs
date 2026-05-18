import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { createDataHook } from '@maya/shared-auth-react';
import { fetchLogs } from '../../../api/logs';
import { useLogStream } from '../../../hooks';

interface ErrorCountArgs {
  since: string;
  streamMark: number;
}

const useErrorCount = createDataHook<ErrorCountArgs, number>({
  // streamMark is part of the key so the query re-runs on each SSE tick.
  queryKey: ({ since, streamMark }) => ['logs', 'error-count', { since, streamMark }],
  fetcher: async ({ since }) => {
    const res = await fetchLogs({
      severity: ['critical', 'high'],
      archived: 'without',
      date_from: since,
      per_page: 1,
    });
    return res.meta?.total ?? res.data?.length ?? 0;
  },
  defaultOptions: { staleTime: 5_000 },
});

/**
 * StatCard widget — count of CRITICAL+HIGH (treated as "errors") logs in the
 * last 24h. Re-fetched whenever a new SSE log payload arrives.
 */
function ErrorCountWidget() {
  const { t } = useTranslation('dashboard');
  const { payload: streamPayload } = useLogStream({ intervalMs: 5000 });

  // Stable per-tick: a marker derived from the latest SSE item so the query
  // refetches when a new log arrives. Hashing the top item id is enough.
  const streamMark =
    streamPayload && streamPayload.length > 0 ? Number(streamPayload[0]?.id ?? 0) : 0;
  const since = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString();

  const { data, isLoading, error } = useErrorCount({ since, streamMark });

  if (isLoading) {
    return (
      <div className="h-full flex items-center justify-center">
        <div className="h-12 w-24 bg-ui-border-l dark:bg-ui-dark-border rounded-lg animate-pulse" />
      </div>
    );
  }

  if (error) {
    return (
      <p className="text-sm text-danger-dark dark:text-danger text-center py-4">
        {t('error')}
      </p>
    );
  }

  return (
    <Link
      to="/logs?severity=critical,high"
      className="block h-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-odoo-purple rounded-lg"
      aria-label={t('widgets.errorCount.label')}
    >
      <div className="h-full flex flex-col items-center justify-center text-center px-2">
        <span
          className="text-5xl sm:text-6xl font-extrabold leading-none bg-clip-text text-transparent bg-gradient-to-br from-danger to-warning-dark"
        >
          {data ?? 0}
        </span>
        <span className="mt-2 text-xs uppercase tracking-wide font-medium text-text-secondary dark:text-text-dark-secondary">
          {t('widgets.errorCount.label')}
        </span>
      </div>
    </Link>
  );
}

export default ErrorCountWidget;
