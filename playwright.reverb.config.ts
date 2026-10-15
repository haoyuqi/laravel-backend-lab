import { randomBytes } from 'node:crypto';
import { defineConfig, devices } from '@playwright/test';

// Config also loads in workers; reuse the parent process's ephemeral secret.
process.env.REVERB_E2E_SECRET ??= randomBytes(32).toString('hex');
// Both servers and the event-dispatching test share ephemeral credentials.
const env = {
  ...process.env,
  APP_ENV: 'local',
  APP_URL: 'http://127.0.0.1:8000',
  DB_CONNECTION: 'sqlite',
  DB_URL: '',
  DB_DATABASE: ':memory:',
  SESSION_DRIVER: 'file',
  CACHE_STORE: 'file',
  QUEUE_CONNECTION: 'sync',
  BROADCAST_CONNECTION: 'reverb',
  TELESCOPE_ENABLED: 'false',
  DEBUGBAR_ENABLED: 'false',
  REVERB_APP_ID: 'reverb-e2e',
  REVERB_APP_KEY: 'reverb-e2e-public-key',
  REVERB_APP_SECRET: process.env.REVERB_E2E_SECRET,
  REVERB_HOST: '127.0.0.1',
  REVERB_PORT: '8080',
  REVERB_SCHEME: 'http',
  REVERB_SERVER_HOST: '0.0.0.0',
  REVERB_SERVER_PORT: '8080',
  REVERB_SCALING_ENABLED: 'false',
  REDIS_DB: '15',
  VITE_REVERB_APP_KEY: 'reverb-e2e-public-key',
  VITE_REVERB_HOST: '127.0.0.1',
  VITE_REVERB_PORT: '8080',
  VITE_REVERB_SCHEME: 'http',
};
Object.assign(process.env, env);

export default defineConfig({
  testDir: './e2e/reverb',
  workers: 1,
  timeout: 30_000,
  use: { baseURL: env.APP_URL, trace: 'retain-on-failure' },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: [
    {
      command: 'php artisan reverb:start',
      port: 8080,
      env,
      reuseExistingServer: false,
    },
    {
      command: 'npm run build && php artisan serve --host=0.0.0.0 --port=8000',
      url: `${env.APP_URL}/up`,
      env,
      timeout: 120_000,
      reuseExistingServer: false,
    },
  ],
});
