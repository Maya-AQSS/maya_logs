import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Mockeamos `./http` (wrapper local sobre `createApiClient` de
 * `@maya/shared-auth-react`). La auth Bearer la inyecta el cliente real;
 * estos tests verifican que `logs.ts` construye URLs correctas y delega
 * al fetcher esperado.
 */
vi.mock('./http', () => {
  class ApiHttpError extends Error {
    status: number;
    constructor(message: string, status: number) {
      super(message);
      this.name = 'ApiHttpError';
      this.status = status;
    }
  }

  return {
    ApiHttpError,
    apiGetJson: vi.fn(),
    apiFetchJson: vi.fn(),
    buildApiUrl: vi.fn((path: string) => `http://logs-api.test/api/v1/${path}`),
    getBearerToken: vi.fn(async () => 'tok-abc'),
  };
});

vi.mock('../auth/oidcAdapter', () => ({
  appendBearerAuthorization: vi.fn(async (headers: Record<string, string>) => {
    headers.Authorization = 'Bearer tok-abc';
  }),
  triggerSignIn: vi.fn(),
}));

import {
  archiveLog,
  fetchLog,
  fetchLogs,
  fetchLogsStream,
  getLogsStreamEndpoint,
  resolveLog,
  type LogsFilters,
} from './logs';
import { ApiHttpError, apiFetchJson, apiGetJson, buildApiUrl } from './http';
import { appendBearerAuthorization, triggerSignIn } from '../auth/oidcAdapter';

describe('logs API', () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  // ─── fetchLogs ──────────────────────────────────────────────────
  describe('fetchLogs', () => {
    it('GET /logs sin query si no hay filtros', async () => {
      vi.mocked(apiGetJson).mockResolvedValue({ data: [], total: 0 } as any);

      await fetchLogs();

      expect(apiGetJson).toHaveBeenCalledWith('logs');
    });

    it('serializa filtro search como query param', async () => {
      vi.mocked(apiGetJson).mockResolvedValue({ data: [] } as any);

      await fetchLogs({ search: 'foo bar' });

      expect(apiGetJson).toHaveBeenCalledWith('logs?search=foo+bar');
    });

    it('serializa array severity como CSV', async () => {
      vi.mocked(apiGetJson).mockResolvedValue({ data: [] } as any);

      await fetchLogs({ severity: ['critical', 'high'] });

      expect(apiGetJson).toHaveBeenCalledWith('logs?severity=critical%2Chigh');
    });

    it('ignora severity si es array vacío', async () => {
      vi.mocked(apiGetJson).mockResolvedValue({ data: [] } as any);

      await fetchLogs({ severity: [] });

      expect(apiGetJson).toHaveBeenCalledWith('logs');
    });

    it('combina varios filtros en la query string', async () => {
      vi.mocked(apiGetJson).mockResolvedValue({ data: [] } as any);

      const filters: LogsFilters = {
        search: 'err',
        severity: ['high'],
        application_id: 42,
        resolved: 'only',
        per_page: 25,
        page: 2,
      };
      await fetchLogs(filters);

      const call = vi.mocked(apiGetJson).mock.calls[0]![0] as string;
      // El orden depende de URLSearchParams; verificamos cada par individualmente.
      expect(call.startsWith('logs?')).toBe(true);
      expect(call).toContain('search=err');
      expect(call).toContain('severity=high');
      expect(call).toContain('application_id=42');
      expect(call).toContain('resolved=only');
      expect(call).toContain('per_page=25');
      expect(call).toContain('page=2');
    });

    it('application_id=0 sí se incluye (0 != null)', async () => {
      vi.mocked(apiGetJson).mockResolvedValue({ data: [] } as any);

      await fetchLogs({ application_id: 0 });

      expect(apiGetJson).toHaveBeenCalledWith('logs?application_id=0');
    });

    it('per_page=null y page=null no se serializan', async () => {
      vi.mocked(apiGetJson).mockResolvedValue({ data: [] } as any);

      await fetchLogs({ per_page: null, page: null });

      expect(apiGetJson).toHaveBeenCalledWith('logs');
    });

    it('sort_by y sort_dir se serializan correctamente', async () => {
      vi.mocked(apiGetJson).mockResolvedValue({ data: [] } as any);

      await fetchLogs({ sort_by: 'severity', sort_dir: 'desc' });

      const call = vi.mocked(apiGetJson).mock.calls[0]![0] as string;
      expect(call).toContain('sort_by=severity');
      expect(call).toContain('sort_dir=desc');
    });

    it('date_from / date_to se pasan tal cual', async () => {
      vi.mocked(apiGetJson).mockResolvedValue({ data: [] } as any);

      await fetchLogs({ date_from: '2026-01-01', date_to: '2026-12-31' });

      const call = vi.mocked(apiGetJson).mock.calls[0]![0] as string;
      expect(call).toContain('date_from=2026-01-01');
      expect(call).toContain('date_to=2026-12-31');
    });

    it('archived=only se serializa', async () => {
      vi.mocked(apiGetJson).mockResolvedValue({ data: [] } as any);

      await fetchLogs({ archived: 'only' });

      expect(apiGetJson).toHaveBeenCalledWith('logs?archived=only');
    });

    it('propaga la respuesta paginada del fetcher', async () => {
      const expected = { data: [{ id: 1 } as any], total: 1, current_page: 1, last_page: 1, per_page: 25 };
      vi.mocked(apiGetJson).mockResolvedValue(expected as any);

      const result = await fetchLogs();

      expect(result).toBe(expected);
    });
  });

  // ─── fetchLog ───────────────────────────────────────────────────
  describe('fetchLog', () => {
    it('GET /logs/{id} con id numérico', async () => {
      vi.mocked(apiGetJson).mockResolvedValue({ data: { id: 42 }, meta: { archived_log_id: null } });

      await fetchLog(42);

      expect(apiGetJson).toHaveBeenCalledWith('logs/42');
    });

    it('propaga payload con data + meta.archived_log_id', async () => {
      const payload = { data: { id: 7 } as any, meta: { archived_log_id: 99 } };
      vi.mocked(apiGetJson).mockResolvedValue(payload);

      const result = await fetchLog(7);

      expect(result).toBe(payload);
      expect(result.meta.archived_log_id).toBe(99);
    });
  });

  // ─── archiveLog ─────────────────────────────────────────────────
  describe('archiveLog', () => {
    it('POST /logs/{id}/archive con body vacío', async () => {
      vi.mocked(apiFetchJson).mockResolvedValue({ data: { archived_log_id: 7 }, meta: { already_archived: false } });

      await archiveLog(42);

      expect(apiFetchJson).toHaveBeenCalledWith('logs/42/archive', { method: 'POST', body: {} });
    });

    it('expone already_archived=true cuando el backend lo marca', async () => {
      vi.mocked(apiFetchJson).mockResolvedValue({
        data: { archived_log_id: 7 },
        meta: { already_archived: true },
      });

      const result = await archiveLog(42);

      expect(result.meta.already_archived).toBe(true);
    });
  });

  // ─── resolveLog ─────────────────────────────────────────────────
  describe('resolveLog', () => {
    it('PATCH /logs/{id}/resolve con body vacío', async () => {
      vi.mocked(apiFetchJson).mockResolvedValue({ data: { id: 42, resolved: true } });

      await resolveLog(42);

      expect(apiFetchJson).toHaveBeenCalledWith('logs/42/resolve', { method: 'PATCH', body: {} });
    });

    it('propaga la respuesta del backend', async () => {
      const payload = { data: { id: 42, resolved: true as const } };
      vi.mocked(apiFetchJson).mockResolvedValue(payload);

      const result = await resolveLog(42);

      expect(result).toBe(payload);
    });
  });

  // ─── getLogsStreamEndpoint ──────────────────────────────────────
  describe('getLogsStreamEndpoint', () => {
    it('devuelve { url, token } usando buildApiUrl y getBearerToken', async () => {
      const result = await getLogsStreamEndpoint();

      expect(buildApiUrl).toHaveBeenCalledWith('logs/stream');
      expect(result.url).toBe('http://logs-api.test/api/v1/logs/stream');
      expect(result.token).toBe('tok-abc');
    });
  });

  // ─── fetchLogsStream ────────────────────────────────────────────
  describe('fetchLogsStream', () => {
    const originalFetch = global.fetch;

    beforeEach(() => {
      global.fetch = vi.fn();
    });

    afterEach(() => {
      global.fetch = originalFetch;
    });

    it('GET con header Accept: text/event-stream y Bearer (vía oidcAdapter)', async () => {
      vi.mocked(global.fetch).mockResolvedValue({
        ok: true,
        text: async () => 'event: logs\ndata: []\n\n',
      } as any);

      await fetchLogsStream();

      expect(appendBearerAuthorization).toHaveBeenCalled();
      const [url, init] = vi.mocked(global.fetch).mock.calls[0]!;
      expect(url).toBe('http://logs-api.test/api/v1/logs/stream');
      expect((init as RequestInit).method).toBe('GET');
      expect((init as RequestInit).headers).toMatchObject({
        Accept: 'text/event-stream',
        Authorization: 'Bearer tok-abc',
      });
    });

    it('parsea un frame SSE con un único item JSON', async () => {
      vi.mocked(global.fetch).mockResolvedValue({
        ok: true,
        text: async () => 'event: logs\ndata: [{"id":1,"severity":"high","message":"boom"}]\n\n',
      } as any);

      const result = await fetchLogsStream();

      expect(result).toEqual([{ id: 1, severity: 'high', message: 'boom' }]);
    });

    it('devuelve [] si no hay líneas data:', async () => {
      vi.mocked(global.fetch).mockResolvedValue({
        ok: true,
        text: async () => 'event: keep-alive\n\n',
      } as any);

      const result = await fetchLogsStream();

      expect(result).toEqual([]);
    });

    it('devuelve [] si el JSON parseado no es un array', async () => {
      vi.mocked(global.fetch).mockResolvedValue({
        ok: true,
        text: async () => 'data: {"unexpected":"shape"}\n\n',
      } as any);

      const result = await fetchLogsStream();

      expect(result).toEqual([]);
    });

    it('soporta líneas data: multi-línea uniendo con \\n', async () => {
      vi.mocked(global.fetch).mockResolvedValue({
        ok: true,
        text: async () => 'data: [\ndata: {"id":1}\ndata: ]\n\n',
      } as any);

      const result = await fetchLogsStream();

      expect(result).toEqual([{ id: 1 }]);
    });

    it('lanza ApiHttpError en respuesta no-ok 500', async () => {
      vi.mocked(global.fetch).mockResolvedValue({
        ok: false,
        status: 500,
        statusText: 'Internal Server Error',
        text: async () => '',
      } as any);

      await expect(fetchLogsStream()).rejects.toBeInstanceOf(ApiHttpError);
      expect(triggerSignIn).not.toHaveBeenCalled();
    });

    it('en 401 dispara triggerSignIn antes de throw', async () => {
      vi.mocked(global.fetch).mockResolvedValue({
        ok: false,
        status: 401,
        statusText: 'Unauthorized',
        text: async () => '',
      } as any);

      await expect(fetchLogsStream()).rejects.toBeInstanceOf(ApiHttpError);
      expect(triggerSignIn).toHaveBeenCalledTimes(1);
    });

    it('propaga AbortSignal al fetch', async () => {
      const controller = new AbortController();
      vi.mocked(global.fetch).mockResolvedValue({
        ok: true,
        text: async () => 'data: []\n\n',
      } as any);

      await fetchLogsStream(controller.signal);

      const init = vi.mocked(global.fetch).mock.calls[0]![1] as RequestInit;
      expect(init.signal).toBe(controller.signal);
    });
  });
});
