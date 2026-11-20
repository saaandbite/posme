const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:8000';
const OUT = '/home/hatch/workspace/posme/docs/screenshots/redesign';
fs.mkdirSync(OUT, { recursive: true });

const EXE = '/home/hatch/.cache/ms-playwright/chromium-1255/chrome-linux64/chrome';
const SIGNED = process.argv[2]; // signed URL meja 1 (token, temporary)

(async () => {
  const browser = await chromium.launch({ executablePath: EXE, args: ['--no-sandbox'] });

  // --- Customer (390px): menu, keranjang, lacak pesanan ---
  const cust = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await cust.goto(SIGNED, { waitUntil: 'networkidle' });
  await cust.waitForTimeout(1500);
  await cust.screenshot({ path: path.join(OUT, 'after2-customer-menu-390.png') });
  console.log('after2 customer menu ok');

  // Tambah 2 item: menu andalan (Kopi Tubruk) lalu baris pertama (Es Kopi Susu).
  const addBtns = cust.locator('button[aria-label^="Tambah"]');
  await addBtns.nth(0).click();
  await cust.waitForTimeout(800);
  await addBtns.nth(1).click();
  await cust.waitForTimeout(800);
  await cust.screenshot({ path: path.join(OUT, 'after2-customer-cart-390.png') });
  console.log('after2 customer cart ok');

  // Kirim pesanan -> layar lacak.
  await cust.locator('button:has-text("Kirim Pesanan")').first().click();
  await cust.waitForTimeout(2500);
  await cust.screenshot({ path: path.join(OUT, 'after2-customer-tracking-390.png') });
  console.log('after2 customer tracking ok');

  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
