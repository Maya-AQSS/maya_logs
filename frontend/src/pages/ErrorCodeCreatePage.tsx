import { useState } from 'react';
import { FormProvider, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Alert, Button, PageTitle } from '@maya/shared-ui-react';
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
    <div className="px-4 py-6 sm:px-6 lg:px-8">
      <PageTitle title="Nuevo código de error" onBack={() => navigate(-1)} backLabel="Volver" />

      <div className="mt-4 rounded-lg border border-ui-border bg-ui-card p-4 dark:border-ui-dark-border dark:bg-ui-dark-card">
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
                Cancelar
              </Link>
              <Button type="submit" variant="primary" size="sm" disabled={saving} loading={saving}>
                {saving ? '…' : 'Crear'}
              </Button>
            </div>
          </form>
        </FormProvider>
      </div>
    </div>
  );
}
