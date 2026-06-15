import { expect, loginViaKeycloak, test } from './fixtures/auth';

test.describe('Autenticación Keycloak', () => {
  test('redirige al SSO cuando no hay sesión', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/realms|auth/);
  });

  test('login exitoso aterriza en el dashboard', async ({ page }) => {
    await loginViaKeycloak(page);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });
});
