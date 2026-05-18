import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ApplicationRef } from '../../types/logs';
import { Button } from '@maya/shared-ui-react';
import { ApplicationSelect, SearchInput } from '../filters';

const ChevronIcon = ({ open }: { open: boolean }) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 20 20"
    fill="currentColor"
    aria-hidden="true"
    className={`w-4 h-4 transition-transform ${open ? 'rotate-180' : ''}`}
  >
    <path
      fillRule="evenodd"
      d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
      clipRule="evenodd"
    />
  </svg>
);

export type ErrorCodesFiltersState = {
  search: string;
  applicationId: number | null;
};

type ErrorCodesFiltersProps = {
  value: ErrorCodesFiltersState;
  applications: ApplicationRef[];
  onChange: (patch: Partial<ErrorCodesFiltersState>) => void;
  onReset: () => void;
};

export function ErrorCodesFilters({
  value,
  applications,
  onChange,
  onReset,
}: ErrorCodesFiltersProps) {
  const { t } = useTranslation('errorCodes');
  const [isOpen, setIsOpen] = useState(false);

  const hasActiveFilters = value.search || value.applicationId !== null;

  return (
    <div className="bg-ui-card dark:bg-ui-dark-card border border-ui-border dark:border-ui-dark-border rounded-lg mb-6 shadow-sm">
      {/* Toggle visible solo en móvil */}
      <Button
        variant="unstyled"
        size="sm"
        onClick={() => setIsOpen((v) => !v)}
        aria-expanded={isOpen}
        aria-controls="error-codes-filter-panel"
        className="md:hidden w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-text-primary dark:text-text-dark-primary"
      >
        <span>
          {t('filters.title')}
          {hasActiveFilters && (
            <span className="ml-2 inline-flex items-center justify-center w-2 h-2 rounded-full bg-odoo-purple" aria-hidden="true" />
          )}
        </span>
        <ChevronIcon open={isOpen} />
      </Button>

      {/* Panel de filtros: colapsable en móvil, siempre visible en ≥ md */}
      <div
        id="error-codes-filter-panel"
        className={`${isOpen ? 'flex' : 'hidden'} md:flex flex-wrap items-end gap-3 p-4`}
      >
        <div className="flex-1 min-w-[180px]">
          <SearchInput
            value={value.search}
            onChange={(search) => onChange({ search })}
            hideLabel
            placeholder={t('filters.searchPlaceholder')}
          />
        </div>
        <div className="flex-1 min-w-[150px]">
          <ApplicationSelect
            applications={applications}
            value={value.applicationId}
            hideLabel
            placeholder={t('filters.applicationAll')}
            onChange={(applicationId) => onChange({ applicationId })}
          />
        </div>
        <div className="shrink-0">
          <Button type="button" variant="secondary" size="sm" onClick={onReset}>
            {t('filters.reset')}
          </Button>
        </div>
      </div>
    </div>
  );
}
