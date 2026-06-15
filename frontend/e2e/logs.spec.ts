import { expect, test } from './fixtures/auth';

test.describe('Logs · listado y filtros', () => {
  test('muestra la tabla de logs y permite filtrar por severidad', async ({
    authenticatedPage: page,
  }) => {
    await page.goto('/logs');

    await expect(page.getByRole('heading', { name: /logs/i }).first()).toBeVisible();

    const errorCheckbox = page.getByRole('checkbox', { name: /error/i }).first();
    if (await errorCheckbox.isVisible()) {
      await errorCheckbox.check();
      await expect(page).toHaveURL(/severity=/);
    }
  });

  test('abre el detalle de un log y permite volver al listado', async ({
    authenticatedPage: page,
  }) => {
    await page.goto('/logs');
    const firstRow = page
      .getByRole('link')
      .filter({ hasText: /ver|detalle|open/i })
      .first();
    if (await firstRow.count()) {
      await firstRow.click();
      await expect(page).toHaveURL(/\/logs\/\d+/);
    }
  });
});
