/**
 * Overrides de paridad visual para `FieldLabel` de `@ceedcv-maya/shared-ui-react`
 * en los paneles de filtros.
 *
 * El `FieldLabel` compartido es uppercase / bold / text-muted; los filtros de
 * esta app usan el mismo estilo que el label interno del `SearchInput`
 * compartido (semibold / text-secondary, sin uppercase). Estas clases igualan
 * el aspecto sin redefinir el componente.
 *
 * `normal-case!` y `tracking-normal!` llevan important porque en el stylesheet
 * generado `.uppercase` y `.tracking-wider` (base de FieldLabel) se emiten
 * después y ganarían el conflicto; el resto de overrides ya ganan por orden.
 */
export const filterLabelClass =
  'normal-case! tracking-normal! font-semibold text-text-secondary dark:text-text-dark-secondary';
