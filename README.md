# Posme — Sistem POS QR Code untuk Resto / Kafe

Posme adalah sistem kasir (Point of Sale) berbasis web untuk resto atau kafe.
Customer scan QR code di meja, pesan dari HP tanpa login, dan bisa bayar
sendiri lewat payment gateway (Midtrans Snap: QRIS / VA / e-wallet) atau
bayar di kasir. Dapur melihat order masuk di layar khusus (KDS), kasir
memantau semua order dan menerima pembayaran, admin mengatur semuanya
lewat panel Filament.

Stack: **Laravel 13** (PHP 8.3) + **Filament 3** (panel admin) +
**Livewire 3** + **SQLite** (tanpa setup database) +
**Midtrans Snap** (`midtrans/midtrans-php`) + **simple-qrcode**.

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
  (tunai/QRIS), status gateway Midtrans.

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

## Pembayaran Midtrans Snap

Customer bayar sendiri dari HP di meja lewat QRIS / VA / e-wallet.
Setelah bayar, webhook Midtrans mengubah `payment_status` order jadi
`paid` dan halaman lacak menampilkan bukti bayar.

### Sandbox (untuk coba-coba)

1. Daftar akun di [Midtrans](https://dashboard.midtrans.com/register)
   (gratis, pilih mode Sandbox).
2. Di dashboard Midtrans: **Settings → Access Keys**, salin
   **Server Key** dan **Client Key** (Sandbox).
3. Isi di `.env`:
   ```env
   MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
   MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxx
   MIDTRANS_IS_PRODUCTION=false
   ```
4. Restart `php artisan serve`. Tombol "Bayar Sekarang" di halaman
   customer akan membuka popup Snap (sandbox).
5. Untuk menguji webhook lokal, jalankan `ngrok http 8000` lalu
   daftarkan URL webhook `https://<ngrok-url>/midtrans/notification`
   di dashboard Midtrans (Settings → Configuration → Payment
   Notification URL).

### Produksi (yang harus disiapkan Haris)

1. Akun Midtrans produksi (verifikasi bisnis) + ganti key di `.env`
   dengan key **produksi**, lalu set `MIDTRANS_IS_PRODUCTION=true`.
2. Daftarkan webhook URL produksi, mis.
   `https://posme-domain-kamu/midtrans/notification`, di dashboard
   Midtrans (Settings → Configuration).
3. Pastikan webhook bisa diakses publik (HTTPS) — kalau tidak,
   pembayaran gateway tidak akan terkonfirmasi otomatis dan kasir
   harus menandai lunas manual.

### Kalau key belum diisi

Aplikasi **tetap jalan normal**. Gateway ditandai "belum
dikonfigurasi" di halaman Pengaturan admin, dan customer diarahkan
ke opsi "Bayar di kasir (tunai/QRIS)". Tidak ada error karena key
kosong.

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
- CSRF aktif (kecuali route webhook Midtrans), mass assignment
  dijaga (`$fillable`), `.env` tidak pernah di-commit.

## Testing

```bash
php artisan test
# 17 passed (61 assertions)
```

Test (`tests/Feature/PosFlowTest.php`) mencakup: customer bisa buat
order lewat signed URL; harga diambil dari server (bukan request);
URL tanpa signature ditolak; kitchen tidak bisa buka admin/kasir;
cashier tidak bisa buka admin/dapur; cashier menandai lunas dengan
kembalian benar; uang kurang ditolak; order batal tidak bisa dibayar;
dapur memajukan status; webhook Midtrans signature valid → lunas,
signature salah → 403; gateway belum dikonfigurasi tidak error.

## Screenshot

Semua di `docs/screenshots/` (tidak ikut ter-push ke GitHub karena
file biner — lihat di repo lokal):

- `01-menu-customer.png` — menu customer di HP (Meja 1)
- `02-menu-customer-keranjang.png` — keranjang + sticky bar
- `03-board-dapur.png` — board dapur (KDS)
- `04-terminal-kasir.png` — monitor order kasir
- `05-dashboard-admin.png` — dashboard admin Filament

## Yang BELUM selesai / butuh Haris

- **Deploy publik** — aplikasi baru jalan lokal (`php artisan serve`).
  Belum di-deploy ke hosting/VPS/Vercel. Perlu Haris memilih hosting
  (mis. VPS + Nginx, atau platform Laravel seperti Laravel Cloud).
- **Key Midtrans asli** — `.env` masih kosong (sandbox/produksi).
  Tanpa key, pembayaran gateway tidak aktif dan customer diarahkan
  "Bayar di kasir". Lihat bagian "Pembayaran Midtrans Snap" di atas.
- **Foto menu asli** — item menu contoh belum punya foto (foto bisa
  di-upload dari panel admin, tersimpan di `storage/app/public`).
- **Domain & HTTPS** — webhook Midtrans produksi butuh URL publik
  HTTPS; QR meja berisi URL `APP_URL`, jadi ganti `APP_URL` di `.env`
  setelah deploy.
- **Password demo** — ganti/hapus akun demo sebelum produksi.
- **Suara notifikasi dapur** — beep sederhana via Web Audio; di
  beberapa browser bunyi hanya jalan setelah user berinteraksi dulu
  dengan halaman (aturan autoplay browser).
