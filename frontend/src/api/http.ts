/**
 * Cliente HTTP autenticado — delegado al factory de @ceedcv-maya/shared-auth-react.
 * `createServiceApiClient` resuelve la baseUrl (`API_URL` de /config.js, VITE_API_URL
 * en desarrollo o `peerOrigin('logs-api')/api/v1`) y el Bearer lo añade la instancia
 * Keycloak de {@link ../auth/oidcAdapter}.
 */
import {
  type ApiFetchOptions,
  ApiHttpError,
  createServiceApiClient,
  publicConfig,
} from '@ceedcv-maya/shared-auth-react';
import { oidcAuthService } from '../auth/oidcAdapter';

const client = createServiceApiClient(
  'logs-api',
  oidcAuthService.keycloak,
  publicConfig('API_URL'),
);

export { type ApiFetchOptions, ApiHttpError };
export const { apiFetchJson, apiGetJson, buildApiUrl, getBearerToken } = client;
