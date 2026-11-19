const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const BASE = 'http://127.0.0.1:8000';
const OUT = '/home/hatch/workspace/posme/docs/screenshots';
fs.mkdirSync(OUT, { recursive: true });

const EXE = '/home/hatch/.cache/ms-playwright/chromium-1255/chrome-linux64/chrome';

// Ambil signed URL Meja 1 dari aplikasi.
const signed = execSync(
  "cd /home/hatch/workspace/posme && php artisan tinker --execute=\"echo \\\\App\\\\Models\\\\DiningTable::first()->orderUrl();\"",
  { encoding: 'utf8' }
).trim();

async function login(page, email) {
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
}

(async () => {
  const browser = await chromium.launch({ executablePath: EXE, args: ['--no-sandbox'] });

  // 1. Menu customer (HP, 390x844) — signed URL Meja 1.
  const mobile = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await mobile.goto(signed, { waitUntil: 'networkidle' });
  await mobile.waitForTimeout(1500);
  await mobile.screenshot({ path: path.join(OUT, '01-menu-customer.png'), fullPage: true });
  console.log('01 menu customer ok');

  // Tambah 1 item ke keranjang supaya sticky bar terlihat.
  try {
    await mobile.click('button:has-text("Tambah") >> nth=0', { timeout: 5000 });
    await mobile.waitForTimeout(1200);
    await mobile.screenshot({ path: path.join(OUT, '02-menu-customer-keranjang.png') });
    console.log('02 keranjang ok');
  } catch (e) {
    console.log('02 keranjang skip:', e.message.split('\n')[0]);
  }

  // 2. Board dapur (desktop).
  const kitchen = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  await login(kitchen, 'dapur@posme.test');
  await kitchen.goto(BASE + '/kitchen', { waitUntil: 'networkidle' });
  await kitchen.waitForTimeout(1500);
  await kitchen.screenshot({ path: path.join(OUT, '03-board-dapur.png'), fullPage: true });
  console.log('03 dapur ok');

  // 3. Terminal kasir (desktop).
  const cashier = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  await login(cashier, 'kasir@posme.test');
  await cashier.goto(BASE + '/cashier', { waitUntil: 'networkidle' });
  await cashier.waitForTimeout(1500);
  await cashier.screenshot({ path: path.join(OUT, '04-terminal-kasir.png'), fullPage: true });
  console.log('04 kasir ok');

  // 4. Dashboard admin (desktop).
  const admin = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  await login(admin, 'admin@posme.test');
  await admin.goto(BASE + '/admin', { waitUntil: 'networkidle' });
  await admin.waitForTimeout(1500);
  await admin.screenshot({ path: path.join(OUT, '05-dashboard-admin.png'), fullPage: true });
  console.log('05 admin ok');

  await browser.close();
  console.log('selesai');
})().catch((e) => { console.error(e); process.exit(1); });
