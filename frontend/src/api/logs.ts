import type { ApiEnvelope, PaginatedResponse, SortDir } from '@maya/shared-auth-react';
import type { Log, LogStreamPayload } from '../types/logs';
import { ApiHttpError, apiFetchJson, apiGetJson, buildApiUrl, getBearerToken } from './http';
import { appendBearerAuthorization, triggerSignIn } from '../auth/oidcAdapter';

export type { Log, LogSeverity, LogStreamItem, LogStreamPayload } from '../types/logs';

export type LogsSortBy = 'created_at' | 'severity' | 'application' | 'resolved';

export type LogsFilters = {
  search?: string | null;
  severity?: string[] | null;
  application_id?: number | null;
  archived?: 'only' | 'without' | null;
  resolved?: 'only' | 'unresolved' | null;
  date_from?: string | null;
  date_to?: string | null;
  sort_by?: LogsSortBy | null;
  sort_dir?: SortDir | null;
  per_page?: number | null;
  page?: number | null;
};

export type LogDetailResponse = {
  data: Log;
  meta: { archived_log_id: number | null };
};

export type ArchiveLogResponse = {
  data: { archived_log_id: number };
  meta: { already_archived: boolean };
};

export type ResolveLogResponse = ApiEnvelope<{ id: number; resolved: true }>;

function buildLogsQuery(filters: LogsFilters): string {
  const qs = new URLSearchParams();
  if (filters.search) qs.set('search', filters.search);
  if (filters.severity && filters.severity.length > 0) {
    qs.set('severity', filters.severity.join(','));
  }
  if (filters.application_id != null) qs.set('application_id', String(filters.application_id));
  if (filters.archived) qs.set('archived', filters.archived);
  if (filters.resolved) qs.set('resolved', filters.resolved);
  if (filters.date_from) qs.set('date_from', filters.date_from);
  if (filters.date_to) qs.set('date_to', filters.date_to);
  if (filters.sort_by) qs.set('sort_by', filters.sort_by);
  if (filters.sort_dir) qs.set('sort_dir', filters.sort_dir);
  if (filters.per_page != null) qs.set('per_page', String(filters.per_page));
  if (filters.page != null) qs.set('page', String(filters.page));
  return qs.toString();
}

/** GET /api/v1/logs — listado paginado con filtros. */
export async function fetchLogs(filters: LogsFilters = {}): Promise<PaginatedResponse<Log>> {
  const qs = buildLogsQuery(filters);
  const path = qs === '' ? 'logs' : `logs?${qs}`;
  return apiGetJson<PaginatedResponse<Log>>(path);
}

/** GET /api/v1/logs/{id} — detalle + id del ArchivedLog asociado si existe. */
export async function fetchLog(id: number): Promise<LogDetailResponse> {
  return apiGetJson<LogDetailResponse>(`logs/${id}`);
}

/**
 * POST /api/v1/logs/{id}/archive — idempotente. Si ya estaba archivado, 200;
 * si lo archiva ahora, 201.
 */
export async function archiveLog(id: number): Promise<ArchiveLogResponse> {
  return apiFetchJson<ArchiveLogResponse>(`logs/${id}/archive`, { method: 'POST', body: {} });
}

/** PATCH /api/v1/logs/{id}/resolve — marca el log como resuelto. */
export async function resolveLog(id: number): Promise<ResolveLogResponse> {
  return apiFetchJson<ResolveLogResponse>(`logs/${id}/resolve`, { method: 'PATCH', body: {} });
}

/**
 * URL absoluta + token Bearer para abrir el stream SSE `/api/v1/logs/stream`.
 * Úsalo si algún día se usa `EventSourcePolyfill` (el nativo no soporta Authorization).
 */
export async function getLogsStreamEndpoint(): Promise<{ url: string; token: string | null }> {
  const [url, token] = [buildApiUrl('logs/stream'), await getBearerToken()];
  return { url, token };
}

/**
 * Parsea un frame SSE devuelto por `/api/v1/logs/stream`.
 * El backend emite un único evento `event: logs\ndata: <json>\n\n` y cierra la conexión,
 * así que lo tratamos como un fetch puntual en lugar de una conexión persistente.
 */
function parseLogsStreamFrame(text: string): LogStreamPayload {
  const dataLines: string[] = [];
  for (const rawLine of text.split(/\r?\n/)) {
    if (rawLine.startsWith('data:')) {
      dataLines.push(rawLine.slice(5).replace(/^\s/, ''));
    }
  }
  if (dataLines.length === 0) {
    return [];
  }
  const json = dataLines.join('\n');
  const parsed: unknown = JSON.parse(json);
  return Array.isArray(parsed) ? (parsed as LogStreamPayload) : [];
}

/**
 * GET /api/v1/logs/stream — consume el frame SSE como si fuese un fetch puntual.
 * Pensado para polling ligero desde {@link ../hooks/useLogStream}. Soporta `AbortSignal`
 * para cancelar al desmontar.
 */
export async function fetchLogsStream(signal?: AbortSignal): Promise<LogStreamPayload> {
  const url = buildApiUrl('logs/stream');
  const headers: Record<string, string> = { Accept: 'text/event-stream' };
  await appendBearerAuthorization(headers);

  const response = await fetch(url, { method: 'GET', headers, signal });
  if (!response.ok) {
    if (response.status === 401) {
      triggerSignIn();
    }
    throw new ApiHttpError(response.statusText || `HTTP ${response.status}`, response.status);
  }

  const text = await response.text();
  return parseLogsStreamFrame(text);
}
