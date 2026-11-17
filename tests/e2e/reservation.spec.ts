import { test, expect, type BrowserContext } from '@playwright/test';

async function csrfHeaders(context: BrowserContext) {
  const cookie = (await context.cookies()).find((item) => item.name === 'XSRF-TOKEN');
  expect(cookie, 'Laravel CSRF cookie should be available').toBeTruthy();
  return { 'X-XSRF-TOKEN': decodeURIComponent(cookie!.value), Accept: 'application/json' };
}

async function register(context: BrowserContext, suffix: string) {
  await context.request.get('/register');
  const email = `e2e-${suffix}-${Date.now()}@example.com`;
  const response = await context.request.post('/register', {
    headers: await csrfHeaders(context),
    data: { name: '予約テスト', email, password: 'Password123!', password_confirmation: 'Password123!' },
    maxRedirects: 0,
  });
  expect(response.status()).toBe(302);
  return email;
}

test('公開カレンダーはデスクトップとモバイルで操作できる', async ({ page }) => {
  await page.goto('/');
  await expect(page.getByRole('heading', { name: /トレーニングを予約|トレーニングの予約|トレーニング予約/ })).toBeVisible();
  await expect(page.getByRole('button', { name: /予約可能$/ }).first()).toBeVisible();
  await expect(page.getByText('指名なし', { exact: true }).first()).toBeVisible();
  const available = page.getByRole('button', { name: /予約可能$/ }).first();
  await available.click();
  await expect(available).toHaveAttribute('aria-pressed', 'true');
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.getByRole('button', { name: /予約可能$/ }).first()).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('会員は予約を確定し、自分の予約を取り消せる', async ({ page, context }) => {
  await register(context, 'booking');
  await page.goto('/reservations/create');
  await page.getByRole('button', { name: /予約可能$/ }).first().click();
  await page.getByRole('button', { name: '予約内容を確認', exact: true }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();
  await dialog.getByRole('button', { name: '予約を確定する', exact: true }).click();
  await expect(page).toHaveURL(/\/reservations\/\d+$/);
  await expect(page.getByRole('heading', { name: 'ご予約が完了しました' })).toBeVisible();
  await expect(page.getByText(/FRM-/).first()).toBeVisible();
  await page.getByRole('button', { name: /予約をキャンセル/ }).first().click();
  const cancellation = page.getByRole('dialog');
  await expect(cancellation).toBeVisible();
  await cancellation.getByRole('button', { name: /キャンセルする/, exact: true }).click();
  await expect(page).toHaveURL(/\/reservations$/);
  await page.getByRole('tab', { name: /履歴・キャンセル/ }).click();
  await expect(page.getByText('キャンセル済み', { exact: true }).first()).toBeVisible();
});

test('クライアントは管理画面にアクセスできない', async ({ context }) => {
  await register(context, 'permissions');
  const response = await context.request.get('/admin/reservations');
  expect(response.status()).toBe(403);
});

test('MySQL上で競合する同時予約は一件だけ確定する', async ({ browser, baseURL }) => {
  const first = await browser.newContext({ baseURL });
  const second = await browser.newContext({ baseURL });
  try {
    await register(first, 'race-a');
    await register(second, 'race-b');
    const availability = await first.request.get('/availability?trainer_id=1');
    expect(availability.ok()).toBe(true);
    const calendar = await availability.json();
    let selected: { date: string; time: string } | undefined;
    for (const day of calendar.days) {
      const time = calendar.times.find((t: string) => calendar.slots[day.date]?.[t]?.status === 'available');
      if (time) { selected = { date: day.date, time }; break; }
    }
    expect(selected, 'At least one available slot is needed').toBeTruthy();
    const data = { ...selected, trainer_id: 1, notes: '同時予約の検証' };
    const results = await Promise.all([
      first.request.post('/reservations', { headers: await csrfHeaders(first), data, maxRedirects: 0 }),
      second.request.post('/reservations', { headers: await csrfHeaders(second), data, maxRedirects: 0 }),
    ]);
    expect(results.map((response) => response.status()).sort()).toEqual([302, 422]);
    const winner = results[0].status() === 302 ? first : second;
    const location = results.find((response) => response.status() === 302)!.headers().location;
    const reservationId = new URL(location, baseURL).pathname.match(/\/reservations\/(\d+)$/)?.[1];
    expect(reservationId, 'Successful booking redirects to its reservation').toBeTruthy();
    const cancellation = await winner.request.delete(`/reservations/${reservationId}`, {
      headers: await csrfHeaders(winner), maxRedirects: 0,
    });
    expect(cancellation.status()).toBe(302);
  } finally {
    await first.close();
    await second.close();
  }
});

test('管理者はトレーナー・週間勤務・休業日を画面から管理できる', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('メールアドレス', { exact: true }).fill('admin@example.com');
  await page.getByLabel('パスワード', { exact: true }).fill('Password123!');
  await page.getByRole('button', { name: 'ログイン', exact: true }).click();
  await expect(page).toHaveURL(/\/admin\/reservations$/);
  await page.getByLabel('ステータス', { exact: true }).selectOption('confirmed');
  await page.getByRole('button', { name: '絞り込む', exact: true }).click();
  await expect(page).toHaveURL(/status=confirmed/);
  await page.getByRole('link', { name: 'トレーナー', exact: true }).click();
  await page.getByRole('button', { name: 'トレーナーを追加', exact: true }).click();
  const editor = page.getByRole('dialog');
  const trainerName = `E2E-TRAINER-${Date.now()}`;
  await editor.getByLabel(/名前/).fill(trainerName);
  await editor.getByLabel(/得意分野/).fill('動作確認');
  await editor.getByLabel('予約を受け付ける', { exact: true }).uncheck();
  await editor.getByRole('button', { name: '保存する', exact: true }).click();
  await expect(editor).not.toBeVisible();
  await expect(page.getByRole('heading', { name: trainerName, exact: true })).toBeVisible();
  await page.getByRole('link', { name: 'スケジュール', exact: true }).click();
  await page.getByLabel('トレーナーを選択', { exact: true }).selectOption({ label: trainerName });
  await page.getByLabel('火曜日の開始時刻', { exact: true }).fill('10:00');
  await page.getByRole('button', { name: '保存する', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('勤務スケジュールを更新しました');
  const available = await (await page.request.get('/availability')).json();
  const closure = new Date(`${available.start}T00:00:00Z`);
  closure.setUTCDate(closure.getUTCDate() + 40);
  await page.getByLabel('休業日', { exact: true }).fill(closure.toISOString().slice(0, 10));
  const reason = `E2E休業確認-${Date.now()}`;
  await page.getByLabel('理由', { exact: true }).fill(reason);
  await page.getByRole('button', { name: '休業日を追加', exact: true }).click();
  const row = page.getByRole('listitem').filter({ hasText: reason });
  await expect(row).toBeVisible();
  await row.getByRole('button', { name: /の休業日を削除$/ }).click();
  await row.getByRole('button', { name: /の休業日を削除する$/ }).click();
  await expect(page.getByText(reason, { exact: true })).not.toBeVisible();
});

test('ログイン前に選んだ日時と連絡事項がログイン後も保持される', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('button', { name: /YUNA/ }).click();
  await expect(page).toHaveURL(/trainer_id=1/);
  const slot = page.getByRole('button', { name: /予約可能$/ }).first();
  const slotName = await slot.getAttribute('aria-label');
  await slot.click();
  await page.getByLabel('ご要望・連絡事項', { exact: false }).fill('ログイン前のご要望');
  await page.getByRole('button', { name: '予約内容を確認', exact: true }).click();
  await page.getByRole('dialog').getByRole('link', { name: 'ログイン', exact: true }).click();
  await page.getByLabel('メールアドレス', { exact: true }).fill('client@example.com');
  await page.getByLabel('パスワード', { exact: true }).fill('Password123!');
  await page.getByRole('button', { name: 'ログイン', exact: true }).click();
  await expect(page).toHaveURL(/trainer_id=1/);
  await expect(page.getByRole('button', { name: slotName!, exact: true })).toHaveAttribute('aria-pressed', 'true');
  await expect(page.getByLabel('ご要望・連絡事項', { exact: false })).toHaveValue('ログイン前のご要望');
});
