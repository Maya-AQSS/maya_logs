import { useState } from 'react';
import { FormProvider, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Alert, Button, Card, PageTitle } from '@maya/shared-ui-react';
import { Link, useNavigate } from 'react-router-dom';
import { fetchApplications, type ApplicationScope } from '../api/applications';
import { createErrorCode, type ErrorCodePayload } from '../api/errorCodes';
import { ErrorCodeForm } from '../components/error-codes';
import type { ApplicationRef, ErrorCode } from '../types/logs';
import {
  errorCodeFormSchema,
  emptyErrorCodeForm,
  type ErrorCodeFormInput,
} from '../schemas/errorCode';
import { createDataHook, createMutationHook } from '@maya/shared-auth-react';
import { PermissionGate } from '../components/layout/PermissionGate';
import { LOGS_PERMISSIONS } from '../permissions';

const useApplicationsQuery = createDataHook<ApplicationScope, ApplicationRef[]>({
  queryKey: (scope) => ['applications', scope],
  fetcher: (scope) => fetchApplications(scope),
  defaultOptions: { staleTime: 60_000 },
});

const useCreateErrorCode = createMutationHook<ErrorCodePayload, ErrorCode>({
  mutationFn: (payload) => createErrorCode(payload),
  invalidates: () => [['error-codes']],
});

function toPayload(form: ErrorCodeFormInput): ErrorCodePayload {
  const parsedLine = form.line.trim() === '' ? null : Number(form.line);
  return {
    application_id: Number(form.application_id),
    code: form.code,
    name: form.name,
    file: form.file.trim() === '' ? null : form.file,
    line: parsedLine != null && Number.isFinite(parsedLine) ? parsedLine : null,
    description: form.description.trim() === '' ? null : form.description,
  };
}

export function ErrorCodeCreatePage() {
  const navigate = useNavigate();
  const { t } = useTranslation(['errorCodes', 'common']);
  const [saveError, setSaveError] = useState<string | null>(null);

  const methods = useForm<ErrorCodeFormInput>({
    defaultValues: emptyErrorCodeForm,
    mode: 'onChange',
    resolver: zodResolver(errorCodeFormSchema),
  });

  const applicationsQuery = useApplicationsQuery('all');
  const applications = applicationsQuery.data ?? [];
  const createMutation = useCreateErrorCode();

  const onSubmit = methods.handleSubmit((values) => {
    setSaveError(null);
    createMutation.mutate(toPayload(values), {
      onSuccess: (created) => navigate(`/error-codes/${created.id}`),
      onError: (e) => setSaveError(e instanceof Error ? e.message : String(e)),
    });
  });

  const saving = createMutation.isPending || methods.formState.isSubmitting;

  return (
    <PermissionGate permission={LOGS_PERMISSIONS.errorCodeCreate}>
    <div className="px-4 py-6 sm:px-6 lg:px-8">
      <PageTitle
        title={t('errorCodes:createTitle')}
        onBack={() => navigate(-1)}
        backLabel={t('common:actions.back')}
      />

      <Card padding="md" radius="xl" className="mt-4">
        <FormProvider {...methods}>
          <form
            onSubmit={(e) => {
              e.preventDefault();
              void onSubmit();
            }}
          >
            <ErrorCodeForm applications={applications} disabled={saving} />

            {saveError && (
              <Alert tone="danger" className="mt-4">
                {saveError}
              </Alert>
            )}

            <div className="mt-4 flex justify-end gap-2">
              <Link
                to="/error-codes"
                className="inline-flex items-center bg-transparent text-text-secondary dark:text-text-dark-secondary border border-ui-border dark:border-ui-dark-border hover:text-text-primary dark:hover:text-text-dark-primary hover:border-text-secondary dark:hover:border-text-dark-secondary px-4 py-1.5 rounded-md text-sm font-semibold transition-colors cursor-pointer"
              >
                {t('common:actions.cancel')}
              </Link>
              <Button type="submit" variant="primary" size="sm" disabled={saving} loading={saving}>
                {saving ? '…' : t('common:actions.create')}
              </Button>
            </div>
          </form>
        </FormProvider>
      </Card>
    </div>
    </PermissionGate>
  );
}
