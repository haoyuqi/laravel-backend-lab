import { test as setup, expect } from '@playwright/test';

const authFile = 'playwright/.auth/admin.json';

setup('authenticate as admin', async ({ page }) => {
  // Navigate to Filament login page
  await page.goto('/admin/login');
  await expect(page).toHaveURL(/\/admin\/login/);

  // Fill credentials
  await page.locator('input[type="email"]').fill('admin@example.com');
  await page.locator('input[type="password"]').fill('password');

  // Submit login form
  await page.locator('button[type="submit"]').click();

  // Wait for successful navigation to /admin
  await page.waitForURL(/\/admin$/);
  await expect(page).toHaveURL(/\/admin$/);

  // Save authenticated session storage state
  await page.context().storageState({ path: authFile });
});
