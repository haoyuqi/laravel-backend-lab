import { test, expect } from '@playwright/test';

test.describe('Public Home Page (FR-002, AC-001)', () => {
  test('displays Hello World rendered by Vue component', async ({ page }) => {
    await page.goto('/');

    // Assert that the page rendered "Hello World"
    await expect(page.getByText('Hello World')).toBeVisible();
  });
});
