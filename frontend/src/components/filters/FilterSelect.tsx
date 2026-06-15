import { FieldLabel, Select } from '@ceedcv-maya/shared-ui-react';
import type { SelectHTMLAttributes } from 'react';
import { filterLabelClass } from './fieldStyles';

/**
 * Diferencias del `<select>` de filtros respecto al `Select` compartido
 * (`fieldSize="comfortable"`): flecha propia (▾) en lugar de la nativa,
 * esquinas `rounded-lg`, sombra, fondo dark de tarjeta y focus ring Odoo.
 */
const selectClass =
  'appearance-none rounded-lg pr-10 shadow-sm dark:bg-ui-dark-card focus:border-odoo-purple focus:outline-none focus:ring-2 focus:ring-odoo-purple/20';

type FilterSelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label?: string;
  hideLabel?: boolean;
};

/**
 * Select de filtro con label opcional y flecha ▾ propia, construido sobre el
 * `Select` de `@ceedcv-maya/shared-ui-react`. Lo comparten `ApplicationSelect`
 * y `ResolvedFilter`.
 */
export function FilterSelect({ label, hideLabel = false, children, ...rest }: FilterSelectProps) {
  return (
    <div>
      {!hideLabel && label && <FieldLabel className={filterLabelClass}>{label}</FieldLabel>}
      <div className="relative">
        <Select fieldSize="comfortable" className={selectClass} {...rest}>
          {children}
        </Select>
        <span
          className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-text-muted dark:text-text-dark-muted"
          aria-hidden
        >
          ▾
        </span>
      </div>
    </div>
  );
}
