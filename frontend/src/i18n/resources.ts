import { commonResources, COMMON_NAMESPACE } from '@maya/shared-i18n-react';

import esDashboard from './locales/es/dashboard.json';
import esLogs from './locales/es/logs.json';
import esArchivedLogs from './locales/es/archivedLogs.json';
import esErrorCodes from './locales/es/errorCodes.json';
import esComments from './locales/es/comments.json';
import esAuth from './locales/es/auth.json';
import esNav from './locales/es/nav.json';

import vaDashboard from './locales/va/dashboard.json';
import vaLogs from './locales/va/logs.json';
import vaArchivedLogs from './locales/va/archivedLogs.json';
import vaErrorCodes from './locales/va/errorCodes.json';
import vaComments from './locales/va/comments.json';
import vaAuth from './locales/va/auth.json';
import vaNav from './locales/va/nav.json';

import enDashboard from './locales/en/dashboard.json';
import enLogs from './locales/en/logs.json';
import enArchivedLogs from './locales/en/archivedLogs.json';
import enErrorCodes from './locales/en/errorCodes.json';
import enComments from './locales/en/comments.json';
import enAuth from './locales/en/auth.json';
import enNav from './locales/en/nav.json';

export const SUPPORTED_LOCALES = ['es', 'va', 'en'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

export const DEFAULT_LOCALE: SupportedLocale = 'es';

export const NAMESPACES = [
  COMMON_NAMESPACE,
  'dashboard',
  'logs',
  'archivedLogs',
  'errorCodes',
  'comments',
  'auth',
  'nav',
] as const;
export type Namespace = (typeof NAMESPACES)[number];

export const resources = {
  es: {
    ...commonResources.es,
    dashboard: esDashboard,
    logs: esLogs,
    archivedLogs: esArchivedLogs,
    errorCodes: esErrorCodes,
    comments: esComments,
    auth: esAuth,
    nav: esNav,
  },
  va: {
    ...commonResources.va,
    dashboard: vaDashboard,
    logs: vaLogs,
    archivedLogs: vaArchivedLogs,
    errorCodes: vaErrorCodes,
    comments: vaComments,
    auth: vaAuth,
    nav: vaNav,
  },
  en: {
    ...commonResources.en,
    dashboard: enDashboard,
    logs: enLogs,
    archivedLogs: enArchivedLogs,
    errorCodes: enErrorCodes,
    comments: enComments,
    auth: enAuth,
    nav: enNav,
  },
} as const;
