import type { BaseMeProfile } from '@maya/shared-profile-react';

/**
 * Shape del perfil devuelto por `GET /api/v1/me` de maya_logs.
 *
 * Forma canónica cross-app (2026-05-18): los campos canónicos en español
 * (`permisos`, `tipo_estudios`, `estudios`, `modulos`, `equipos`) viven en
 * `BaseMeProfile`. maya_logs devuelve estos campos como arrays vacíos — no
 * tiene tablas locales de permisos ni de relaciones académicas; la
 * autorización dentro de logs se delega al middleware `RequirePermission`,
 * que resuelve permisos contra maya_authorization vía FDW.
 *
 * Eliminados del payload (no exponer en /me):
 * - `roles`, `department`/`departamento`, `organization_id`.
 */
export type MeProfile = BaseMeProfile & {
  first_name: string | null;
  last_name: string | null;
  username: string | null;
  scope: string;
};
