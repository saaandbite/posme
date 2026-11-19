const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:8000';
const OUT = '/home/hatch/workspace/posme/docs/screenshots';
fs.mkdirSync(OUT, { recursive: true });

const EXE = '/home/hatch/.cache/ms-playwright/chromium-1255/chrome-linux64/chrome';

async function login(page, email) {
  await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
}

(async () => {
  const browser = await chromium.launch({ executablePath: EXE, args: ['--no-sandbox'] });

  // 06. Pengaturan Gateway (desktop, admin).
  const admin = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await login(admin, 'admin@posme.test');
  await admin.goto(BASE + '/admin/gateway-settings', { waitUntil: 'networkidle' });
  await admin.waitForTimeout(1500);
  await admin.screenshot({ path: path.join(OUT, '06-gateway-settings.png'), fullPage: true });
  console.log('06 gateway settings ok');

  // 07. Menu Builder — daftar Item Menu (desktop, admin).
  await admin.goto(BASE + '/admin/menu-items', { waitUntil: 'networkidle' });
  await admin.waitForTimeout(1500);
  await admin.screenshot({ path: path.join(OUT, '07-menu-builder.png'), fullPage: true });
  console.log('07 menu builder ok');

  await browser.close();
  console.log('selesai');
})().catch((e) => { console.error(e); process.exit(1); });
