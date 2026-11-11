import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './compose',
  outputDir: './.results/compose-results',
  workers: 1,
  timeout: 60_000,
  forbidOnly: !!process.env.CI,
  use: { baseURL: process.env.APP_URL, trace: 'retain-on-failure' },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
