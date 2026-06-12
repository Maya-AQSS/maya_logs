import { createAppI18n } from '@ceedcv-maya/shared-i18n-react';
import { NAMESPACES, resources } from './resources';

export const { i18n, changeLocale } = createAppI18n(resources, NAMESPACES);

export { DEFAULT_LOCALE, SUPPORTED_LOCALES } from '@ceedcv-maya/shared-i18n-react';
export type { SupportedLocale } from './resources';
export default i18n;
