import { useCallback, useEffect, useState } from 'react';
import { FormProvider, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Alert, Button, Card, ConfirmDialog, PageTitle } from '@maya/shared-ui-react';
import { useNavigate, useParams } from 'react-router-dom';
import { fetchApplications } from '../api/applications';
import {
  deleteErrorCode,
  fetchErrorCode,
  updateErrorCode,
  type ErrorCodePayload,
} from '../api/errorCodes';
import { CommentThread } from '../components/comments';
import { PermissionGate } from '../components/layout/PermissionGate';
import { useUserProfile } from '../features/user-profile';
import { LOGS_PERMISSIONS } from '../permissions';
import { ErrorCodeForm } from '../components/error-codes';
import type { ApplicationRef, ErrorCode } from '../types/logs';
import {
  errorCodeFormSchema,
  emptyErrorCodeForm,
  type ErrorCodeFormInput,
} from '../schemas/errorCode';

type State =
  | { status: 'loading'; data: ErrorCode | null }
  | { status: 'ready'; data: ErrorCode }
  | { status: 'error'; error: string; data: ErrorCode | null }
  | { status: 'not-found' };

function toFormInput(ec: ErrorCode): ErrorCodeFormInput {
  return {
    application_id: ec.application?.id != null ? String(ec.application.id) : '',
    code: ec.code,
    name: ec.name,
    file: ec.file ?? '',
    line: ec.line != null ? String(ec.line) : '',
    description: ec.description ?? '',
  };
}

function toPayload(form: ErrorCodeFormInput): Partial<ErrorCodePayload> {
  const parsedLine = form.line.trim() === '' ? null : Number(form.line);
  return {
    application_id: form.application_id ? Number(form.application_id) : undefined,
    code: form.code,
    name: form.name,
    file: form.file.trim() === '' ? null : form.file,
    line: parsedLine != null && Number.isFinite(parsedLine) ? parsedLine : null,
    description: form.description.trim() === '' ? null : form.description,
  };
}

export function ErrorCodeDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation(['errorCodes', 'common', 'comments']);
  const { hasPermission } = useUserProfile();
  const canUpdate = hasPermission(LOGS_PERMISSIONS.errorCodeUpdate);
  const canDelete = hasPermission(LOGS_PERMISSIONS.errorCodeDelete);

  const errorCodeId = id ? Number(id) : NaN;
  const validId = Number.isFinite(errorCodeId) && errorCodeId > 0;

  const [applications, setApplications] = useState<ApplicationRef[]>([]);
  const [state, setState] = useState<State>({ status: 'loading', data: null });
  const [editing, setEditing] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const methods = useForm<ErrorCodeFormInput>({
    defaultValues: emptyErrorCodeForm,
    mode: 'onChange',
    resolver: zodResolver(errorCodeFormSchema),
  });

  useEffect(() => {
    let cancelled = false;
    fetchApplications('all')
      .then((apps) => {
        if (!cancelled) setApplications(apps);
      })
      .catch(() => {
        /* ignorar, select mostrará vacío */
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const load = useCallback(() => {
    if (!validId) {
      setState({ status: 'not-found' });
      return () => {};
    }
    let cancelled = false;
    setState((prev) => ({
      status: 'loading',
      data: prev.status === 'ready' || prev.status === 'error' ? prev.data : null,
    }));
    fetchErrorCode(errorCodeId)
      .then((data) => {
        if (cancelled) return;
        setState({ status: 'ready', data });
        methods.reset(toFormInput(data));
      })
      .catch((e) => {
        if (cancelled) return;
        const message = e instanceof Error ? e.message : String(e);
        if (/404/.test(message)) {
          setState({ status: 'not-found' });
        } else {
          setState((prev) => ({
            status: 'error',
            error: message,
            data: prev.status === 'ready' || prev.status === 'error' ? prev.data : null,
          }));
        }
      });
    return () => {
      cancelled = true;
    };
  }, [errorCodeId, validId, methods]);

  useEffect(() => load(), [load]);

  const ec = state.status === 'ready' || state.status === 'error' ? state.data : null;

  const onStartEdit = useCallback(() => {
    if (!ec) return;
    methods.reset(toFormInput(ec));
    setSaveError(null);
    setEditing(true);
  }, [ec, methods]);

  const onCancelEdit = useCallback(() => {
    if (ec) methods.reset(toFormInput(ec));
    setEditing(false);
    setSaveError(null);
  }, [ec, methods]);

  const onSubmit = methods.handleSubmit(async (values) => {
    if (!validId) return;
    setSaveError(null);
    try {
      const updated = await updateErrorCode(errorCodeId, toPayload(values));
      setState({ status: 'ready', data: updated });
      methods.reset(toFormInput(updated));
      setEditing(false);
    } catch (e) {
      setSaveError(e instanceof Error ? e.message : String(e));
    }
  });

  const onDelete = useCallback(async () => {
    if (!validId) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      await deleteErrorCode(errorCodeId);
      navigate('/error-codes');
    } catch (e) {
      setDeleteError(e instanceof Error ? e.message : String(e));
      setDeleting(false);
      setConfirmDelete(false);
    }
  }, [errorCodeId, validId, navigate]);

  if (state.status === 'not-found') {
    return (
      <PermissionGate permission={LOGS_PERMISSIONS.errorCodeShow}>
        <div className="px-4 py-6 sm:px-6 lg:px-8">
          <PageTitle
            title={t('errorCodes:detailTitle')}
            onBack={() => navigate(-1)}
            backLabel={t('common:actions.back')}
          />
          <Card padding="lg" className="mt-4 border-dashed text-center text-sm text-text-muted dark:text-text-dark-muted">
            {t('errorCodes:notFound')}
          </Card>
        </div>
      </PermissionGate>
    );
  }

  const saving = methods.formState.isSubmitting;

  return (
    <PermissionGate permission={LOGS_PERMISSIONS.errorCodeShow}>
    <div className="px-4 py-6 sm:px-6 lg:px-8">
      <PageTitle
        title={ec ? t('errorCodes:detailTitleWithCode', { code: ec.code }) : t('errorCodes:detailTitle')}
        onBack={() => navigate(-1)}
        backLabel={t('common:actions.back')}
        actions={
          ec && !editing ? (
            <>
              {canUpdate && (
                <Button variant="outline" size="sm" onClick={onStartEdit}>
                  {t('common:actions.edit')}
                </Button>
              )}
              {canDelete && (
                <Button variant="danger" size="sm" onClick={() => setConfirmDelete(true)}>
                  {t('common:actions.delete')}
                </Button>
              )}
            </>
          ) : undefined
        }
      />

      {deleteError && (
        <Alert tone="danger" className="mt-4">
          {deleteError}
        </Alert>
      )}

      {state.status === 'error' && (
        <Alert tone="danger" className="mt-4">
          {t('errorCodes:loadErrorDetail', { error: state.error })}
        </Alert>
      )}

      {state.status === 'loading' && !ec && (
        <Card padding="lg" className="mt-4 text-center text-sm text-text-muted dark:text-text-dark-muted">
          {t('common:status.loading')}
        </Card>
      )}

      {ec && (
        <div className="mt-4 space-y-4">
          <Card padding="md">
            <FormProvider {...methods}>
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  void onSubmit();
                }}
              >
                <ErrorCodeForm
                  applications={applications}
                  disabled={!editing || saving}
                  codeReadOnly
                  applicationReadOnly
                />

                {editing && saveError && (
                  <Alert tone="danger" className="mt-4">
                    {saveError}
                  </Alert>
                )}

                {editing && (
                  <div className="mt-4 flex justify-end gap-2">
                    <Button
                      type="button"
                      variant="secondary"
                      size="sm"
                      onClick={onCancelEdit}
                      disabled={saving}
                    >
                      {t('common:actions.cancel')}
                    </Button>
                    <Button
                      type="submit"
                      variant="primary"
                      size="sm"
                      disabled={saving}
                      loading={saving}
                    >
                      {saving ? '…' : t('common:actions.save')}
                    </Button>
                  </div>
                )}
              </form>
            </FormProvider>
          </Card>

          <Card padding="md">
            <h2 className="text-base font-semibold text-text-primary dark:text-text-dark-primary">
              {t('comments:title')}
            </h2>
            <div className="mt-3">
              <CommentThread commentableType="error-codes" commentableId={ec.id} />
            </div>
          </Card>
        </div>
      )}

      <ConfirmDialog
        open={confirmDelete}
        title={t('errorCodes:deleteTitle')}
        description={t('errorCodes:deleteConfirmDescription')}
        confirmLabel={t('common:actions.delete')}
        variant="danger"
        loading={deleting}
        onConfirm={onDelete}
        onCancel={() => !deleting && setConfirmDelete(false)}
      />
    </div>
    </PermissionGate>
  );
}
