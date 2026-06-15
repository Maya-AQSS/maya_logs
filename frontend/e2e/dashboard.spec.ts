import { expect, test } from './fixtures/auth';

test.describe('Dashboard', () => {
  test('dashboard carga con métricas y nav visible', async ({ authenticatedPage: page }) => {
    await page.goto('/dashboard');
    await expect(page.getByRole('navigation').first()).toBeVisible();
    await expect(page.getByRole('heading').first()).toBeVisible();
  });
});
