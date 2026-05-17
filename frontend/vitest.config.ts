import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['src/**/*.test.{ts,tsx}'],
    setupFiles: ['./vitest.setup.ts'],
    // Vitest 4: pool y workers top-level. Single fork mantiene la huella
    // de memoria controlada en contenedores con mem_limit ajustado.
    pool: 'forks',
    maxWorkers: 1,
    minWorkers: 1,
  },
});
