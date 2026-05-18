import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import type { Log } from '../../types/logs';
import { formatDateTime } from '@maya/shared-ui-react';
import { SeverityBadge } from '../severity';

type LogDetailViewProps = {
  log: Log;
  archivedLogId: number | null;
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

export function LogDetailView({ log, archivedLogId }: LogDetailViewProps) {
  const { t } = useTranslation('logs');

  const metadataJson =
    log.metadata && Object.keys(log.metadata).length > 0
      ? JSON.stringify(log.metadata, null, 2)
      : null;

  return (
    <div className="mt-4 grid grid-cols-1 gap-3 text-base md:grid-cols-2">
      <Field label={t('detail.fields.id')}>{String(log.id)}</Field>

      <Field label={t('detail.fields.application')}>{log.application?.name ?? '—'}</Field>

      <div>
        <div className="block text-sm font-medium text-text-secondary dark:text-text-dark-secondary">
          {t('detail.fields.severity')}
        </div>
        <div className="mt-1 flex min-h-[2.75rem] items-center rounded-lg border border-ui-border bg-ui-body px-3 py-2 shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg">
          <SeverityBadge severity={log.severity} />
        </div>
      </div>

      <div>
        <div className="block text-sm font-medium text-text-secondary dark:text-text-dark-secondary">
          {t('detail.fields.status')}
        </div>
        <div className="mt-1 flex min-h-[2.75rem] items-center rounded-lg border border-ui-border bg-ui-body px-3 py-2 shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg">
          <span
            className={
              log.resolved
                ? 'inline-flex items-center rounded-full bg-success/10 px-2.5 py-1 text-xs font-semibold text-success-dark ring-1 ring-inset ring-success/20'
                : 'inline-flex items-center rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning-dark ring-1 ring-inset ring-warning/20'
            }
          >
            {log.resolved ? t('detail.fields.resolved') : t('detail.fields.unresolved')}
          </span>
        </div>
      </div>

      <Field label={t('detail.fields.errorCode')}>{log.error_code?.code ?? '—'}</Field>

      <Field label={t('detail.fields.createdAt')}>{formatDateTime(log.created_at)}</Field>

      {log.file !== null && (
        <Field label={t('detail.fields.file')}>{log.file}</Field>
      )}

      {log.line !== null && (
        <Field label={t('detail.fields.line')}>{String(log.line)}</Field>
      )}

      {archivedLogId !== null && (
        <div className="md:col-span-2 rounded-lg border border-ui-border bg-ui-card px-4 py-3 text-sm text-text-secondary dark:border-ui-dark-border dark:bg-ui-dark-card dark:text-text-dark-secondary flex items-center justify-between gap-3">
          <span>{t('detail.archivedNotice')}</span>
          <Link
            to={`/archived-logs/${archivedLogId}`}
            className="shrink-0 text-odoo-purple dark:text-odoo-dark-purple hover:underline font-medium"
          >
            {t('detail.viewArchived')}
          </Link>
        </div>
      )}

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
