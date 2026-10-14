import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

test('time updates arrive through a real Reverb WebSocket and survive reload', async ({ page }) => {
  for (let round = 0; round < 2; round++) {
    await page.goto('/time');
    await expect(page.locator('table tbody tr')).toHaveCount(1);
    await page.waitForFunction(() => {
      const echo = (window as any).Echo;
      return echo?.connector.pusher.channel('push-time')?.subscribed === true;
    });

    execFileSync('php', ['artisan', 'tinker', '--execute=event(new App\\Events\\PushTimeEvent);'], {
      env: process.env,
      stdio: 'pipe',
    });

    await expect(page.locator('table tbody tr')).toHaveCount(2);
    await expect(page.locator('table tbody td').last()).toHaveText(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
  }
});
