import { test, expect } from '@playwright/test';
import { randomBytes } from 'node:crypto';

test('blacklist creation is visible in read-only admin operation logs', async ({ page }) => {
  // Projects and retries share the fixture DB; each run needs a distinct IP.
  const generatedIp = `2001:db8:${randomBytes(12).toString('hex').match(/.{4}/g)!.join(':')}`;
  // PostgreSQL's inet type normalizes IPv6; submit canonical form on every DB.
  const ip = new URL(`http://[${generatedIp}]`).hostname.slice(1, -1);
  await page.goto('/admin/black-lists/create');
  await page.getByRole('textbox', { name: /^IP/ }).fill(ip);
  await page.locator('button[type="submit"]:visible').first().click();
  await expect(page).toHaveURL(/\/admin\/black-lists$/);

  await page.goto('/admin/admin-operation-logs');
  await expect(page.getByRole('heading', { name: '操作日志', exact: true })).toBeVisible();
  await expect(page.getByText(JSON.stringify({ ip, deleted: false }), { exact: true })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: '操作人' })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: '变更前' })).toBeVisible();
  await expect(page.getByRole('button', { name: '创建', exact: true })).toHaveCount(0);
  await expect(page.getByRole('button', { name: '删除', exact: true })).toHaveCount(0);
});
