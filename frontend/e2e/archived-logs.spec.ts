import { expect, test } from './fixtures/auth';

test.describe('Archived Logs', () => {
  test('listado de archived renderiza', async ({ authenticatedPage: page }) => {
    await page.goto('/archived-logs');
    await expect(page.getByRole('heading').first()).toBeVisible();
  });
});
