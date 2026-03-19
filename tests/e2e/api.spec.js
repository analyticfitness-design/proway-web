import { test, expect } from '@playwright/test';

test.describe('API Health', () => {
  test('API login endpoint responds (rejects bad creds)', async ({ request }) => {
    const response = await request.post('/api/v1/auth/login', {
      data: { email: 'test@test.com', password: 'wrong' },
      headers: { 'Content-Type': 'application/json' },
    });
    // Debe responder 401 (no 500 ni timeout)
    expect([401, 422]).toContain(response.status());
    const body = await response.json();
    expect(body.success).toBe(false);
    expect(body.error).toBeDefined();
  });

  test('API 404 for unknown routes', async ({ request }) => {
    const response = await request.get('/api/v1/nonexistent');
    expect(response.status()).toBe(404);
    const body = await response.json();
    expect(body.success).toBe(false);
  });

  test('API protected route returns 401 without auth', async ({ request }) => {
    const response = await request.get('/api/v1/auth/me');
    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body.success).toBe(false);
    expect(body.error.code).toBe('UNAUTHENTICATED');
  });
});
