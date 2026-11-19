<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;

/**
 * Pembayaran lewat Midtrans Snap (QRIS / VA / e-wallet).
 *
 * Gateway bersifat OPSIONAL: bila MIDTRANS_SERVER_KEY / MIDTRANS_CLIENT_KEY
 * belum diisi, configured() bernilai false dan aplikasi memakai fallback
 * "Bayar di kasir" — tidak ada error karena key kosong.
 */
class MidtransService
{
    public static function configured(): bool
    {
        return filled(config('services.midtrans.server_key'))
            && filled(config('services.midtrans.client_key'));
    }

    public static function isProduction(): bool
    {
        return (bool) config('services.midtrans.is_production', false);
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
        return config('services.midtrans.client_key') ?: null;
    }

    protected static function boot(): void
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = self::isProduction();
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Buat (atau pakai ulang) Snap token untuk sebuah order.
     * Mengembalikan null bila gateway belum dikonfigurasi.
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
     * Verifikasi signature_key dari notifikasi Midtrans.
     * Rumus resmi: sha512(order_id + status_code + gross_amount + server_key).
     */
    public static function verifySignature(array $payload): bool
    {
        $serverKey = config('services.midtrans.server_key');

        if (! filled($serverKey)) {
            return false;
        }

        $expected = hash('sha512', ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').$serverKey);

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }
}
