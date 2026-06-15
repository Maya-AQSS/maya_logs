import { SearchInput as SharedSearchInput } from '@ceedcv-maya/shared-ui-react';
import { useTranslation } from 'react-i18next';

type SearchInputProps = {
  value: string;
  onChange: (value: string) => void;
  label?: string;
  hideLabel?: boolean;
  placeholder?: string;
  debounceMs?: number;
};

/**
 * Wrapper sobre `@ceedcv-maya/shared-ui-react` que rellena los textos por
 * defecto desde `common.filters.search*` via las props `defaultLabel`/
 * `defaultPlaceholder` del componente compartido (0.16.0).
 */
export function SearchInput(props: SearchInputProps) {
  const { t } = useTranslation('common');

  return (
    <SharedSearchInput
      {...props}
      defaultLabel={t('filters.searchLabel')}
      defaultPlaceholder={t('filters.searchPlaceholder')}
    />
  );
}
