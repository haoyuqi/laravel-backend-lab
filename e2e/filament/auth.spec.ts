import { test, expect } from '@playwright/test';

// Ensure anonymous/guest session to test redirection and login validation
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Filament Authentication & Access Control', () => {
  test('guest visiting /admin is redirected to /admin/login (FR-003)', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin\/login/);
  });

  test('guest visiting /admin/visitors is redirected to /admin/login (FR-003)', async ({ page }) => {
    await page.goto('/admin/visitors');
    await expect(page).toHaveURL(/\/admin\/login/);
  });

  test('guest visiting /admin/black-lists is redirected to /admin/login (FR-003)', async ({ page }) => {
    await page.goto('/admin/black-lists');
    await expect(page).toHaveURL(/\/admin\/login/);
  });

  test('submitting invalid credentials stays on login page and shows validation error (FR-004, AC-002)', async ({ page }) => {
    await page.goto('/admin/login');

    await page.locator('input[type="email"]').fill('admin@example.com');
    await page.locator('input[type="password"]').fill('invalid-password');
    await page.locator('button[type="submit"]').click();

    // Verify still on login page
    await expect(page).toHaveURL(/\/admin\/login/);

    // Verify error notification or inline error exists
    const errorIndicator = page.locator('.fi-fo-field-wrp-error-message, [role="alert"], .text-danger-600, .fi-modal-content');
    await expect(errorIndicator.first()).toBeVisible({ timeout: 5000 });
  });
});
