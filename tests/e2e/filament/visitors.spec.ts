import { test, expect } from '@playwright/test';

test.describe('Filament Visitors Resource (FR-007, AC-003)', () => {
  test('authenticated admin can view visitors table and records', async ({ page }) => {
    await page.goto('/admin/visitors');
    await expect(page).toHaveURL(/\/admin\/visitors$/);

    // Verify page header
    await expect(page.getByRole('heading', { name: '访客列表' })).toBeVisible();

    // Verify seeded visitor record (127.0.0.1) exists in the table
    await expect(page.getByText('127.0.0.1').first()).toBeVisible();

    // Verify table column headers
    await expect(page.getByRole('columnheader', { name: '今日访问量' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: '历史访问量' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: '黑名单' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: '首次访问' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: '最后访问' })).toBeVisible();
  });
});
