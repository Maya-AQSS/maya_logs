import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useMemo } from 'react';
import { createDataHook } from '@maya/shared-auth-react';
import { fetchLogs } from '../../../api/logs';
import { useLogStream } from '../../../hooks';
import { resolveUniqueErrorCount } from './errorCount';

interface ErrorCountArgs {
  since: string;
  streamMark: number;
}

const useErrorCount = createDataHook<ErrorCountArgs, number>({
  // streamMark is part of the key so the query re-runs on each SSE tick.
  queryKey: ({ since, streamMark }) => ['logs', 'error-count', { since, streamMark }],
  fetcher: async ({ since }) => {
    const perPage = 100;
    const maxPages = 50;
    const logs = [];

    let page = 1;
    while (page <= maxPages) {
      const res = await fetchLogs({
        archived: 'without',
        date_from: since,
        per_page: perPage,
        page,
      });

      logs.push(...res.data);

      if (page >= res.last_page) {
        break;
      }

      page += 1;
    }

    return resolveUniqueErrorCount(logs);
  },
  defaultOptions: { staleTime: 5_000 },
});

/**
 * StatCard widget — unique errors in last 24h.
 * Multiple logs with the same error signature are counted once.
 */
function ErrorCountWidget() {
  const { t } = useTranslation('dashboard');
  const { payload: streamPayload } = useLogStream({ intervalMs: 5000 });

  // Stable per-tick: a marker derived from the latest SSE item so the query
  // refetches when a new log arrives. Hashing the top item id is enough.
  const streamMark =
    streamPayload && streamPayload.length > 0 ? Number(streamPayload[0]?.id ?? 0) : 0;
  // Recompute at most once per minute to avoid query-key churn on every render.
  const minuteBucket = Math.floor(Date.now() / 60_000);
  const since = useMemo(
    () => new Date(minuteBucket * 60_000 - 24 * 60 * 60 * 1000).toISOString(),
    [minuteBucket],
  );

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
        {t('dashboard.loadError')}
      </p>
    );
  }

  return (
    <Link
      to="/logs"
      className="block h-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-odoo-purple rounded-lg"
      aria-label={t('widgets.errorCount.title')}
    >
      <div className="h-full flex flex-col items-center justify-center text-center px-2">
        <span
          className="text-5xl sm:text-6xl font-extrabold leading-none text-gradient-danger"
        >
          {data ?? 0}
        </span>
        <span className="mt-2 text-xs uppercase tracking-wider font-semibold text-text-secondary dark:text-text-dark-secondary">
          {t('widgets.errorCount.subtitle')}
        </span>
      </div>
    </Link>
  );
}

export default ErrorCountWidget;
