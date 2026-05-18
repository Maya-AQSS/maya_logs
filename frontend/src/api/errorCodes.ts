import type { ApiEnvelope, PaginatedResponse } from '@maya/shared-auth-react';
import type { ErrorCode } from '../types/logs';
import { apiFetchJson, apiGetJson } from './http';

export type { ErrorCode } from '../types/logs';

export type ErrorCodesFilters = {
  search?: string | null;
  application_id?: number | null;
  per_page?: number | null;
  page?: number | null;
};

export type ErrorCodePayload = {
  application_id: number;
  code: string;
  name: string;
  file?: string | null;
  line?: number | null;
  description?: string | null;
};

function buildQuery(filters: ErrorCodesFilters): string {
  const qs = new URLSearchParams();
  if (filters.search) qs.set('search', filters.search);
  if (filters.application_id != null) qs.set('application_id', String(filters.application_id));
  if (filters.per_page != null) qs.set('per_page', String(filters.per_page));
  if (filters.page != null) qs.set('page', String(filters.page));
  return qs.toString();
}

/** GET /api/v1/error-codes — paginado. */
export async function fetchErrorCodes(filters: ErrorCodesFilters = {}): Promise<PaginatedResponse<ErrorCode>> {
  const qs = buildQuery(filters);
  const path = qs === '' ? 'error-codes' : `error-codes?${qs}`;
  return apiGetJson<PaginatedResponse<ErrorCode>>(path);
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
export async function updateErrorCode(id: number, payload: Partial<ErrorCodePayload>): Promise<ErrorCode> {
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
