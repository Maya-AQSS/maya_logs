import { useTranslation } from 'react-i18next';
import { SearchInput as SharedSearchInput } from '@maya/shared-ui-react';

type SearchInputProps = {
  value: string;
  onChange: (value: string) => void;
  label?: string;
  hideLabel?: boolean;
  placeholder?: string;
  debounceMs?: number;
};

/**
 * Wrapper sobre `@maya/shared-ui-react` que rellena los textos por defecto
 * desde `common.filters.search*` cuando no se pasan por props.
 */
export function SearchInput({
  value,
  onChange,
  label,
  hideLabel = false,
  placeholder,
  debounceMs = 300,
}: SearchInputProps) {
  const { t } = useTranslation('common');

  return (
    <SharedSearchInput
      value={value}
      onChange={onChange}
      label={label ?? t('filters.searchLabel')}
      hideLabel={hideLabel}
      placeholder={placeholder ?? t('filters.searchPlaceholder')}
      debounceMs={debounceMs}
    />
  );
}
