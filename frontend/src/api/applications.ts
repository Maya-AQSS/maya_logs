import type { ApiEnvelope } from '@maya/shared-auth-react';
import type { ApplicationRef } from '../types/logs';
import { apiGetJson } from './http';

export type ApplicationScope = 'all' | 'with_logs' | 'with_archived_logs';

/** GET /api/v1/applications?scope=... — lista de aplicaciones para dropdowns. */
export async function fetchApplications(scope: ApplicationScope = 'all'): Promise<ApplicationRef[]> {
  const qs = new URLSearchParams({ scope }).toString();
  const body = await apiGetJson<ApiEnvelope<ApplicationRef[]>>(`applications?${qs}`);
  return body.data;
}
