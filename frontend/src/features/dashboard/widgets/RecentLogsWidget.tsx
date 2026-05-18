import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { createDataHook, type PaginatedResponse } from '@maya/shared-auth-react';
import { fetchLogs } from '../../../api/logs';
import { SeverityBadge } from '../../../components/severity';
import type { Log } from '../../../types/logs';
import { useLogStream } from '../../../hooks';

const PAGE_SIZE = 5;

const RECENT_LOGS_KEY = ['recent-logs', PAGE_SIZE] as const;

const useRecentLogsQuery = createDataHook<void, PaginatedResponse<Log>>({
  queryKey: () => RECENT_LOGS_KEY,
  fetcher: () =>
    fetchLogs({
      per_page: PAGE_SIZE,
      archived: 'without',
      sort_by: 'created_at',
      sort_dir: 'desc',
    }),
  defaultOptions: { staleTime: 0 },
});

function formatRelative(iso: string | null, locale: string): string {
  if (!iso) return '—';
  const ts = new Date(iso).getTime();
  if (Number.isNaN(ts)) return '—';
  const diffSec = Math.round((ts - Date.now()) / 1000);
  const abs = Math.abs(diffSec);
  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
  if (abs < 60) return rtf.format(diffSec, 'second');
  if (abs < 3600) return rtf.format(Math.round(diffSec / 60), 'minute');
  if (abs < 86_400) return rtf.format(Math.round(diffSec / 3600), 'hour');
  return rtf.format(Math.round(diffSec / 86_400), 'day');
}

function RecentLogsWidget() {
  const { t, i18n } = useTranslation('dashboard');
  const queryClient = useQueryClient();

  const { payload: streamPayload } = useLogStream({ intervalMs: 5000 });
  const recentQuery = useRecentLogsQuery();

  useEffect(() => {
    if (streamPayload == null) return;
    void queryClient.invalidateQueries({ queryKey: RECENT_LOGS_KEY });
  }, [streamPayload, queryClient]);

  const logs = recentQuery.data?.data ?? [];

  if (recentQuery.isLoading && logs.length === 0) {
    return (
      <div className="flex flex-col gap-2 p-1">
        {[1, 2, 3].map((n) => (
          <div
            key={n}
            className="h-10 bg-ui-border-l dark:bg-ui-dark-border rounded-lg animate-pulse"
          />
        ))}
      </div>
    );
  }

  if (recentQuery.isError) {
    return (
      <p className="text-sm text-danger-dark dark:text-danger text-center py-4">
        {t('error')}
      </p>
    );
  }

  if (logs.length === 0) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary text-center py-4">
        {t('widgets.recentLogs.empty')}
      </p>
    );
  }

  return (
    <ul className="flex flex-col gap-2 overflow-auto h-full" role="list">
      {logs.map((log) => (
        <li key={log.id}>
          <Link
            to={`/logs/${log.id}`}
            className="flex items-start gap-2 px-2 py-2 rounded-md hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-odoo-purple"
          >
            <span className="shrink-0 mt-0.5">
              <SeverityBadge severity={log.severity} />
            </span>
            <span className="flex-1 min-w-0">
              <span className="block text-sm text-text-primary dark:text-text-dark-primary truncate">
                {log.message}
              </span>
              <span className="block text-xs text-text-muted dark:text-text-dark-muted">
                {log.application?.name ? `${log.application.name} · ` : ''}
                {formatRelative(log.created_at, i18n.language || 'es')}
              </span>
            </span>
          </Link>
        </li>
      ))}
    </ul>
  );
}

export default RecentLogsWidget;
