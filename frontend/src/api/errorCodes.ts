import {
  type ApiEnvelope,
  buildQueryString,
  type PaginatedResponse,
} from '@ceedcv-maya/shared-auth-react';
import type { ErrorCode } from '../types/logs';
import { apiFetchJson, apiGetJson } from './http';

export type { ErrorCode } from '../types/logs';

export type ErrorCodesFilters = {
  search?: string | null;
  application_id?: number | null;
  per_page?: number | null;
  page?: number | null;
  sort_by?: string | null;
  sort_dir?: string | null;
};

export type ErrorCodePayload = {
  application_id: number;
  code: string;
  name: string;
  file?: string | null;
  line?: number | null;
  description?: string | null;
};

/**
 * GET /api/v1/error-codes — paginado.
 *
 * Serialización vía `buildQueryString` canónica (shared-auth-react): misma
 * semántica que el `buildQuery` local que sustituye (omite null/undefined/'')
 * salvo que también omite `0` y `false`. Verificado: ningún call site produce
 * esos valores (application_id del backend siempre > 0, per_page/page > 0, sin
 * filtros booleanos).
 */
export async function fetchErrorCodes(
  filters: ErrorCodesFilters = {},
): Promise<PaginatedResponse<ErrorCode>> {
  return apiGetJson<PaginatedResponse<ErrorCode>>(`error-codes${buildQueryString(filters)}`);
}

/** GET /api/v1/error-codes/{id}. */
export async function fetchErrorCode(id: number): Promise<ErrorCode> {
  const body = await apiGetJson<ApiEnvelope<ErrorCode>>(`error-codes/${id}`);
  return body.data;
}

/** POST /api/v1/error-codes. */
export async function createErrorCode(payload: ErrorCodePayload): Promise<ErrorCode> {
  const body = await apiFetchJson<ApiEnvelope<ErrorCode>>('error-codes', {
    method: 'POST',
    body: payload,
  });
  return body.data;
}

/** PATCH /api/v1/error-codes/{id}. */
export async function updateErrorCode(
  id: number,
  payload: Partial<ErrorCodePayload>,
): Promise<ErrorCode> {
  const body = await apiFetchJson<ApiEnvelope<ErrorCode>>(`error-codes/${id}`, {
    method: 'PATCH',
    body: payload,
  });
  return body.data;
}

/** DELETE /api/v1/error-codes/{id}. */
export async function deleteErrorCode(id: number): Promise<void> {
  await apiFetchJson<void>(`error-codes/${id}`, { method: 'DELETE' });
}
