/**
 * Cliente HTTP autenticado — delegado al factory de @ceedcv-maya/shared-auth-react.
 * `createServiceApiClient` resuelve la baseUrl (VITE_API_URL override o
 * `peerOrigin('logs-api')/api/v1`) y el Bearer lo añade la instancia Keycloak
 * de {@link ../auth/oidcAdapter}.
 */
import {
  type ApiFetchOptions,
  ApiHttpError,
  createServiceApiClient,
} from '@ceedcv-maya/shared-auth-react';
import { oidcAuthService } from '../auth/oidcAdapter';

const client = createServiceApiClient(
  'logs-api',
  oidcAuthService.keycloak,
  import.meta.env.VITE_API_URL as string | undefined,
);

export { type ApiFetchOptions, ApiHttpError };
export const { apiFetchJson, apiGetJson, buildApiUrl, getBearerToken } = client;
