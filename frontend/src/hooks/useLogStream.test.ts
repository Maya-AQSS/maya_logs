import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { renderHook, act } from '@testing-library/react';

vi.mock('../api/logs', () => ({
  fetchLogsStream: vi.fn(),
}));

import { fetchLogsStream } from '../api/logs';
import { useLogStream } from './useLogStream';

const mockFetchLogsStream = vi.mocked(fetchLogsStream);

describe('useLogStream', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    mockFetchLogsStream.mockResolvedValue([]);
  });

  afterEach(() => {
    vi.clearAllMocks();
    vi.useRealTimers();
  });

  it('arranca en estado connecting y dispara fetchLogsStream al montar', async () => {
    // Promesa que nunca resuelve para capturar el estado inicial sin disparar el siguiente tick.
    mockFetchLogsStream.mockReturnValue(new Promise(() => {}));

    const { result } = renderHook(() => useLogStream());

    expect(result.current.status).toBe('connecting');
    expect(mockFetchLogsStream).toHaveBeenCalledTimes(1);
  });

  it('pasa a status="open" tras una respuesta exitosa', async () => {
    mockFetchLogsStream.mockResolvedValue([{ id: 1, severity: 'high' } as any]);

    const { result } = renderHook(() => useLogStream());

    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(result.current.status).toBe('open');
    expect(result.current.payload).toEqual([{ id: 1, severity: 'high' }]);
    expect(result.current.error).toBeNull();
  });

  it('pasa a status="error" y guarda el mensaje cuando fetchLogsStream falla', async () => {
    mockFetchLogsStream.mockRejectedValue(new Error('network down'));

    const { result } = renderHook(() => useLogStream());

    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(result.current.status).toBe('error');
    expect(result.current.error).toBe('network down');
    expect(result.current.payload).toBeNull();
  });

  it('ignora errores cuyo mensaje contiene "aborted"', async () => {
    mockFetchLogsStream.mockRejectedValue(new Error('The operation was aborted'));

    const { result } = renderHook(() => useLogStream());

    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(result.current.error).toBeNull();
    expect(result.current.status).not.toBe('error');
  });

  it('hace polling con el intervalMs especificado', async () => {
    mockFetchLogsStream.mockResolvedValue([]);

    renderHook(() => useLogStream({ intervalMs: 3000 }));

    // Primer fetch en mount
    await act(async () => { await vi.advanceTimersByTimeAsync(0); });
    expect(mockFetchLogsStream).toHaveBeenCalledTimes(1);

    // Segundo fetch tras intervalMs
    await act(async () => { await vi.advanceTimersByTimeAsync(3000); });
    expect(mockFetchLogsStream).toHaveBeenCalledTimes(2);

    // Tercero
    await act(async () => { await vi.advanceTimersByTimeAsync(3000); });
    expect(mockFetchLogsStream).toHaveBeenCalledTimes(3);
  });

  it('aplica un mínimo de 1000ms entre ticks incluso si intervalMs es menor', async () => {
    mockFetchLogsStream.mockResolvedValue([]);

    renderHook(() => useLogStream({ intervalMs: 100 }));

    await act(async () => { await vi.advanceTimersByTimeAsync(0); });
    expect(mockFetchLogsStream).toHaveBeenCalledTimes(1);

    // No re-fetch antes de 1000ms
    await act(async () => { await vi.advanceTimersByTimeAsync(500); });
    expect(mockFetchLogsStream).toHaveBeenCalledTimes(1);

    // Sí re-fetch tras 1000ms (el mínimo)
    await act(async () => { await vi.advanceTimersByTimeAsync(500); });
    expect(mockFetchLogsStream).toHaveBeenCalledTimes(2);
  });

  it('cuando enabled=false no hace fetch y pasa a status="closed"', async () => {
    const { result } = renderHook(() => useLogStream({ enabled: false }));

    await act(async () => { await vi.advanceTimersByTimeAsync(0); });

    expect(mockFetchLogsStream).not.toHaveBeenCalled();
    expect(result.current.status).toBe('closed');
  });

  it('reconnect() fuerza un nuevo fetch inmediato', async () => {
    mockFetchLogsStream.mockResolvedValue([]);

    const { result } = renderHook(() => useLogStream({ intervalMs: 60_000 }));

    await act(async () => { await vi.advanceTimersByTimeAsync(0); });
    expect(mockFetchLogsStream).toHaveBeenCalledTimes(1);

    await act(async () => {
      result.current.reconnect();
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(mockFetchLogsStream).toHaveBeenCalledTimes(2);
  });

  it('al desmontar deja de hacer polling (cleanup)', async () => {
    mockFetchLogsStream.mockResolvedValue([]);

    const { unmount } = renderHook(() => useLogStream({ intervalMs: 1000 }));

    await act(async () => { await vi.advanceTimersByTimeAsync(0); });
    expect(mockFetchLogsStream).toHaveBeenCalledTimes(1);

    unmount();

    await act(async () => { await vi.advanceTimersByTimeAsync(5000); });
    // Sin nuevos fetches tras desmontar
    expect(mockFetchLogsStream).toHaveBeenCalledTimes(1);
  });

  it('recupera tras un error: la próxima respuesta exitosa vuelve a "open"', async () => {
    mockFetchLogsStream
      .mockRejectedValueOnce(new Error('boom'))
      .mockResolvedValueOnce([{ id: 7, severity: 'low' } as any]);

    const { result } = renderHook(() => useLogStream({ intervalMs: 1000 }));

    await act(async () => { await vi.advanceTimersByTimeAsync(0); });
    expect(result.current.status).toBe('error');
    expect(result.current.error).toBe('boom');

    // Avanzar el timer del polling + dejar que la promesa exitosa se resuelva
    await act(async () => { await vi.advanceTimersByTimeAsync(1000); });
    await act(async () => { await vi.advanceTimersByTimeAsync(0); });

    expect(result.current.status).toBe('open');
    expect(result.current.payload).toEqual([{ id: 7, severity: 'low' }]);
    expect(result.current.error).toBeNull();
  });
});
