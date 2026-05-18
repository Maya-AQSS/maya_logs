import { useCallback, useState } from 'react';
import {
  Alert,
  Button,
  ConfirmDialog,
  FieldLabel,
  PageTitle,
  TextArea,
  TextInput,
} from '@maya/shared-ui-react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import {
  deleteArchivedLog,
  fetchArchivedLog,
  updateArchivedLog,
} from '../api/archivedLogs';
import { ArchivedLogDetailView } from '../components/archived-logs';
import { CommentThread } from '../components/comments';
import {
  archivedLogEditSchema,
  emptyArchivedLogEdit,
  type ArchivedLogEditInput,
} from '../schemas/archivedLog';
import type { ArchivedLog } from '../types/logs';
import { createDataHook, createMutationHook } from '@maya/shared-auth-react';

const useArchivedLogDetailQuery = createDataHook<number, ArchivedLog>({
  queryKey: (id) => ['archived-log', id],
  fetcher: (id) => fetchArchivedLog(id),
  defaultOptions: { staleTime: 0 },
});

type UpdateVars = { id: number; description: string | null; url_tutorial: string | null };

const useUpdateArchivedLog = createMutationHook<UpdateVars, ArchivedLog>({
  mutationFn: ({ id, description, url_tutorial }) =>
    updateArchivedLog(id, { description, url_tutorial }),
  invalidates: ({ id }) => [['archived-log', id], ['archived-logs']],
});

const useDeleteArchivedLog = createMutationHook<number, void>({
  mutationFn: (id) => deleteArchivedLog(id),
  invalidates: () => [['archived-logs']],
});

function toEditForm(log: ArchivedLog): ArchivedLogEditInput {
  return {
    description: log.description ?? '',
    url_tutorial: log.url_tutorial ?? '',
  };
}

export function ArchivedLogDetailPage() {
  const { t } = useTranslation('archivedLogs');
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const logId = id ? Number(id) : NaN;
  const validId = Number.isFinite(logId) && logId > 0;

  const [editing, setEditing] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<ArchivedLogEditInput>({
    mode: 'onSubmit',
    resolver: zodResolver(archivedLogEditSchema),
    defaultValues: emptyArchivedLogEdit,
  });

  const logQuery = useArchivedLogDetailQuery(logId, { enabled: validId });
  const updateMutation = useUpdateArchivedLog();
  const deleteMutation = useDeleteArchivedLog();

  const saving = updateMutation.isPending;
  const deleting = deleteMutation.isPending;

  const errorMessage = logQuery.error
    ? logQuery.error instanceof Error
      ? logQuery.error.message
      : String(logQuery.error)
    : null;

  const notFound = !validId || (logQuery.isError && errorMessage != null && /404/.test(errorMessage));
  const otherError = logQuery.isError && errorMessage != null && !/404/.test(errorMessage);

  const log = logQuery.data ?? null;

  const onStartEdit = useCallback(() => {
    if (!log) return;
    reset(toEditForm(log));
    setSaveError(null);
    setEditing(true);
  }, [log, reset]);

  const onCancelEdit = useCallback(() => {
    setEditing(false);
    setSaveError(null);
  }, []);

  const onSubmit = handleSubmit((values) => {
    if (!validId) return;
    setSaveError(null);
    updateMutation.mutate(
      {
        id: logId,
        description: values.description.trim() === '' ? null : values.description,
        url_tutorial: values.url_tutorial.trim() === '' ? null : values.url_tutorial,
      },
      {
        onSuccess: () => setEditing(false),
        onError: (e) => setSaveError(e instanceof Error ? e.message : String(e)),
      },
    );
  });

  const onDelete = useCallback(() => {
    if (!validId) return;
    setDeleteError(null);
    deleteMutation.mutate(logId, {
      onSuccess: () => navigate('/archived-logs'),
      onError: (e) => {
        setDeleteError(e instanceof Error ? e.message : String(e));
        setConfirmDelete(false);
      },
    });
  }, [logId, validId, navigate, deleteMutation]);

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

  return (
    <div className="px-4 py-6 sm:px-6 lg:px-8">
      <PageTitle
        title={log ? t('detail.titleWithId', { id: log.id }) : t('detail.title')}
        onBack={() => navigate(-1)}
        backLabel={t('detail.back')}
        actions={
          log && !editing ? (
            <>
              <Button variant="outline" size="sm" onClick={onStartEdit}>
                {t('detail.edit')}
              </Button>
              <Button variant="danger" size="sm" onClick={() => setConfirmDelete(true)}>
                {t('detail.delete')}
              </Button>
            </>
          ) : undefined
        }
      />

      {deleteError && (
        <Alert tone="danger" className="mt-4">{deleteError}</Alert>
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

      {log && (
        <div className="mt-4 space-y-4">
          <ArchivedLogDetailView log={log} />

          <div className="rounded-lg border border-ui-border bg-ui-card p-4 dark:border-ui-dark-border dark:bg-ui-dark-card">
            <h2 className="text-base font-semibold text-text-primary dark:text-text-dark-primary">
              {t('detail.editableInfo')}
            </h2>

            {editing ? (
              <form className="mt-3 space-y-4" onSubmit={onSubmit} noValidate>
                <div>
                  <FieldLabel htmlFor="archived-log-description">
                    {t('detail.fields.description')}
                  </FieldLabel>
                  <TextArea
                    id="archived-log-description"
                    fieldSize="comfortable"
                    rows={4}
                    disabled={saving}
                    {...register('description')}
                  />
                  {errors.description && (
                    <p className="mt-1 text-xs text-state-danger">{errors.description.message}</p>
                  )}
                </div>

                <div>
                  <FieldLabel htmlFor="archived-log-url-tutorial">
                    {t('detail.fields.urlTutorial')}
                  </FieldLabel>
                  <TextInput
                    id="archived-log-url-tutorial"
                    type="url"
                    fieldSize="comfortable"
                    disabled={saving}
                    placeholder="https://…"
                    {...register('url_tutorial')}
                  />
                  {errors.url_tutorial && (
                    <p className="mt-1 text-xs text-state-danger">{errors.url_tutorial.message}</p>
                  )}
                </div>

                {saveError && (
                  <Alert tone="danger" className="mt-4">{saveError}</Alert>
                )}

                <div className="flex justify-end gap-2">
                  <Button type="button" variant="secondary" size="sm" onClick={onCancelEdit} disabled={saving}>
                    {t('detail.cancel')}
                  </Button>
                  <Button type="submit" variant="primary" size="sm" disabled={saving} loading={saving}>
                    {saving ? '…' : t('detail.save')}
                  </Button>
                </div>
              </form>
            ) : (
              <dl className="mt-3 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <div>
                  <dt className="text-sm font-medium text-text-secondary dark:text-text-dark-secondary">
                    {t('detail.fields.description')}
                  </dt>
                  <dd className="mt-1 rounded-lg border border-ui-border bg-ui-body px-3 py-2.5 text-sm text-text-primary whitespace-pre-wrap break-words shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary">
                    {log.description && log.description.trim() !== '' ? (
                      log.description
                    ) : (
                      <span className="italic text-text-muted dark:text-text-dark-muted">
                        {t('detail.fields.noDescription')}
                      </span>
                    )}
                  </dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-text-secondary dark:text-text-dark-secondary">
                    {t('detail.fields.urlTutorial')}
                  </dt>
                  <dd className="mt-1 rounded-lg border border-ui-border bg-ui-body px-3 py-2.5 text-sm shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg">
                    {log.url_tutorial && log.url_tutorial.trim() !== '' ? (
                      <a
                        href={log.url_tutorial}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-odoo-purple hover:underline dark:text-odoo-dark-purple break-all"
                      >
                        {log.url_tutorial}
                      </a>
                    ) : (
                      <span className="italic text-text-muted dark:text-text-dark-muted">
                        {t('detail.fields.noUrl')}
                      </span>
                    )}
                  </dd>
                </div>
              </dl>
            )}
          </div>

          <div className="rounded-lg border border-ui-border bg-ui-card p-4 dark:border-ui-dark-border dark:bg-ui-dark-card">
            <h2 className="text-base font-semibold text-text-primary dark:text-text-dark-primary">
              {t('detail.comments')}
            </h2>
            <div className="mt-3">
              <CommentThread commentableType="archived-logs" commentableId={log.id} />
            </div>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={confirmDelete}
        title={t('confirmations.delete.title')}
        description={t('confirmations.delete.message')}
        confirmLabel={t('confirmations.delete.confirmLabel')}
        variant="danger"
        loading={deleting}
        onConfirm={onDelete}
        onCancel={() => !deleting && setConfirmDelete(false)}
      />
    </div>
  );
}
