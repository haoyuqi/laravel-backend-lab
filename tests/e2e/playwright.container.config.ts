import { defineConfig, devices } from '@playwright/test';

/**
 * Drives a container that is already running (for example the CI image smoke
 * test). Unlike the other configs this one never starts a local server.
 */
export default defineConfig({
  testDir: './container',
  outputDir: './.results/container-results',
  timeout: 30 * 1000,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list']],
  use: {
    baseURL: process.env.APP_URL || 'http://127.0.0.1:8080',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
