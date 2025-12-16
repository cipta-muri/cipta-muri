import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

test.beforeAll(() => {
  // Bootstrap admin user/role/rekening for the session
  execSync('php tests/playwright/bootstrap_admin.php', { stdio: 'inherit' });
});

const ADMIN_EMAIL = 'testing@ciptamuri.com';
const ADMIN_NIK = '9999999999999999';

async function loginAsAdmin(page) {
  await page.goto(`/_playwright/login?email=${encodeURIComponent(ADMIN_EMAIL)}`);
  await page.goto('/admin');
  // Debug cookies to ensure session is attached during CI/local runs.
  // Remove verbose output if it becomes noisy.
  // eslint-disable-next-line no-console
  console.log(await page.context().cookies());
  // eslint-disable-next-line no-console
  console.log('current url', await page.url());
  await expect(page.locator('.fi-main')).toBeVisible({ timeout: 20_000 });
}

test('admin can login and see Filament main layout', async ({ page }) => {
  await loginAsAdmin(page);
});

test('Sampah CRUD create-update-delete', async ({ page }) => {
  await loginAsAdmin(page);

  // Buat data lewat factory agar id diketahui
  const name = 'PW Sampah ' + Math.random().toString(36).slice(2, 6).toUpperCase();
  const sampah = await page.request.post('/_playwright/factory', { data: { type: 'sampah', jenis_sampah: name, saldo_per_kg: 1000 } }).then(r => r.json());
  const updated = sampah.jenis_sampah + ' UPD';

  // Update lewat UI edit page
  await page.goto(`/admin/sampahs/${sampah.id}/edit`);
  await page.fill('input[dusk="sampah-form-jenis_sampah"]', updated);
  await page.click('form[wire\\:submit] button[type=submit]');
  await expect(page.getByText('Data berhasil disimpan')).toBeVisible({ timeout: 10_000 });

  await page.goto('/admin/sampahs');
  await expect(page.getByText(updated)).toBeVisible({ timeout: 10_000 });

  const deleteSelector = `[wire\\:key$=".table.records.${sampah.id}"] [dusk="sampah-delete-action"]`;
  await page.click(deleteSelector, { timeout: 10_000 });
  await page.click('.fi-modal button[type=submit]', { timeout: 10_000 });
  await page.waitForTimeout(1000);
  await expect(page.getByText(updated)).not.toBeVisible({ timeout: 10_000 });
});

test('Setor Sampah delete existing record', async ({ page, request }) => {
  await loginAsAdmin(page);
  const sampah = await request.post('/_playwright/factory', { data: { type: 'sampah', jenis_sampah: 'PW SEED SETOR ' + Date.now() } }).then(r => r.json());
  const setor = await request.post('/_playwright/factory', { data: { type: 'setor_sampah', sampah_id: sampah.id } }).then(r => r.json());

  await page.goto('/admin/setor-sampahs');
  await expect(page.getByText('Playwright')).toBeVisible({ timeout: 10_000 }).catch(() => {});

  const row = `[wire\\:key$=".table.records.${setor.id}"]`;
  await page.waitForSelector(row, { timeout: 10_000 });
  await page.click(`${row} [dusk="setor-delete-action"]`);
  await page.click('.fi-modal button[type=submit]', { timeout: 10_000 });
  await page.waitForTimeout(1000);
  await expect(page.locator(row)).toHaveCount(0, { timeout: 10_000 });
});

test('Sampah Keluar delete existing record', async ({ page, request }) => {
  await loginAsAdmin(page);
  const sampah = await request.post('/_playwright/factory', { data: { type: 'sampah', jenis_sampah: 'PW KELUAR ' + Date.now() } }).then(r => r.json());
  const keluar = await request.post('/_playwright/factory', { data: { type: 'sampah_keluar', sampah_id: sampah.id } }).then(r => r.json());

  await page.goto('/admin/sampah-keluars');
  const row = `[wire\\:key$=".table.records.${keluar.id}"]`;
  await page.waitForSelector(row, { timeout: 10_000 });
  await page.click(`${row} [dusk="sampah-keluar-delete-action"]`);
  await page.click('.fi-modal button[type=submit]', { timeout: 10_000 });
  await page.waitForTimeout(1000);
  await expect(page.locator(row)).toHaveCount(0, { timeout: 10_000 });
});

test('Withdraw Request delete existing record', async ({ page, request }) => {
  await loginAsAdmin(page);
  const rekening = await request.post('/_playwright/factory', { data: { type: 'rekening', balance: 250000 } }).then(r => r.json());
  const withdraw = await request.post('/_playwright/factory', { data: { type: 'withdraw_request', rekening_id: rekening.id, amount: 15000 } }).then(r => r.json());

  await page.goto('/admin/withdraw-requests');
  const row = `[wire\\:key$=".table.records.${withdraw.id}"]`;
  await page.waitForSelector(row, { timeout: 10_000 });
  await page.click(`${row} [dusk="withdraw-delete-action"]`);
  await page.click('.fi-modal button[type=submit]', { timeout: 10_000 });
  await page.waitForTimeout(1000);
  await expect(page.locator(row)).toHaveCount(0, { timeout: 10_000 });
});
