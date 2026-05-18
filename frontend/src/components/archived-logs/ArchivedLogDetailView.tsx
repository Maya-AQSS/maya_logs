import { useTranslation } from 'react-i18next';
import type { ArchivedLog } from '../../types/logs';
import { formatDateTime } from '@maya/shared-ui-react';
import { SeverityBadge } from '../severity';

type ArchivedLogDetailViewProps = {
  log: ArchivedLog;
};

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="block text-sm font-medium text-text-secondary dark:text-text-dark-secondary">
        {label}
      </div>
      <div className="mt-1 rounded-lg border border-ui-border bg-ui-body px-3 py-2.5 text-sm text-text-primary shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary">
        {children}
      </div>
    </div>
  );
}

export function ArchivedLogDetailView({ log }: ArchivedLogDetailViewProps) {
  const { t } = useTranslation('archivedLogs');
  const metadataJson =
    log.metadata_formatted ??
    (log.metadata && Object.keys(log.metadata).length > 0
      ? JSON.stringify(log.metadata, null, 2)
      : null);

  return (
    <div className="grid grid-cols-1 gap-3 text-base md:grid-cols-2">
      <Field label={t('detail.fields.application')}>{log.application?.name ?? '—'}</Field>

      <div>
        <div className="block text-sm font-medium text-text-secondary dark:text-text-dark-secondary">
          {t('detail.fields.severity')}
        </div>
        <div className="mt-1 flex min-h-[2.75rem] items-center rounded-lg border border-ui-border bg-ui-body px-3 py-2 shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg">
          <SeverityBadge severity={log.severity} />
        </div>
      </div>

      <Field label={t('detail.fields.errorCode')}>{log.error_code?.code ?? '—'}</Field>
      <Field label={t('detail.fields.archivedBy')}>{log.archived_by?.name ?? '—'}</Field>

      <Field label={t('detail.fields.archivedAt')}>{formatDateTime(log.archived_at)}</Field>
      <Field label={t('detail.fields.originalCreatedAt')}>{formatDateTime(log.original_created_at)}</Field>

      <div className="md:col-span-2">
        <div className="block text-sm font-medium text-text-secondary dark:text-text-dark-secondary">
          {t('detail.fields.message')}
        </div>
        <div className="mt-1 max-h-64 overflow-y-auto rounded-lg border border-ui-border bg-ui-body px-3 py-2.5 text-sm text-text-primary whitespace-pre-wrap break-words shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary md:max-h-96">
          {log.message || '—'}
        </div>
      </div>

      <div className="md:col-span-2">
        <div className="block text-sm font-medium text-text-secondary dark:text-text-dark-secondary">
          {t('detail.fields.metadata')}
        </div>
        {metadataJson !== null ? (
          <pre className="mt-1 max-h-64 overflow-y-auto rounded-lg border border-ui-border bg-ui-body px-3 py-2.5 font-mono text-xs text-text-primary whitespace-pre-wrap break-all shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary md:max-h-96">
            {metadataJson}
          </pre>
        ) : (
          <div className="mt-1 rounded-lg border border-ui-border bg-ui-body px-3 py-2.5 text-sm italic text-text-muted shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-muted">
            {t('detail.fields.noMetadata')}
          </div>
        )}
      </div>
    </div>
  );
}
