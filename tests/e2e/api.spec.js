import { test, expect } from '@playwright/test';

test.describe('API Health', () => {
  test('API login endpoint rejects missing credentials with 422', async ({ request }) => {
    // Validation fires before any DB query, so this works even without DB connectivity
    const response = await request.post('/api/auth/login.php', {
      data: {},
      headers: { 'Content-Type': 'application/json' },
    });
    expect(response.status()).toBe(422);
    const body = await response.json();
    expect(body.success).toBe(false);
    expect(body.error).toBeDefined();
  });

  test('API login rejects wrong HTTP method with 405', async ({ request }) => {
    // Method check fires before DB query
    const response = await request.get('/api/auth/login.php');
    expect(response.status()).toBe(405);
    const body = await response.json();
    expect(body.success).toBe(false);
  });

  test('API protected route returns 401 without auth', async ({ request }) => {
    // Token check fires before DB query (token is null → 401 immediately)
    const response = await request.get('/api/auth/me.php');
    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body.success).toBe(false);
    expect(body.error).toBeDefined();
  });

  test('API login returns JSON content-type for validation errors', async ({ request }) => {
    // Validation fires before any DB query — response must be JSON, not HTML
    const response = await request.post('/api/auth/login.php', {
      data: { email: '', password: '' },
      headers: { 'Content-Type': 'application/json' },
    });
    const contentType = response.headers()['content-type'] ?? '';
    expect(contentType).toContain('application/json');
    expect(response.status()).toBe(422);
  });
});
