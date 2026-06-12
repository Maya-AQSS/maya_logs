/**
 * Shape del perfil devuelto por `GET /api/v1/me` de maya_logs.
 *
 * Idéntico en las 4 apps (authorization, audit, logs, dashboard) → desde
 * 0.16.0 es el `StandardMeProfile` de @ceedcv-maya/shared-profile-react.
 * maya_logs devuelve los campos canónicos (`permisos`, `tipo_estudios`,
 * `estudios`, `modulos`, `equipos`) como arrays vacíos — la autorización se
 * delega al middleware `RequirePermission` contra maya_authorization via FDW.
 */
export type { StandardMeProfile as MeProfile } from '@ceedcv-maya/shared-profile-react';
