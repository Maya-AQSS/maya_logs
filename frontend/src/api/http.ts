/**
 * Cliente HTTP autenticado — delegado al factory de @ceedcv-maya/shared-auth-react.
 * El Bearer lo añade la instancia Keycloak de {@link ../auth/oidcAdapter}.
 */
import { createApiClient, ApiHttpError, type ApiFetchOptions } from '@ceedcv-maya/shared-auth-react'
import { oidcAuthService } from '../auth/oidcAdapter'
import { peerOrigin } from '../lib/peerService'

const baseUrl = ((import.meta.env.VITE_API_URL as string | undefined)?.trim())
  || `${peerOrigin('logs-api')}/api/v1`

const client = createApiClient(oidcAuthService.keycloak, baseUrl)

export { ApiHttpError, type ApiFetchOptions }
export const { apiFetchJson, apiGetJson, buildApiUrl, getBearerToken } = client
