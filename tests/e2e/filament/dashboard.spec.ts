import { test, expect } from '@playwright/test';

test.describe('Filament Admin Dashboard (FR-006, AC-003)', () => {
  test('authenticated admin can view dashboard and stats widgets', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin$/);

    // Verify stats overview widget labels
    await expect(page.getByText('今日 PV')).toBeVisible();
    await expect(page.getByText('今日 UV')).toBeVisible();
    await expect(page.getByText('访客总数')).toBeVisible();
    await expect(page.getByText('黑名单 IP')).toBeVisible();
  });
});
