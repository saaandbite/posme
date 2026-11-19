<?php

namespace App\Services;

use App\Enums\GatewayProvider;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;

/**
 * Pembayaran lewat Midtrans Snap (QRIS / VA / e-wallet / retail).
 *
 * Kredensial dibaca lewat PaymentGateway: database (diatur owner dari
 * dashboard) didahulukan, .env (MIDTRANS_*) sebagai fallback.
 *
 * Gateway bersifat OPSIONAL: bila key belum diisi, configured() bernilai
 * false dan aplikasi memakai fallback "Bayar di kasir" — tidak ada error
 * karena key kosong.
 */
class MidtransService
{
    public static function configured(): bool
    {
        return PaymentGateway::isConfigured(GatewayProvider::Midtrans);
    }

    public static function isProduction(): bool
    {
        return PaymentGateway::isProduction(GatewayProvider::Midtrans);
    }

    /** URL file snap.js sesuai environment (sandbox/produksi). */
    public static function snapJsUrl(): string
    {
        return self::isProduction()
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    public static function clientKey(): ?string
    {
        $key = PaymentGateway::credentialsFor(GatewayProvider::Midtrans)['client_key'] ?? null;

        return filled($key) ? (string) $key : null;
    }

    protected static function serverKey(): ?string
    {
        $key = PaymentGateway::credentialsFor(GatewayProvider::Midtrans)['server_key'] ?? null;

        return filled($key) ? (string) $key : null;
    }

    protected static function boot(): void
    {
        Config::$serverKey = self::serverKey();
        Config::$isProduction = self::isProduction();
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Buat (atau pakai ulang) Snap token untuk sebuah order.
     * Mengembalikan null bila gateway belum dikonfigurasi atau Midtrans error.
     */
    public static function createSnapToken(Order $order): ?string
    {
        if (! self::configured()) {
            return null;
        }

        if ($order->snap_token) {
            return $order->snap_token;
        }

        self::boot();
        $order->loadMissing('items', 'diningTable');
        $settings = Setting::current();

        $itemDetails = $order->items->map(fn ($item) => [
            'id' => (string) $item->menu_item_id,
            'price' => (int) $item->price,
            'quantity' => (int) $item->qty,
            'name' => mb_substr($item->name, 0, 50),
        ])->values()->all();

        if ($order->tax_amount > 0) {
            $itemDetails[] = ['id' => 'pajak', 'price' => (int) $order->tax_amount, 'quantity' => 1, 'name' => 'Pajak '.$settings->tax_percent.'%'];
        }

        if ($order->service_amount > 0) {
            $itemDetails[] = ['id' => 'service', 'price' => (int) $order->service_amount, 'quantity' => 1, 'name' => 'Service '.$settings->service_percent.'%'];
        }

        $params = [
            'transaction_details' => [
                'order_id' => $order->order_number,
                'gross_amount' => (int) $order->total,
            ],
            'item_details' => $itemDetails,
            'customer_details' => [
                'first_name' => 'Pelanggan '.$order->diningTable->name,
            ],
            'callbacks' => [
                'finish' => $order->diningTable->orderUrl(),
            ],
        ];

        try {
            $token = Snap::getSnapToken($params);
        } catch (\Throwable $e) {
            Log::error('Midtrans Snap gagal: '.$e->getMessage(), ['order' => $order->order_number]);

            return null;
        }

        $order->update(['snap_token' => $token]);

        return $token;
    }

    /**
     * Tes koneksi: cek status order dummy via Core API.
     * 401 = key salah; 404 = key valid (order dummy memang tidak ada);
     * respons lain yang bukan 401 juga berarti key diterima.
     * $credentials: ['server_key' => ...] — boleh dari form (belum disimpan).
     *
     * @return array{ok: bool, message: string}
     */
    public static function testConnection(array $credentials = []): array
    {
        $serverKey = $credentials['server_key'] ?? self::serverKey();

        if (! filled($serverKey)) {
            return ['ok' => false, 'message' => 'Server Key belum diisi.'];
        }

        $base = self::isProduction()
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        try {
            $response = Http::withBasicAuth((string) $serverKey, '')
                ->timeout(15)
                ->get($base.'/v2/POSME-TEST-KEY-CHECK/status');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Midtrans: '.$e->getMessage()];
        }

        return match ($response->status()) {
            401 => ['ok' => false, 'message' => 'Server Key salah (401 Unauthorized). Periksa kembali key-nya.'],
            404 => ['ok' => true, 'message' => 'Koneksi berhasil — Server Key valid (order dummy tidak ditemukan, seperti yang diharapkan).'],
            default => ['ok' => true, 'message' => 'Koneksi berhasil — Midtrans menjawab HTTP '.$response->status().'.'],
        };
    }

    /**
     * Verifikasi signature_key dari notifikasi Midtrans.
     * Rumus resmi: sha512(order_id + status_code + gross_amount + server_key).
     */
    public static function verifySignature(array $payload): bool
    {
        $serverKey = self::serverKey();

        if (! filled($serverKey)) {
            return false;
        }

        $expected = hash('sha512', ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').$serverKey);

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }
}
