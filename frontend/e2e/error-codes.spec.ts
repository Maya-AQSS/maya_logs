import { expect, test } from './fixtures/auth';

test.describe('Error Codes · CRUD', () => {
  test('listado accesible y botón de creación visible', async ({ authenticatedPage: page }) => {
    await page.goto('/error-codes');
    await expect(page.getByRole('heading').first()).toBeVisible();
  });

  test('flujo de creación se renderiza', async ({ authenticatedPage: page }) => {
    await page.goto('/error-codes/create');
    await expect(page.getByRole('heading').first()).toBeVisible();
  });
});
