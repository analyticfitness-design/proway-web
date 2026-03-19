import { test, expect } from '@playwright/test';

test.describe('Static Pages Load', () => {
  const pages = [
    { url: '/login.html', title: /ProWay/ },
    { url: '/portal.html', title: /Portal|ProWay/ },
    { url: '/admin.html', title: /Admin|ProWay/ },
    { url: '/facturas.html', title: /Facturas|ProWay/ },
    { url: '/perfil.html', title: /Perfil|ProWay/ },
  ];

  for (const { url, title } of pages) {
    test(`${url} returns 200 and has title`, async ({ page }) => {
      const response = await page.goto(url);
      expect(response?.status()).toBe(200);
      await expect(page).toHaveTitle(title);
    });
  }

  test('login page has form elements', async ({ page }) => {
    await page.goto('/login.html');
    // Input de email/usuario
    const emailInput = page.locator('input[type="email"], input[name="email"], input[name="username"]').first();
    await expect(emailInput).toBeVisible();
    // Input de password
    await expect(page.locator('input[type="password"]')).toBeVisible();
    // Botón submit
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('portal page has HTMX containers', async ({ page }) => {
    await page.goto('/portal.html');
    // Verificar que existen los contenedores HTMX
    await expect(page.locator('#client-stats, [hx-get*="client-stats"]').first()).toBeAttached();
    await expect(page.locator('[hx-get*="project-list"]').first()).toBeAttached();
  });

  test('admin page loads', async ({ page }) => {
    await page.goto('/admin.html');
    const status = (await page.goto('/admin.html'))?.status();
    expect(status).toBe(200);
  });
});
