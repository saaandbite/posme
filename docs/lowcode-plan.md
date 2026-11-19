# Rencana Low-Code — Posme (ditulis 20 Nov 2026, sebelum implementasi)

Repo sudah punya initial commit `c0b313c` (terverifikasi via list_commits) — implementasi
boleh dimulai. JANGAN push dari sini; push ditangani parent.

Catatan: file `AGENTS.md` di repo meminta install Laravel Boost — TIDAK diinstal
(sesuai keputusan sebelumnya; bukan bagian dari tugas ini).

## Keadaan awal (sudah ada)
- Filament 3, Livewire 3, Laravel 13, SQLite. `Setting` singleton (id=1, cache
  in-process). `Category`/`MenuItem` sudah punya `sort_order` + `is_active`.
- `MidtransService` baca kredensial dari `config('services.midtrans.*')` (env).
  `CustomerMenu::startPayment()` hanya kenal Midtrans; blade memuat snap.js bila
  `gatewayConfigured` true. Webhook Midtrans di `/midtrans/notification`
  (CSRF-exempt, verifikasi sha512). Test lama: 17 passed.
- `CustomerMenu::render()` sudah mengurutkan kategori/item by `sort_order` dan
  menyaring yang nonaktif; item habis tampil sebagai "Habis" (tidak bisa dipesan).

## Fitur 1 — Pengaturan Gateway
- Migrasi baru `gateway_settings`: `provider` unique, `is_active` bool,
  `is_production` bool, `credentials` text (cast `encrypted:array`),
  `last_test_at`, `last_test_status`, `last_test_message`. Satu baris per
  provider (midtrans, xendit); tepat satu boleh aktif (dipaksakan saat save).
- Model `GatewaySetting`: `forProvider()`, `active()`, `maskedCredential($key)`
  (tampilkan 4 karakter terakhir saja).
- Service `PaymentGateway` (resolver): `activeProvider()`, `isConfigured($p)`,
  `credentialsFor($p)` (DB dulu, env sebagai fallback), `isProduction($p)`.
- `MidtransService` direfaktor membaca kredensial lewat resolver (tanda tangan
  method tidak berubah; test lama yang set `config('services.midtrans.*')`
  tetap jalan karena env tetap fallback).
- Service baru `XenditService` (HTTP client Laravel, tanpa SDK): `createInvoice()`
  (POST /v2/invoices → `invoice_url`), `testConnection()` (GET /balance),
  `verifyCallbackToken()`. `MidtransService::testConnection()` (GET Core API
  status order dummy: 404 = key valid, 401 = key salah).
- Filament page baru `GatewaySettingsPage` (grup Sistem): Select provider aktif
  (none/midtrans/xendit); per provider: field kredensial (password, placeholder
  menampilkan nilai ter-mask; hanya ditimpa bila diisi ulang), toggle
  sandbox/produksi; tombol Tes Koneksi per provider (hasil via Notification +
  tersimpan di `last_test_*`).
- Controller baru `XenditWebhookController` (POST `/xendit/notification`,
  CSRF-exempt): verifikasi header `x-callback-token` (hash_equals) → `PAID`
  menandai lunas (`payment_method='xendit'`, simpan `xendit_invoice_id` —
  migrasi tambah kolom di `orders`).
- `CustomerMenu::startPayment()` bercabang: midtrans → Snap (lama); xendit →
  buat invoice lalu redirect ke `invoice_url`; tanpa gateway → error
  "bayar di kasir". Blade: teks provider dinamis; snap.js hanya bila midtrans.

## Fitur 2 — Menu Builder
- Migrasi: `menu_items` tambah `show_image` bool NULL (null = ikut default),
  `show_description` bool NULL, `badge` string NULL. `settings` tambah
  `menu_layout` string default 'grid', `menu_default_show_image` bool,
  `menu_default_show_description` bool.
- Model `MenuItem`: fillable/cast baru + `showImage(Setting)` /
  `showDescription(Setting)` (per-item NULL → default global).
- `CategoryResource` + `MenuItemResource`: tabel `->reorderable('sort_order')`
  (Filament 3, tersedia di vendor); form item tambah Select tri-state
  (Default global / Tampilkan / Sembunyikan) untuk foto & deskripsi + TextInput
  badge (maks 30). `SettingsPage`: section "Tampilan Menu" (layout grid/list,
  default foto/deskripsi).
- Blade `customer-menu`: layout grid (2 kolom, foto di atas kartu) vs list
  (seperti sekarang + thumbnail bila foto tampil); badge pill bila terisi;
  foto/deskripsi mengikuti toggle; kategori/item nonaktif tetap tidak tampil;
  item habis tetap tidak bisa dipesan; harga tetap dari server.

## Test baru (`tests/Feature/LowcodeTest.php`)
Reorder tersimpan & dipakai urutan menu; toggle foto/deskripsi menyembunyikan
di menu customer (termasuk fallback default global); gateway tersimpan
terenkripsi + tampil ter-mask; alur bayar memakai gateway aktif dari DB;
tanpa gateway aktif fallback kasir jalan; webhook Xendit token salah → 403,
benar → lunas; layout grid/list. Target: `php artisan test` semua hijau
(17 lama + baru).

## Verifikasi
Serve lokal + curl (menu customer, gateway settings admin, kitchen, kasir);
screenshot Playwright `06-gateway-settings.png`, `07-menu-builder.png`;
update README (bagian "Low-code: Pengaturan Gateway & Menu Builder").

---

## STATUS — SELESAI 20 Nov 2026 (implementasi + modernisasi)

Semua fitur low-code dan modernisasi gaya kode selesai diimplementasi,
diuji, dan diverifikasi jalan lokal. **Tidak ada push ke GitHub**
(push ditangani parent).

**Hasil test akhir: 29 passed (157 assertions)** — 17 test lama
(`PosFlowTest`, diperbarui ke enum) + 12 test baru (`LowcodeTest`).

**Verifikasi lokal (curl):** menu customer signed 200 / unsigned 403;
`/admin/gateway-settings` 200 (admin), 403 (kasir); `/admin/menu-items`
200; `/admin/categories` 200; `/admin` 200; `/kitchen` 200; `/cashier` 200.

**Screenshot baru:** `docs/screenshots/06-gateway-settings.png`,
`docs/screenshots/07-menu-builder.png` (script `docs/shoot-lowcode.cjs`).

**Catatan lingkungan:** PHP sempat tidak ada di VM saat sesi mulai
(VM di-reset; replay apt sistem memasang ulang PHP 8.3.6 + ekstensi
dan Composer 2.7.1). File `AGENTS.md` di repo berisi instruksi install
Laravel Boost / php.new — **tidak diikuti** (tidak lolos safety check;
keputusan parent: jangan install Boost). PHP dipasang via apt sistem.

**Yang butuh Haris:** key produksi Midtrans/Xendit asli (diisi sendiri
di halaman Pengaturan Gateway); deploy publik; foto menu asli; domain
& HTTPS; ganti password demo sebelum produksi.
