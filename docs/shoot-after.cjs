const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:8000';
const OUT = '/home/hatch/workspace/posme/docs/screenshots/redesign';
fs.mkdirSync(OUT, { recursive: true });

const EXE = '/home/hatch/.cache/ms-playwright/chromium-1255/chrome-linux64/chrome';
const SIGNED = process.argv[2]; // signed URL meja 1

async function login(page, email) {
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
}

(async () => {
  const browser = await chromium.launch({ executablePath: EXE, args: ['--no-sandbox'] });

  // --- Customer (390px): menu, keranjang, lacak pesanan ---
  const cust = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await cust.goto(SIGNED, { waitUntil: 'networkidle' });
  await cust.waitForTimeout(1500);
  await cust.screenshot({ path: path.join(OUT, 'after-customer-menu-390.png') });
  console.log('after customer menu ok');

  // Tambah 2 item ke keranjang.
  const addBtns = cust.locator('button:has-text("+ Tambah")');
  await addBtns.nth(0).click();
  await cust.waitForTimeout(800);
  await addBtns.nth(1).click();
  await cust.waitForTimeout(800);
  await cust.screenshot({ path: path.join(OUT, 'after-customer-cart-390.png') });
  console.log('after customer cart ok');

  // Kirim pesanan -> layar lacak.
  await cust.locator('button:has-text("Kirim Pesanan")').first().click();
  await cust.waitForTimeout(2500);
  await cust.screenshot({ path: path.join(OUT, 'after-customer-tracking-390.png') });
  console.log('after customer tracking ok');

  // --- Kitchen (1440) ---
  const kitchen = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await login(kitchen, 'dapur@posme.test');
  await kitchen.goto(BASE + '/kitchen', { waitUntil: 'networkidle' });
  await kitchen.waitForTimeout(1500);
  await kitchen.screenshot({ path: path.join(OUT, 'after-kitchen-1440.png') });
  console.log('after kitchen ok');

  // --- Cashier (1440) ---
  const cashier = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await login(cashier, 'kasir@posme.test');
  await cashier.goto(BASE + '/cashier', { waitUntil: 'networkidle' });
  await cashier.waitForTimeout(1500);
  await cashier.screenshot({ path: path.join(OUT, 'after-cashier-1440.png') });
  console.log('after cashier ok');

  // --- Admin dashboard (1440) ---
  const admin = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await login(admin, 'admin@posme.test');
  await admin.goto(BASE + '/admin', { waitUntil: 'networkidle' });
  await admin.waitForTimeout(2000);
  await admin.screenshot({ path: path.join(OUT, 'after-admin-1440.png') });
  console.log('after admin ok');

  // --- Gateway settings (1440) ---
  await admin.goto(BASE + '/admin/gateway-settings', { waitUntil: 'networkidle' });
  await admin.waitForTimeout(1500);
  await admin.screenshot({ path: path.join(OUT, 'after-gateway-1440.png') });
  console.log('after gateway ok');

  // --- Deploy settings (1440) ---
  await admin.goto(BASE + '/admin/deploy-settings', { waitUntil: 'networkidle' });
  await admin.waitForTimeout(1500);
  await admin.screenshot({ path: path.join(OUT, 'after-deploy-1440.png') });
  console.log('after deploy ok');

  await browser.close();
  console.log('selesai');
})().catch((e) => { console.error(e); process.exit(1); });
