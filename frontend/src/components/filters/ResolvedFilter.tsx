import { useTranslation } from 'react-i18next';
import { FilterSelect } from './FilterSelect';

export type ResolvedFilterValue = 'all' | 'unresolved' | 'only';

type ResolvedFilterProps = {
  value: ResolvedFilterValue;
  onChange: (value: ResolvedFilterValue) => void;
  label?: string;
  hideLabel?: boolean;
};

export function ResolvedFilter({ value, onChange, label, hideLabel = false }: ResolvedFilterProps) {
  const { t } = useTranslation('common');
  const resolvedLabel = label ?? t('filters.resolvedLabel');

  const options: Array<{ value: ResolvedFilterValue; label: string }> = [
    { value: 'all', label: t('resolved.all') },
    { value: 'unresolved', label: t('resolved.unresolved') },
    { value: 'only', label: t('resolved.only') },
  ];

  return (
    <FilterSelect
      label={resolvedLabel}
      hideLabel={hideLabel}
      value={value}
      onChange={(e) => onChange(e.target.value as ResolvedFilterValue)}
    >
      {options.map((opt) => (
        <option key={opt.value} value={opt.value}>
          {opt.label}
        </option>
      ))}
    </FilterSelect>
  );
}
