import { useTranslation } from 'react-i18next';
import { useFormContext } from 'react-hook-form';
import { FieldLabel, Select, TextArea, TextInput } from '@maya/shared-ui-react';
import type { ApplicationRef } from '../../types/logs';
import type { ErrorCodeFormInput } from '../../schemas/errorCode';

interface ErrorCodeFormProps {
  applications: ApplicationRef[];
  disabled?: boolean;
  codeReadOnly?: boolean;
  applicationReadOnly?: boolean;
}

/**
 * Presentational form for error code create/edit. Consumes the surrounding
 * `<FormProvider>` for state + validation (see `errorCodeFormSchema`).
 */
export function ErrorCodeForm({
  applications,
  disabled = false,
  codeReadOnly = false,
  applicationReadOnly = false,
}: ErrorCodeFormProps) {
  const { t } = useTranslation('errorCodes');
  const {
    register,
    formState: { errors },
  } = useFormContext<ErrorCodeFormInput>();

  const applicationLocked = applicationReadOnly || disabled;

  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
      <div>
        <FieldLabel htmlFor="error-code-name" required>
          {t('form.name')}
        </FieldLabel>
        <TextInput
          id="error-code-name"
          fieldSize="comfortable"
          disabled={disabled}
          error={!!errors.name}
          {...register('name')}
        />
        {errors.name && <p className="mt-1 text-xs text-danger">{errors.name.message}</p>}
      </div>

      <div>
        <FieldLabel htmlFor="error-code-code" required>
          {t('form.code')}
        </FieldLabel>
        <TextInput
          id="error-code-code"
          fieldSize="comfortable"
          disabled={disabled}
          readOnly={codeReadOnly}
          className="font-mono"
          error={!!errors.code}
          {...register('code')}
        />
        {errors.code && <p className="mt-1 text-xs text-danger">{errors.code.message}</p>}
      </div>

      <div>
        <FieldLabel htmlFor="error-code-application" required>
          {t('form.application')}
        </FieldLabel>
        <Select
          id="error-code-application"
          fieldSize="comfortable"
          disabled={applicationLocked}
          error={!!errors.application_id}
          {...register('application_id')}
        >
          <option value="">{t('form.applicationPlaceholder')}</option>
          {applications.map((app) => (
            <option key={app.id} value={app.id}>
              {app.name}
            </option>
          ))}
        </Select>
        {errors.application_id && (
          <p className="mt-1 text-xs text-danger">{errors.application_id.message}</p>
        )}
      </div>

      <div>
        <FieldLabel htmlFor="error-code-file">{t('form.file')}</FieldLabel>
        <TextInput
          id="error-code-file"
          fieldSize="comfortable"
          disabled={disabled}
          className="font-mono"
          error={!!errors.file}
          {...register('file')}
        />
        {errors.file && <p className="mt-1 text-xs text-danger">{errors.file.message}</p>}
      </div>

      <div>
        <FieldLabel htmlFor="error-code-line">{t('form.line')}</FieldLabel>
        <TextInput
          id="error-code-line"
          type="number"
          fieldSize="comfortable"
          min={1}
          disabled={disabled}
          error={!!errors.line}
          {...register('line')}
        />
        {errors.line && <p className="mt-1 text-xs text-danger">{errors.line.message}</p>}
      </div>

      <div className="md:col-span-2">
        <FieldLabel htmlFor="error-code-description">{t('form.description')}</FieldLabel>
        <TextArea
          id="error-code-description"
          fieldSize="comfortable"
          rows={4}
          disabled={disabled}
          {...register('description')}
        />
        {errors.description && (
          <p className="mt-1 text-xs text-danger">{errors.description.message}</p>
        )}
      </div>
    </div>
  );
}
