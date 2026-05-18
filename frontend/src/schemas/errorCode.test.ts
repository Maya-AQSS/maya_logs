import { describe, expect, it } from 'vitest';
import { errorCodeFormSchema, emptyErrorCodeForm } from './errorCode';

describe('errorCodeFormSchema', () => {
  it('acepta un formulario válido completo', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: 'E001',
      name: 'Error de prueba',
      file: 'app/Foo.php',
      line: '42',
      description: 'Algo pasó',
    });

    expect(result.success).toBe(true);
  });

  it('aplica defaults a campos opcionales omitidos', () => {
    const result = errorCodeFormSchema.parse({
      application_id: '1',
      code: 'E001',
      name: 'Test',
    });

    expect(result.file).toBe('');
    expect(result.line).toBe('');
    expect(result.description).toBe('');
  });

  it('rechaza application_id vacío con mensaje específico', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '',
      code: 'E001',
      name: 'Test',
    });

    expect(result.success).toBe(false);
    if (!result.success) {
      const msg = result.error.issues.find((i) => i.path[0] === 'application_id')?.message;
      expect(msg).toBe('Selecciona una aplicación');
    }
  });

  it('rechaza code vacío como "Requerido"', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: '',
      name: 'Test',
    });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues.find((i) => i.path[0] === 'code')?.message).toBe('Requerido');
    }
  });

  it('rechaza code que exceda 50 chars', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: 'X'.repeat(51),
      name: 'Test',
    });

    expect(result.success).toBe(false);
  });

  it('rechaza name vacío', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: 'E001',
      name: '',
    });

    expect(result.success).toBe(false);
  });

  it('rechaza name que exceda 200 chars', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: 'E001',
      name: 'A'.repeat(201),
    });

    expect(result.success).toBe(false);
  });

  it('acepta line vacío (significa "sin especificar")', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: 'E001',
      name: 'Test',
      line: '',
    });

    expect(result.success).toBe(true);
  });

  it('rechaza line no numérica con "Solo números enteros"', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: 'E001',
      name: 'Test',
      line: 'abc',
    });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues.find((i) => i.path[0] === 'line')?.message).toBe(
        'Solo números enteros',
      );
    }
  });

  it('rechaza line decimal', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: 'E001',
      name: 'Test',
      line: '3.14',
    });

    expect(result.success).toBe(false);
  });

  it('rechaza line negativa', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: 'E001',
      name: 'Test',
      line: '-5',
    });

    expect(result.success).toBe(false);
  });

  it('rechaza line=0 con "Mínimo 1"', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: 'E001',
      name: 'Test',
      line: '0',
    });

    expect(result.success).toBe(false);
    if (!result.success) {
      const msg = result.error.issues.find((i) => i.path[0] === 'line')?.message;
      expect(msg).toBe('Mínimo 1');
    }
  });

  it('acepta line=1 (límite inferior)', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: 'E001',
      name: 'Test',
      line: '1',
    });

    expect(result.success).toBe(true);
  });

  it('rechaza description que exceda 5000 chars', () => {
    const result = errorCodeFormSchema.safeParse({
      application_id: '1',
      code: 'E001',
      name: 'Test',
      description: 'a'.repeat(5001),
    });

    expect(result.success).toBe(false);
  });

  it('emptyErrorCodeForm tiene la estructura esperada', () => {
    expect(emptyErrorCodeForm).toEqual({
      application_id: '',
      code: '',
      name: '',
      file: '',
      line: '',
      description: '',
    });
  });
});
