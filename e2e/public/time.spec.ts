import { test, expect } from '@playwright/test';

test.describe('Public Time Page (FR-002, AC-001)', () => {
  test('displays formatted time rendered by Vue TimeComponent', async ({ page }) => {
    await page.goto('/time');

    // Assert that TimeComponent table is visible
    await expect(page.locator('table')).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Time' })).toBeVisible();

    // Assert that at least one date-time string matching YYYY-MM-DD HH:mm is rendered
    const timeCell = page.locator('table tbody td').first();
    await expect(timeCell).toBeVisible();
    await expect(timeCell).toHaveText(/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/);
  });
});
