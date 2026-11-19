<?php

namespace App\Services;

use App\Enums\GatewayProvider;
use App\Models\Order;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pembayaran lewat Xendit Invoice (QRIS / VA / e-wallet / retail).
 * Dipakai HTTP client Laravel langsung, tanpa SDK.
 *
 * Base URL sama untuk sandbox & produksi (yang membedakan adalah key).
 * Dokumen: https://developers.xendit.co/api-reference/#create-invoice
 */
class XenditService
{
    protected const API_BASE = 'https://api.xendit.co';

    public static function configured(): bool
    {
        return PaymentGateway::isConfigured(GatewayProvider::Xendit);
    }

    protected static function secretKey(): ?string
    {
        $key = PaymentGateway::credentialsFor(GatewayProvider::Xendit)['secret_key'] ?? null;

        return filled($key) ? (string) $key : null;
    }

    protected static function callbackToken(): ?string
    {
        $token = PaymentGateway::credentialsFor(GatewayProvider::Xendit)['callback_token'] ?? null;

        return filled($token) ? (string) $token : null;
    }

    protected static function client(?string $secretKey = null): \Illuminate\Http\Client\PendingRequest
    {
        $key = $secretKey ?? self::secretKey();

        return Http::withBasicAuth((string) $key, '')
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * Tes koneksi: GET /balance. 200 = key valid; 401 = key salah.
     * $credentials: ['secret_key' => ...] — boleh dari form (belum disimpan).
     *
     * @return array{ok: bool, message: string}
     */
    public static function testConnection(array $credentials = []): array
    {
        $secretKey = $credentials['secret_key'] ?? self::secretKey();

        if (! filled($secretKey)) {
            return ['ok' => false, 'message' => 'Secret Key belum diisi.'];
        }

        try {
            $response = self::client($secretKey)->get(self::API_BASE.'/balance');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Xendit: '.$e->getMessage()];
        }

        return match ($response->status()) {
            200 => ['ok' => true, 'message' => 'Koneksi berhasil — Secret Key valid.'],
            401 => ['ok' => false, 'message' => 'Secret Key salah (401 Unauthorized). Periksa kembali key-nya.'],
            default => ['ok' => false, 'message' => 'Xendit menjawab HTTP '.$response->status().'. Coba lagi nanti.'],
        };
    }

    /**
     * Buat Invoice Xendit untuk sebuah order. Mengembalikan URL invoice
     * (customer diarahkan ke sana), atau null bila gagal / belum dikonfigurasi.
     */
    public static function createInvoice(Order $order): ?string
    {
        if (! self::configured()) {
            return null;
        }

        if ($order->xendit_invoice_id) {
            // Invoice sudah dibuat sebelumnya — ambil lagi URL-nya.
            return self::invoiceUrl($order->xendit_invoice_id);
        }

        $order->loadMissing('diningTable');
        $returnUrl = $order->diningTable->orderUrl();

        try {
            $response = self::client()->post(self::API_BASE.'/v2/invoices', [
                'external_id' => $order->order_number,
                'amount' => (int) $order->total,
                'description' => 'Pembayaran order '.$order->order_number,
                'success_redirect_url' => $returnUrl,
                'failure_redirect_url' => $returnUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('Xendit create invoice gagal: '.$e->getMessage(), ['order' => $order->order_number]);

            return null;
        }

        if (! $response->successful()) {
            Log::error('Xendit create invoice ditolak.', [
                'order' => $order->order_number,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        $order->update(['xendit_invoice_id' => $data['id'] ?? null]);

        return $data['invoice_url'] ?? null;
    }

    /** Ambil ulang URL invoice yang sudah dibuat. */
    protected static function invoiceUrl(string $invoiceId): ?string
    {
        try {
            $response = self::client()->get(self::API_BASE.'/v2/invoices/'.$invoiceId);
        } catch (\Throwable) {
            return null;
        }

        return $response->successful() ? ($response->json('invoice_url') ?? null) : null;
    }

    /**
     * Verifikasi header x-callback-token dari webhook Xendit.
     * Token yang tersimpan HARUS cocok — bila tidak ada token tersimpan,
     * webhook ditolak (jangan pernah menerima tanpa verifikasi).
     */
    public static function verifyCallbackToken(?string $headerToken): bool
    {
        $stored = self::callbackToken();

        if (! filled($stored) || ! filled($headerToken)) {
            return false;
        }

        return hash_equals($stored, $headerToken);
    }
}
