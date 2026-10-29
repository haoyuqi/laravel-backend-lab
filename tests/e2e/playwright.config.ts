import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, devices } from '@playwright/test';

const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

function getEnvValue(key: string, fallback: string = ''): string {
  if (process.env[key] !== undefined && process.env[key] !== '') {
    return process.env[key]!;
  }
  try {
    const envPath = path.resolve(rootDir, '.env');
    if (fs.existsSync(envPath)) {
      const content = fs.readFileSync(envPath, 'utf-8');
      const match = content.match(new RegExp(`^${key}=(.*)$`, 'm'));
      if (match) {
        return match[1].trim().replace(/^["']|["']$/g, '');
      }
    }
  } catch {}
  return fallback;
}

const dbConnection = getEnvValue('TEST_DB_CONNECTION', getEnvValue('DB_CONNECTION', 'pgsql'));
const dbHost = getEnvValue('TEST_DB_HOST', getEnvValue('DB_HOST', '127.0.0.1'));
const dbPort = getEnvValue('TEST_DB_PORT', getEnvValue('DB_PORT', '5432'));
const dbDatabase = getEnvValue('TEST_DB_DATABASE', getEnvValue('DB_DATABASE', 'web_test'));
const dbUsername = getEnvValue('TEST_DB_USERNAME', getEnvValue('DB_USERNAME', 'postgres'));
const dbPassword = getEnvValue('TEST_DB_PASSWORD', getEnvValue('DB_PASSWORD', 'postgres'));

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: '.',
  testIgnore: 'reverb/**',
  outputDir: './.results/test-results',
  timeout: 30 * 1000,
  expect: {
    timeout: 10 * 1000,
  },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: [
    ['html', { outputFolder: './.results/playwright-report', open: 'never' }],
    ['list'],
  ],
  use: {
    baseURL: process.env.APP_URL || 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
      use: {
        // Engine-only CI jobs must authenticate with the installed browser.
        browserName: (process.env.PLAYWRIGHT_SETUP_BROWSER || 'chromium') as 'chromium' | 'firefox' | 'webkit',
      },
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },
    {
      name: 'firefox',
      use: {
        ...devices['Desktop Firefox'],
        storageState: 'tests/e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },
    {
      name: 'webkit',
      use: {
        ...devices['Desktop Safari'],
        storageState: 'tests/e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },
    {
      name: 'Mobile Chrome',
      use: {
        ...devices['Pixel 7'],
        storageState: 'tests/e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },
    {
      name: 'Mobile Safari',
      use: {
        ...devices['iPhone 15'],
        storageState: 'tests/e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },
  ],

  webServer: {
    // Auto-reload filters environment overrides before starting PHP's server.
    command: 'php artisan serve --no-reload --host 127.0.0.1 --port 8000',
    cwd: rootDir,
    url: 'http://127.0.0.1:8000/up',
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
    env: {
      APP_ENV: 'testing',
      DB_CONNECTION: dbConnection,
      DB_HOST: dbHost,
      DB_PORT: dbPort,
      DB_DATABASE: dbDatabase,
      DB_USERNAME: dbUsername,
      DB_PASSWORD: dbPassword,
    },
  },
});
