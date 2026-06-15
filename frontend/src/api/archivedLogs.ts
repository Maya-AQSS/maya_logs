import {
  type ApiEnvelope,
  buildQueryString,
  type PaginatedResponse,
  type SortDir,
} from '@ceedcv-maya/shared-auth-react';
import type { ArchivedLog } from '../types/logs';
import { apiFetchJson, apiGetJson } from './http';

export type { ArchivedLog } from '../types/logs';

export type ArchivedLogsSortBy = 'archived_at' | 'original_created_at' | 'severity' | 'application';

export type ArchivedLogsFilters = {
  severity?: string[] | null;
  application_id?: number | null;
  date_from?: string | null;
  date_to?: string | null;
  sort_by?: ArchivedLogsSortBy | null;
  sort_dir?: SortDir | null;
  per_page?: number | null;
  page?: number | null;
};

export type ArchivedLogUpdatePayload = {
  description?: string | null;
  url_tutorial?: string | null;
};

/**
 * GET /api/v1/archived-logs — paginado con filtros.
 *
 * Serialización vía `buildQueryString` canónica (shared-auth-react): misma
 * semántica que el `buildQuery` local que sustituye (omite null/undefined/''/
 * arrays vacíos; arrays → CSV) salvo que también omite `0` y `false`.
 * Verificado: ningún call site produce esos valores (application_id del backend
 * siempre > 0, per_page/page > 0, sin filtros booleanos).
 */
export async function fetchArchivedLogs(
  filters: ArchivedLogsFilters = {},
): Promise<PaginatedResponse<ArchivedLog>> {
  return apiGetJson<PaginatedResponse<ArchivedLog>>(`archived-logs${buildQueryString(filters)}`);
}

/** GET /api/v1/archived-logs/{id} — detalle con relaciones y `comments_count`. */
export async function fetchArchivedLog(id: number): Promise<ArchivedLog> {
  const body = await apiGetJson<ApiEnvelope<ArchivedLog>>(`archived-logs/${id}`);
  return body.data;
}

/** PATCH /api/v1/archived-logs/{id} — actualiza campos editables (description, url_tutorial). */
export async function updateArchivedLog(
  id: number,
  payload: ArchivedLogUpdatePayload,
): Promise<ArchivedLog> {
  const body = await apiFetchJson<ApiEnvelope<ArchivedLog>>(`archived-logs/${id}`, {
    method: 'PATCH',
    body: payload,
  });
  return body.data;
}

/** DELETE /api/v1/archived-logs/{id} — soft-delete. */
export async function deleteArchivedLog(id: number): Promise<void> {
  await apiFetchJson<void>(`archived-logs/${id}`, { method: 'DELETE' });
}
