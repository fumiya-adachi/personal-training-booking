import { test, expect } from '@playwright/test';

test('管理者の予約一覧はJSON APIで取得し、絞り込み・履歴復元・再試行できる', async ({ page, context }) => {
  await context.request.get('/login');
  const cookie = (await context.cookies()).find(item => item.name === 'XSRF-TOKEN')!;
  const login = await context.request.post('/login', {
    headers: { 'X-XSRF-TOKEN': decodeURIComponent(cookie.value), Accept: 'application/json' },
    data: { email: 'admin@example.com', password: 'Password123!' },
    maxRedirects: 0,
  });
  expect(login.status()).toBe(302);

  const initial = page.waitForResponse(response => response.url().includes('/api/admin/reservations'));
  await page.goto('/admin/reservations');
  expect((await initial).headers()['content-type']).toContain('application/json');
  await expect(page.getByText('予約一覧を読み込み中…')).toBeHidden();

  let pageRequests = 0;
  page.on('request', request => {
    if (new URL(request.url()).pathname === '/admin/reservations') pageRequests++;
  });
  await page.getByLabel('ステータス', { exact: true }).selectOption('cancelled');
  const filtered = page.waitForResponse(response => response.url().includes('/api/admin/reservations?status=cancelled'));
  await page.getByRole('button', { name: '絞り込む', exact: true }).click();
  expect((await filtered).ok()).toBe(true);
  await expect(page).toHaveURL(/status=cancelled/);
  await expect(page.getByText('予約一覧を読み込み中…')).toBeHidden();
  expect(pageRequests).toBe(0);

  await page.getByRole('button', { name: '絞り込みを解除' }).click();
  await expect(page).toHaveURL(/\/admin\/reservations$/);
  await page.goBack();
  await expect(page.getByLabel('ステータス', { exact: true })).toHaveValue('cancelled');
  await expect(page.getByText('予約一覧を読み込み中…')).toBeHidden();

  await page.route('**/api/admin/reservations*', route => route.fulfill({
    status: 500, contentType: 'application/json', body: '{"message":"test failure"}',
  }), { times: 1 });
  await page.getByRole('button', { name: '絞り込む', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('予約一覧を取得できませんでした');
  await page.getByRole('button', { name: '再試行', exact: true }).click();
  await expect(page.getByRole('alert')).toBeHidden();
  await expect(page.getByText('予約一覧を読み込み中…')).toBeHidden();
});
