# Posme — Sistem POS QR Code untuk Resto / Kafe

Posme adalah sistem kasir (Point of Sale) berbasis web untuk resto atau kafe.
Customer scan QR code di meja, pesan dari HP tanpa login, dan bisa bayar
sendiri lewat payment gateway (Midtrans Snap atau Xendit Invoice: QRIS / VA /
e-wallet) atau bayar di kasir. Dapur melihat order masuk di layar khusus
(KDS), kasir memantau semua order dan menerima pembayaran, admin mengatur
semuanya lewat panel Filament — termasuk gateway dan tampilan menu, tanpa
sentuh kode (low-code).

Stack: **Laravel 13** (PHP 8.3) + **Filament 3** (panel admin) +
**Livewire 3** + **SQLite** (tanpa setup database) +
**Midtrans Snap** (`midtrans/midtrans-php`) + **Xendit Invoice** (HTTP client
Laravel, tanpa SDK) + **simple-qrcode**.

Bahasa UI: Bahasa Indonesia. Mata uang: Rupiah.

## Cara menjalankan (lokal)

```bash
# 1. Clone & install dependency
git clone https://github.com/saaandbite/posme.git
cd posme
composer install
npm install && npm run build

# 2. Konfigurasi
cp .env.example .env
php artisan key:generate

# 3. Database + data contoh
php artisan migrate --seed
php artisan storage:link

# 4. Jalankan
php artisan serve
# Buka http://127.0.0.1:8000
```

> Catatan: `public/build` tidak ikut di-commit (standar Laravel), jadi
> `npm install && npm run build` wajib dijalankan sekali setelah clone.

## Login demo (password semua: `password`)

| Role | Email | Masuk ke |
| --- | --- | --- |
| Admin | admin@posme.test | `/admin` (panel Filament) |
| Kasir | kasir@posme.test | `/cashier` (monitor + terminal + riwayat) |
| Dapur | dapur@posme.test | `/kitchen` (board order) |

> **PENTING:** akun demo ini hanya untuk pengembangan lokal. Ganti
> passwordnya (atau hapus usernya) sebelum dipakai di server produksi.

## Alur utama

1. **Admin** buat kategori, item menu, dan meja di panel `/admin`.
   Di halaman "Meja & QR Code", tiap meja punya tombol "Lihat QR".
   Halaman "Print QR Meja" mencetak semua QR sekaligus untuk ditempel
   di meja.
2. **Customer** scan QR di meja → halaman menu terbuka (URL bertanda
   tangan, tidak bisa ditebak untuk meja lain) → tambah item ke
   keranjang, tulis catatan per item (mis. "tidak pedas") → kirim order.
3. **Dapur** melihat order di `/kitchen` (refresh otomatis tiap 5 detik,
   kartu baru berkedip + bunyi "beep") → ubah status
   Dipesan → Disiapkan → Siap → Disajikan dengan tombol besar.
4. **Customer** melacak status ordernya di halaman yang sama, dan bisa
   klik **"Bayar Sekarang"** (Midtrans Snap) atau memilih
   **"Bayar di kasir"** bila gateway belum dikonfigurasi.
5. **Kasir** memantau semua order di `/cashier` (tab Monitor Order),
   menandai "Disajikan" saat mengantar, menerima bayar manual
   (tunai/QRIS, kembalian dihitung otomatis), mencetak struk, dan
   melihat riwayat transaksi hari ini.

## Fitur per role

### Admin (`/admin`, Filament)
- CRUD kategori menu dan item menu (nama, deskripsi, harga, foto,
  ketersediaan, aktif/nonaktif).
- CRUD meja + QR code per meja (URL signed) + halaman print semua QR.
- CRUD pengguna staf dengan role (hanya admin yang bisa masuk panel).
- Laporan di dashboard: pendapatan hari ini, jumlah order, order aktif,
  grafik pendapatan 7 hari, item terlaris, filter status & periode
  di halaman Order.
- Pengaturan: nama resto, pajak %, service %, metode bayar manual
  (tunai/QRIS), status gateway aktif, dan tampilan menu customer
  (layout grid/list, default foto & deskripsi).
- **Pengaturan Gateway** (`/admin/gateway-settings`, menu Sistem):
  pilih provider aktif, isi key, mode Sandbox/Produksi, tes koneksi.
- **Menu Builder**: drag-and-drop urutan kategori & item menu,
  toggle tampil foto/deskripsi per item, badge/label bebas
  (mis. "Baru", "Best Seller", "Pedas").

### Kasir (`/cashier`)
- **Monitor Order** (live, refresh 5 detik): semua meja, daftar item +
  jumlah, status order, status bayar (Belum bayar / Lunas via gateway /
  Lunas tunai-QRIS), waktu order. Bisa tandai "Disajikan" dan
  "Terima Bayar" dari kartu order.
- **Pembayaran**: pilih metode (Tunai/QRIS), input uang diterima,
  kembalian dihitung otomatis, tandai lunas, cetak struk
  (`/cashier/receipt/{order}`).
- Batalkan order dengan alasan (hanya sebelum dibayar).
- **Riwayat Hari Ini**: order lunas & batal hari ini.

### Dapur (`/kitchen`, layar KDS gelap)
- Board order real-time (polling 5 detik): kartu per order berisi nomor
  meja, item + jumlah + catatan, waktu order.
- Status: Baru (merah) → Disiapkan (kuning) → Siap (hijau) →
  Disajikan (keluar dari board). Order baru berkedip + bunyi beep.
- Order yang sudah disajikan masuk riwayat (tidak tampil di board aktif).

### Customer (tanpa login, `/m/{kode-meja}?signature=...`)
- Menu per kategori dengan foto & harga, mobile-first, tombol besar.
- Item habis tidak bisa dipesan. Keranjang sticky di bawah (session
  per meja, tidak hilang saat refresh).
- Catatan per item (maks. 200 karakter), qty maks. 20 per item.
- Lacak status order (Diterima → Disiapkan → Siap → Disajikan) +
  status bayar (Belum bayar / Menunggu pembayaran / Lunas) +
  tombol "Bayar Sekarang" (Snap) atau "Bayar di kasir".

## Pembayaran Midtrans Snap & Xendit Invoice

Customer bayar sendiri dari HP di meja lewat QRIS / VA / e-wallet.
Setelah bayar, webhook gateway mengubah `payment_status` order jadi
`paid` dan halaman lacak menampilkan bukti bayar.

Gateway yang dipakai dipilih owner di halaman **Pengaturan Gateway**
(`/admin/gateway-settings`): "Tidak ada (bayar di kasir saja)",
"Midtrans", atau "Xendit". Hanya satu yang aktif dalam satu waktu.
Alur bayar customer membaca gateway aktif dari database saat runtime.

### Mengisi key — lewat dashboard (cara utama)

1. Buka `/admin` → menu **Sistem → Pengaturan Gateway**.
2. Pilih provider aktif di dropdown "Gateway pembayaran".
3. Isi kredensial provider tersebut:
   - **Midtrans**: Server Key + Client Key (dashboard Midtrans →
     Settings → Access Keys).
   - **Xendit**: Secret Key + Webhook Callback Token (dashboard
     Xendit → Settings → Developers → API Keys & Webhooks).
4. Atur toggle **Mode Produksi**: mati = Sandbox (uang mainan),
   nyala = Produksi (uang asli, pakai key produksi).
5. Klik **"Tes koneksi"** untuk memastikan key valid:
   - Midtrans: cek status order dummy via Core API
     (401 = key salah, respons lain = key valid).
   - Xendit: GET `/balance` (200 = key valid).
   Hasil tes disimpan dan ditampilkan di halaman yang sama.
6. Klik **"Simpan Pengaturan Gateway"**.

### Fallback `.env` (cara lama, tetap didukung)

Bila database gateway masih kosong, aplikasi memakai nilai `.env`
sebagai fallback, jadi instalasi lama tetap jalan tanpa perubahan:

```env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxx
MIDTRANS_IS_PRODUCTION=false

XENDIT_SECRET_KEY=xnd_development_xxxx
XENDIT_CALLBACK_TOKEN=token-rahasia-xxxx
```

**Kredensial di database mengalahkan `.env`.** Begitu owner mengisi
key di dashboard, key itulah yang dipakai (termasuk untuk verifikasi
signature webhook Midtrans dan token webhook Xendit).

### Keamanan key

- Key yang disimpan lewat dashboard **dienkripsi** di database
  (cast Laravel `encrypted:array`, kunci enkripsi = `APP_KEY`).
  Nilai mentah di database tidak bisa dibaca tanpa `APP_KEY`.
- Di halaman pengaturan, key hanya tampil **4 karakter terakhir**
  (ter-mask). Key hanya berubah bila admin mengisi ulang fieldnya —
  menyimpan form tanpa mengubah field tidak menghapus key lama.
- **Jangan pernah** menulis key asli di README, chat, atau commit.
  Key produksi hanya diisi owner sendiri di dashboard / `.env`
  server produksi.
- Webhook Xendit **menolak** request tanpa header
  `x-callback-token` yang cocok dengan token tersimpan (403).
  Webhook Midtrans tetap memverifikasi signature
  `sha512(order_id + status_code + gross_amount + server_key)`.

### Webhook (produksi)

- Midtrans: daftarkan `https://domain-kamu/midtrans/notification`
  di dashboard Midtrans (Settings → Configuration → Payment
  Notification URL).
- Xendit: daftarkan `https://domain-kamu/xendit/notification`
  di dashboard Xendit (Settings → Developers → Webhooks) dan isi
  callback token yang sama di halaman Pengaturan Gateway.
- Untuk menguji webhook lokal, jalankan `ngrok http 8000` lalu
  daftarkan URL `https://<ngrok-url>/midtrans/notification` (atau
  `/xendit/notification`).

### Kalau key belum diisi

Aplikasi **tetap jalan normal**. Gateway ditandai "tidak ada gateway
aktif" di halaman Pengaturan admin, dan customer diarahkan ke opsi
"Bayar di kasir (tunai/QRIS)". Tidak ada error karena key kosong.

## Low-code: Menu Builder

Owner menata tampilan menu customer sendiri dari panel admin, tanpa
sentuh kode.

### Urutan (drag-and-drop)

- **Kategori Menu** (`/admin/categories`): seret baris untuk mengubah
  `sort_order` — urutan kategori di menu customer mengikuti ini.
- **Item Menu** (`/admin/menu-items`): sama, seret baris untuk
  mengubah urutan item di dalam daftar.
- Urutan juga bisa diubah manual lewat field "Urutan tampil" di form.

### Tampilan per item (form Item Menu → bagian "Tampilan di menu customer")

- **Tampilkan foto**: Ya / Tidak / Ikuti default global.
- **Tampilkan deskripsi**: Ya / Tidak / Ikuti default global.
- **Badge / label**: teks bebas, mis. "Baru", "Best Seller", "Pedas"
  (boleh kosong — tidak tampil apa-apa).

### Tampilan global (Pengaturan → "Tampilan Menu")

- **Layout menu**: Grid (2 kolom) atau List (1 kolom).
- **Tampilkan foto (default)** dan **Tampilkan deskripsi (default)**:
  berlaku untuk semua item yang toggle per-itemnya dibiarkan
  "Ikuti default global". Toggle per item mengalahkan default ini.

### Yang tetap dijaga

- Kategori/item **nonaktif tidak tampil** di menu customer.
- Item **habis tetap tidak bisa dipesan** (tombol tambah disembunyikan).
- **Harga tetap dihitung dari server** — harga di request customer
  tidak pernah dipercaya.

### Produksi (yang harus disiapkan Haris)

1. Akun Midtrans/Xendit produksi (verifikasi bisnis) + isi key
   **produksi** di halaman Pengaturan Gateway, lalu nyalakan
   toggle Mode Produksi untuk provider yang dipakai.
2. Daftarkan webhook URL produksi (lihat di atas).
3. Pastikan webhook bisa diakses publik (HTTPS) — kalau tidak,
   pembayaran gateway tidak akan terkonfirmasi otomatis dan kasir
   harus menandai lunas manual.

## Keamanan

- Semua rute staf di balik auth + middleware role (`role:admin`,
  `role:cashier`, `role:kitchen`); Filament panel hanya bisa dibuka
  admin (`User::canAccessPanel()`).
- URL menu customer memakai **signed URL** (`URL::signedRoute`) —
  URL tanpa signature ditolak (403).
- Submit order di-throttle (maks. 5 order / 5 menit per meja + IP)
  dan divalidasi ketat (item harus ada & tersedia, qty 1–20,
  catatan maks. 200 karakter).
- **Harga selalu dihitung ulang dari database di server** saat order
  dibuat — harga dari request customer tidak pernah dipercaya.
- Webhook Midtrans memverifikasi signature
  (`sha512(order_id + status_code + gross_amount + server_key)`)
  **sebelum** mengubah status apa pun; signature salah → 403.
- Webhook Xendit memverifikasi header `x-callback-token` dengan
  token tersimpan **sebelum** mengubah status apa pun; token salah
  atau tidak ada → 403.
- Kredensial gateway yang diisi lewat dashboard disimpan **terenkripsi**
  di database (`encrypted:array`) dan hanya tampil 4 karakter terakhir
  di UI; `.env` tidak pernah di-commit.
- CSRF aktif (kecuali route webhook Midtrans & Xendit), mass assignment
  dijaga (`$fillable`), `.env` tidak pernah di-commit.

## Testing

```bash
php artisan test
# 29 passed (157 assertions)
```

Test (`tests/Feature/PosFlowTest.php`, 17 test) mencakup: customer bisa buat
order lewat signed URL; harga diambil dari server (bukan request);
URL tanpa signature ditolak; kitchen tidak bisa buka admin/kasir;
cashier tidak bisa buka admin/dapur; cashier menandai lunas dengan
kembalian benar; uang kurang ditolak; order batal tidak bisa dibayar;
dapur memajukan status; webhook Midtrans signature valid → lunas,
signature salah → 403; gateway belum dikonfigurasi tidak error.

Test (`tests/Feature/LowcodeTest.php`, 12 test) mencakup: reorder
kategori/item tersimpan dan tercermin di menu; kategori/item nonaktif
tidak tampil; toggle foto/deskripsi menyembunyikan field di menu
(termasuk fallback default global); badge tampil dan layout grid/list
mengikuti pengaturan; gateway settings tersimpan terenkripsi dan tampil
ter-mask; halaman Pengaturan Gateway bisa dibuka & disimpan admin;
alur bayar memakai gateway aktif dari database; tanpa gateway aktif
fallback kasir jalan tanpa error; kredensial database mengalahkan
`.env` untuk verifikasi signature; webhook Xendit token salah → 403,
token benar → lunas, EXPIRED → kembali belum bayar; enum status &
role bekerja.

## Screenshot

Semua di `docs/screenshots/` (tidak ikut ter-push ke GitHub karena
file biner — lihat di repo lokal):

- `01-menu-customer.png` — menu customer di HP (Meja 1)
- `02-menu-customer-keranjang.png` — keranjang + sticky bar
- `03-board-dapur.png` — board dapur (KDS)
- `04-terminal-kasir.png` — monitor order kasir
- `05-dashboard-admin.png` — dashboard admin Filament
- `06-gateway-settings.png` — halaman Pengaturan Gateway (low-code)
- `07-menu-builder.png` — daftar Item Menu dengan kolom Badge (Menu Builder)

## Yang BELUM selesai / butuh Haris

- **Deploy publik** — aplikasi baru jalan lokal (`php artisan serve`).
  Belum di-deploy ke hosting/VPS/Vercel. Perlu Haris memilih hosting
  (mis. VPS + Nginx, atau platform Laravel seperti Laravel Cloud).
- **Key gateway asli** — database gateway masih kosong (dan `.env`
  juga kosong untuk Midtrans/Xendit). Tanpa key, pembayaran gateway
  tidak aktif dan customer diarahkan "Bayar di kasir". Key produksi
  harus diisi Haris sendiri di halaman Pengaturan Gateway
  (lihat bagian "Pembayaran Midtrans Snap & Xendit Invoice").
- **Foto menu asli** — item menu contoh belum punya foto (foto bisa
  di-upload dari panel admin, tersimpan di `storage/app/public`).
- **Domain & HTTPS** — webhook Midtrans produksi butuh URL publik
  HTTPS; QR meja berisi URL `APP_URL`, jadi ganti `APP_URL` di `.env`
  setelah deploy.
- **Password demo** — ganti/hapus akun demo sebelum produksi.
- **Suara notifikasi dapur** — beep sederhana via Web Audio; di
  beberapa browser bunyi hanya jalan setelah user berinteraksi dulu
  dengan halaman (aturan autoplay browser).
