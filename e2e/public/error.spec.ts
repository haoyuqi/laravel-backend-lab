import { test, expect } from '@playwright/test';

test.describe('Public Error Page (FR-002, AC-001)', () => {
  test('displays No Message rendered by Vue component', async ({ page }) => {
    await page.goto('/error');

    // Assert that the page rendered "No Message"
    await expect(page.getByText('No Message')).toBeVisible();
  });
});
