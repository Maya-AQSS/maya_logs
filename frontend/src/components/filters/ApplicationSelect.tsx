import { useTranslation } from 'react-i18next';
import type { ApplicationRef } from '../../types/logs';
import { FilterSelect } from './FilterSelect';

type ApplicationSelectProps = {
  applications: ApplicationRef[];
  value: number | null;
  onChange: (id: number | null) => void;
  label?: string;
  hideLabel?: boolean;
  placeholder?: string;
};

export function ApplicationSelect({
  applications,
  value,
  onChange,
  label,
  hideLabel = false,
  placeholder,
}: ApplicationSelectProps) {
  const { t } = useTranslation('common');
  const resolvedLabel = label ?? t('filters.applicationLabel');
  const resolvedPlaceholder = placeholder ?? t('filters.applicationAll');

  return (
    <FilterSelect
      label={resolvedLabel}
      hideLabel={hideLabel}
      value={value ?? ''}
      onChange={(e) => {
        const v = e.target.value;
        onChange(v === '' ? null : Number(v));
      }}
    >
      <option value="">{resolvedPlaceholder}</option>
      {applications.map((app) => (
        <option key={app.id} value={app.id}>
          {app.name}
        </option>
      ))}
    </FilterSelect>
  );
}
