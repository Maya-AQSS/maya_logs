/**
 * Inicialización del cliente OIDC (Keycloak vía @ceedcv-maya/shared-auth-react).
 * Solo este archivo lee la configuración de Keycloak (`KEYCLOAK_*` de /config.js, con
 * `VITE_KEYCLOAK_*` de respaldo en desarrollo); el resto de la app
 * consume el servicio resultante o los hooks (`useOidcSession`, `useAuth`) del paquete.
 *
 * Identidad y permisos de negocio: GET /api/v1/me.
 */
import { createOidcAdapter, publicKeycloakConfig } from '@ceedcv-maya/shared-auth-react';

// Configuración al arrancar y no al construir (D26 R6): index.html carga /config.js
// (MAYA_PUBLIC_* del chart) antes del bundle, así la misma imagen vale en todos los entornos.
// publicKeycloakConfig() falla si falta KEYCLOAK_URL, KEYCLOAK_REALM o KEYCLOAK_CLIENT_ID.
export const { oidcAuthService, appendBearerAuthorization, triggerSignIn } =
  createOidcAdapter(publicKeycloakConfig());
