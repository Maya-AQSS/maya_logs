import { describe, expect, it } from 'vitest';
import { archivedLogEditSchema, emptyArchivedLogEdit } from './archivedLog';

describe('archivedLogEditSchema', () => {
  it('acepta un formulario válido con URL', () => {
    const result = archivedLogEditSchema.safeParse({
      description: 'Nota del incidente',
      url_tutorial: 'https://example.com/runbook',
    });

    expect(result.success).toBe(true);
  });

  it('acepta http:// además de https://', () => {
    const result = archivedLogEditSchema.safeParse({
      description: '',
      url_tutorial: 'http://internal.localhost/docs',
    });

    expect(result.success).toBe(true);
  });

  it('aplica defaults cuando faltan campos opcionales', () => {
    const result = archivedLogEditSchema.parse({});

    expect(result.description).toBe('');
    expect(result.url_tutorial).toBe('');
  });

  it('acepta url_tutorial vacío (significa "sin tutorial")', () => {
    const result = archivedLogEditSchema.safeParse({
      description: 'desc',
      url_tutorial: '',
    });

    expect(result.success).toBe(true);
  });

  it('rechaza url_tutorial sin protocolo http(s)', () => {
    const result = archivedLogEditSchema.safeParse({
      description: '',
      url_tutorial: 'example.com/foo',
    });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues.find((i) => i.path[0] === 'url_tutorial')?.message).toBe(
        'URL inválida',
      );
    }
  });

  it('rechaza URLs con protocolo no permitido (ftp://, javascript:)', () => {
    const ftpResult = archivedLogEditSchema.safeParse({ url_tutorial: 'ftp://server/x' });
    const jsResult = archivedLogEditSchema.safeParse({ url_tutorial: 'javascript:alert(1)' });

    expect(ftpResult.success).toBe(false);
    expect(jsResult.success).toBe(false);
  });

  it('acepta URL con mayúsculas en el protocolo (case-insensitive regex)', () => {
    const result = archivedLogEditSchema.safeParse({
      url_tutorial: 'HTTPS://example.com',
    });

    expect(result.success).toBe(true);
  });

  it('rechaza description que exceda 5000 chars', () => {
    const result = archivedLogEditSchema.safeParse({
      description: 'a'.repeat(5001),
      url_tutorial: '',
    });

    expect(result.success).toBe(false);
  });

  it('acepta description exactamente de 5000 chars (límite)', () => {
    const result = archivedLogEditSchema.safeParse({
      description: 'a'.repeat(5000),
      url_tutorial: '',
    });

    expect(result.success).toBe(true);
  });

  it('emptyArchivedLogEdit tiene la estructura esperada', () => {
    expect(emptyArchivedLogEdit).toEqual({
      description: '',
      url_tutorial: '',
    });
  });
});
