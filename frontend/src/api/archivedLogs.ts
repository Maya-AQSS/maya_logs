import type { ApiEnvelope, PaginatedResponse, SortDir } from '@maya/shared-auth-react';
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

function buildQuery(filters: ArchivedLogsFilters): string {
  const qs = new URLSearchParams();
  if (filters.severity && filters.severity.length > 0) qs.set('severity', filters.severity.join(','));
  if (filters.application_id != null) qs.set('application_id', String(filters.application_id));
  if (filters.date_from) qs.set('date_from', filters.date_from);
  if (filters.date_to) qs.set('date_to', filters.date_to);
  if (filters.sort_by) qs.set('sort_by', filters.sort_by);
  if (filters.sort_dir) qs.set('sort_dir', filters.sort_dir);
  if (filters.per_page != null) qs.set('per_page', String(filters.per_page));
  if (filters.page != null) qs.set('page', String(filters.page));
  return qs.toString();
}

/** GET /api/v1/archived-logs — paginado con filtros. */
export async function fetchArchivedLogs(filters: ArchivedLogsFilters = {}): Promise<PaginatedResponse<ArchivedLog>> {
  const qs = buildQuery(filters);
  const path = qs === '' ? 'archived-logs' : `archived-logs?${qs}`;
  return apiGetJson<PaginatedResponse<ArchivedLog>>(path);
}

/** GET /api/v1/archived-logs/{id} — detalle con relaciones y `comments_count`. */
export async function fetchArchivedLog(id: number): Promise<ArchivedLog> {
  const body = await apiGetJson<ApiEnvelope<ArchivedLog>>(`archived-logs/${id}`);
  return body.data;
}

/** PATCH /api/v1/archived-logs/{id} — actualiza campos editables (description, url_tutorial). */
export async function updateArchivedLog(id: number, payload: ArchivedLogUpdatePayload): Promise<ArchivedLog> {
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
