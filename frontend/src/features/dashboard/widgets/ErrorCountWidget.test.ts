import { describe, expect, it } from 'vitest';
import { errorSignature, resolveUniqueErrorCount } from './errorCount';

describe('ErrorCountWidget', () => {
  describe('resolveUniqueErrorCount', () => {
    it('deduplica por app + error_code_id cuando existe', () => {
      const logs = [
        { id: 1, message: 'A', application: { id: 1, name: 'App' }, error_code: { id: 7, code: 'E', name: 'E' } },
        { id: 2, message: 'A', application: { id: 1, name: 'App' }, error_code: { id: 7, code: 'E', name: 'E' } },
        { id: 3, message: 'A', application: { id: 2, name: 'App2' }, error_code: { id: 7, code: 'E', name: 'E' } },
      ] as any;

      expect(resolveUniqueErrorCount(logs)).toBe(2);
    });

    it('si no hay error_code deduplica por app + mensaje normalizado', () => {
      const logs = [
        { id: 1, message: '  SQL ERROR  ', application: { id: 1, name: 'App' }, error_code: null },
        { id: 2, message: 'sql error', application: { id: 1, name: 'App' }, error_code: null },
        { id: 3, message: 'sql error', application: { id: 2, name: 'App2' }, error_code: null },
      ] as any;

      expect(resolveUniqueErrorCount(logs)).toBe(2);
    });
  });

  describe('errorSignature', () => {
    it('prioriza error_code_id sobre message', () => {
      const sig = errorSignature({
        id: 1,
        severity: 'high',
        message: 'msg',
        metadata: null,
        resolved: false,
        file: null,
        line: null,
        created_at: null,
        application: { id: 10, name: 'A' },
        error_code: { id: 20, code: 'X', name: 'X' },
      });

      expect(sig).toBe('10|ec:20');
    });
  });
});
