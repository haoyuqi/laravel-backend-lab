import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

test('compose supports authenticated admin access and real-time queue delivery', async ({ page }) => {
  await page.goto('/admin/login');
  await page.locator('input[type="email"]').fill('admin@example.com');
  await page.locator('input[type="password"]').fill(process.env.CONTAINER_ADMIN_PASSWORD!);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/\/admin$/);

  await page.goto('/time');
  await page.waitForFunction(() => {
    return (window as any).Echo?.connector.pusher.channel('push-time')?.subscribed === true;
  });
  const before = await page.locator('table tbody tr').count();
  // No manual dispatch: the real scheduler must publish through the queue.
  await expect.poll(() => page.locator('table tbody tr').count(), { timeout: 80_000 }).toBeGreaterThan(before);
  const afterScheduledEvent = await page.locator('table tbody tr').count();
  execFileSync('docker', [
    'compose', '--env-file', process.env.COMPOSE_SMOKE_ENV!,
    '-f', process.env.COMPOSE_SMOKE_FILE!, '-p', process.env.COMPOSE_SMOKE_PROJECT!,
    'exec', '-T', 'app', 'php', 'artisan', 'tinker',
    '--execute=event(new App\\Events\\PushTimeEvent);',
  ], { stdio: 'pipe', env: process.env });
  await expect.poll(() => page.locator('table tbody tr').count(), { timeout: 20_000 }).toBeGreaterThan(afterScheduledEvent);
});
