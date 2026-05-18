import { useCallback, useState } from 'react';
import { Alert, Button, ConfirmDialog, PageTitle } from '@maya/shared-ui-react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router-dom';
import { archiveLog, fetchLog, resolveLog, type LogDetailResponse } from '../api/logs';
import { LogDetailView } from '../components/logs';
import { createDataHook, createMutationHook } from '@maya/shared-auth-react';

const useLogDetailQuery = createDataHook<number, LogDetailResponse>({
  queryKey: (id) => ['log', id],
  fetcher: (id) => fetchLog(id),
  defaultOptions: { staleTime: 0 },
});

const useArchiveLog = createMutationHook<number, Awaited<ReturnType<typeof archiveLog>>>({
  mutationFn: (id) => archiveLog(id),
});

const useResolveLog = createMutationHook<number, Awaited<ReturnType<typeof resolveLog>>>({
  mutationFn: (id) => resolveLog(id),
  invalidates: (id) => [['log', id]],
});

type Dialog = 'none' | 'archive' | 'resolve';

export function LogDetailPage() {
  const { t } = useTranslation('logs');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const logId = id ? Number(id) : NaN;
  const validId = Number.isFinite(logId) && logId > 0;

  const [dialog, setDialog] = useState<Dialog>('none');
  const [actionError, setActionError] = useState<string | null>(null);

  const logQuery = useLogDetailQuery(logId, { enabled: validId });
  const archiveMutation = useArchiveLog();
  const resolveMutation = useResolveLog();

  const busy = archiveMutation.isPending || resolveMutation.isPending;

  const errorMessage = logQuery.error
    ? logQuery.error instanceof Error
      ? logQuery.error.message
      : String(logQuery.error)
    : null;

  const notFound = !validId || (logQuery.isError && errorMessage != null && /404/.test(errorMessage));
  const otherError = logQuery.isError && errorMessage != null && !/404/.test(errorMessage);

  const onArchive = useCallback(() => {
    setActionError(null);
    archiveMutation.mutate(logId, {
      onSuccess: (res) => {
        navigate(`/archived-logs/${res.data.archived_log_id}`);
      },
      onError: (e) => {
        setActionError(e instanceof Error ? e.message : String(e));
        setDialog('none');
      },
    });
  }, [logId, navigate, archiveMutation]);

  const onResolve = useCallback(() => {
    setActionError(null);
    resolveMutation.mutate(logId, {
      onSuccess: () => {
        setDialog('none');
      },
      onError: (e) => {
        setActionError(e instanceof Error ? e.message : String(e));
        setDialog('none');
      },
    });
  }, [logId, resolveMutation]);

  if (notFound) {
    return (
      <div className="px-4 py-6 sm:px-6 lg:px-8">
        <PageTitle title={t('detail.title')} onBack={() => navigate(-1)} backLabel={t('detail.back')} />
        <div className="mt-4 rounded-lg border border-dashed border-ui-border bg-ui-card p-6 text-center text-sm text-text-muted dark:border-ui-dark-border dark:bg-ui-dark-card dark:text-text-dark-muted">
          {t('detail.notFound')}
        </div>
      </div>
    );
  }

  const log = logQuery.data?.data ?? null;
  const archivedLogId = logQuery.data?.meta.archived_log_id ?? null;

  return (
    <div className="px-4 py-6 sm:px-6 lg:px-8">
      <PageTitle
        title={log ? t('detail.titleWithId', { id: log.id }) : t('detail.title')}
        onBack={() => navigate(-1)}
        backLabel={t('detail.back')}
        actions={
          <>
            {log && archivedLogId === null && (
              <Button variant="primary" size="sm" onClick={() => setDialog('archive')}>
                {t('actions.archive')}
              </Button>
            )}
            {log && !log.resolved && (
              <Button variant="teal" size="sm" onClick={() => setDialog('resolve')}>
                {t('actions.resolve')}
              </Button>
            )}
          </>
        }
      />

      {actionError && (
        <Alert tone="danger" className="mt-4">{actionError}</Alert>
      )}

      {otherError && errorMessage && (
        <Alert tone="danger" className="mt-4">{t('detail.loadError', { message: errorMessage })}
        </Alert>
      )}

      {logQuery.isLoading && !log && (
        <div className="mt-4 rounded-lg border border-ui-border bg-ui-card p-6 text-center text-sm text-text-muted dark:border-ui-dark-border dark:bg-ui-dark-card dark:text-text-dark-muted">
          {t('detail.loading')}
        </div>
      )}

      {log && <LogDetailView log={log} archivedLogId={archivedLogId} />}

      <ConfirmDialog
        open={dialog === 'archive'}
        title={t('confirmations.archive.title')}
        description={t('confirmations.archive.message')}
        confirmLabel={t('confirmations.archive.confirmLabel')}
        loading={busy}
        onConfirm={onArchive}
        onCancel={() => !busy && setDialog('none')}
      />
      <ConfirmDialog
        open={dialog === 'resolve'}
        title={t('confirmations.resolve.title')}
        description={t('confirmations.resolve.message')}
        confirmLabel={t('confirmations.resolve.confirmLabel')}
        loading={busy}
        onConfirm={onResolve}
        onCancel={() => !busy && setDialog('none')}
      />
    </div>
  );
}
