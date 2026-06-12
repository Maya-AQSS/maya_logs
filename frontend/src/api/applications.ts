import { createApplicationsApi } from '@ceedcv-maya/shared-profile-react';
import type { ApplicationRef } from '../types/logs';
import { apiGetJson } from './http';

export type ApplicationScope = 'all' | 'with_logs' | 'with_archived_logs';

const api = createApplicationsApi<ApplicationScope>({ apiGetJson });

/**
 * GET /api/v1/applications?scope=... — lista de aplicaciones para dropdowns.
 *
 * Nota de tipos: el genérico TRef del paquete exige `slug`, pero el endpoint
 * de logs devuelve solo `{id, name}` (ApplicationRefResource) — se re-tipa al
 * ApplicationRef local. Gap del paquete documentado; el runtime es idéntico.
 */
export async function fetchApplications(scope: ApplicationScope = 'all'): Promise<ApplicationRef[]> {
  return (await api.fetchApplications(scope)) as unknown as ApplicationRef[];
}
