import { commonResources, COMMON_NAMESPACE, notificationResources, deepMerge } from '@ceedcv-maya/shared-i18n-react';

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
  'notifications',
] as const;
export type Namespace = (typeof NAMESPACES)[number];

// Cada namespace fusiona el canon shared con sus strings locales. Local
// siempre gana en colisión gracias al orden del spread.
const baseEs = commonResources.es.common;
const baseVa = commonResources.va.common;
const baseEn = commonResources.en.common;

export const resources = {
  es: {
    common: baseEs,
    dashboard: deepMerge(baseEs, esDashboard),
    logs: deepMerge(baseEs, esLogs),
    archivedLogs: deepMerge(baseEs, esArchivedLogs),
    errorCodes: deepMerge(baseEs, esErrorCodes),
    comments: deepMerge(baseEs, esComments),
    auth: deepMerge(baseEs, esAuth),
    nav: deepMerge(baseEs, esNav),
    notifications: notificationResources.es.notifications,
  },
  va: {
    common: baseVa,
    dashboard: deepMerge(baseVa, vaDashboard),
    logs: deepMerge(baseVa, vaLogs),
    archivedLogs: deepMerge(baseVa, vaArchivedLogs),
    errorCodes: deepMerge(baseVa, vaErrorCodes),
    comments: deepMerge(baseVa, vaComments),
    auth: deepMerge(baseVa, vaAuth),
    nav: deepMerge(baseVa, vaNav),
    notifications: notificationResources.va.notifications,
  },
  en: {
    common: baseEn,
    dashboard: deepMerge(baseEn, enDashboard),
    logs: deepMerge(baseEn, enLogs),
    archivedLogs: deepMerge(baseEn, enArchivedLogs),
    errorCodes: deepMerge(baseEn, enErrorCodes),
    comments: deepMerge(baseEn, enComments),
    auth: deepMerge(baseEn, enAuth),
    nav: deepMerge(baseEn, enNav),
    notifications: notificationResources.en.notifications,
  },
} as const;
