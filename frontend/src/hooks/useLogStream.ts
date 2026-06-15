import { useCallback, useEffect, useRef, useState } from 'react';
import { fetchLogsStream } from '../api/logs';
import type { LogStreamPayload } from '../types/logs';

export type LogStreamStatus = 'idle' | 'connecting' | 'open' | 'closed' | 'error';

export type UseLogStreamOptions = {
  /** Si `false`, el polling queda suspendido (p.ej. pestaña oculta). Por defecto `true`. */
  enabled?: boolean;
  /** Intervalo entre llamadas, en ms. Por defecto 5000. */
  intervalMs?: number;
};

export type UseLogStreamResult = {
  payload: LogStreamPayload | null;
  status: LogStreamStatus;
  error: string | null;
  /** Fuerza un refetch inmediato. */
  reconnect: () => void;
};

/**
 * Polling hook sobre el endpoint SSE `/api/v1/logs/stream`. El backend emite un único
 * frame por conexión, por lo que en lugar de mantener un `EventSource` abierto hacemos
 * fetches puntuales cada `intervalMs`. Ideal para la tabla de Logs y la Dashboard.
 */
export function useLogStream(options: UseLogStreamOptions = {}): UseLogStreamResult {
  const { enabled = true, intervalMs = 5000 } = options;

  const [payload, setPayload] = useState<LogStreamPayload | null>(null);
  const [status, setStatus] = useState<LogStreamStatus>('idle');
  const [error, setError] = useState<string | null>(null);

  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const mountedRef = useRef(true);
  const tickRef = useRef<() => void>(() => {});

  const clearTimer = useCallback(() => {
    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
  }, []);

  const tick = useCallback(async () => {
    if (!mountedRef.current) return;

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setStatus((prev) => (prev === 'open' ? 'open' : 'connecting'));

    try {
      const next = await fetchLogsStream(controller.signal);
      if (!mountedRef.current || controller.signal.aborted) return;
      setPayload(next);
      setError(null);
      setStatus('open');
    } catch (e) {
      if (!mountedRef.current || controller.signal.aborted) return;
      const message = e instanceof Error ? e.message : String(e);
      if (/aborted/i.test(message)) return;
      setError(message);
      setStatus('error');
    } finally {
      if (mountedRef.current && timerRef.current === null) {
        timerRef.current = setTimeout(
          () => {
            timerRef.current = null;
            tickRef.current();
          },
          Math.max(1000, intervalMs),
        );
      }
    }
  }, [intervalMs]);

  useEffect(() => {
    tickRef.current = tick;
  }, [tick]);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
      clearTimer();
      abortRef.current?.abort();
    };
  }, [clearTimer]);

  useEffect(() => {
    if (!enabled) {
      clearTimer();
      abortRef.current?.abort();
      setStatus('closed');
      return;
    }
    tick();
    return () => {
      clearTimer();
      abortRef.current?.abort();
    };
  }, [enabled, tick, clearTimer]);

  const reconnect = useCallback(() => {
    clearTimer();
    tick();
  }, [clearTimer, tick]);

  return { payload, status, error, reconnect };
}
