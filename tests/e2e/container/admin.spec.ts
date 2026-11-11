import { test, expect } from '@playwright/test';

// Credentials are created by .github/scripts/container-smoke.sh in CI.
const adminEmail = process.env.CONTAINER_ADMIN_EMAIL || 'admin@example.com';
const adminPassword = process.env.CONTAINER_ADMIN_PASSWORD || 'ci-admin-password';

test('authenticated admin panel is reachable', async ({ page }) => {
  await page.goto('/admin/login');
  await expect(page).toHaveURL(/\/admin\/login/);

  await page.locator('input[type="email"]').fill(adminEmail);
  await page.locator('input[type="password"]').fill(adminPassword);
  await page.locator('button[type="submit"]').click();

  await page.waitForURL(/\/admin$/);
  await expect(page.locator('input[type="password"]')).toHaveCount(0);
});
