import { test, expect } from '@playwright/test';

test.describe('Filament Blacklist Resources (FR-007, AC-003)', () => {
  test('authenticated admin can view blacklist table and record', async ({ page }) => {
    await page.goto('/admin/black-lists');
    await expect(page).toHaveURL(/\/admin\/black-lists$/);

    // Verify page header
    await expect(page.getByRole('heading', { name: '黑名单列表' })).toBeVisible();

    // Verify seeded blacklist IP (127.0.0.99)
    await expect(page.getByText('127.0.0.99').first()).toBeVisible();

    // Verify column headers
    await expect(page.getByRole('columnheader', { name: '今日拦截量' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: '历史拦截量' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: '添加时间' })).toBeVisible();
  });

  test('authenticated admin can view blacklist logs table and record', async ({ page }) => {
    await page.goto('/admin/black-list-logs');
    await expect(page).toHaveURL(/\/admin\/black-list-logs$/);

    // Verify page header
    await expect(page.getByRole('heading', { name: '拦截日志' })).toBeVisible();

    // Verify seeded blacklist log details
    await expect(page.getByText('127.0.0.99').first()).toBeVisible();
    await expect(page.getByText('https://example.com/blocked-e2e').first()).toBeVisible();

    // Verify column headers
    await expect(page.getByRole('columnheader', { name: 'IP' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'URL' })).toBeVisible();
  });
});
