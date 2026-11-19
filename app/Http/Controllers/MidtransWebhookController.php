<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    /**
     * Terima notifikasi pembayaran dari Midtrans.
     * Route ini dikecualikan dari CSRF (server-to-server), jadi verifikasi
     * signature_key WAJIB lolos sebelum status order apa pun diubah.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! MidtransService::verifySignature($payload)) {
            Log::warning('Midtrans webhook: signature tidak valid.', [
                'order_id' => $payload['order_id'] ?? null,
            ]);

            return response()->json(['message' => 'Signature tidak valid.'], 403);
        }

        $order = Order::where('order_number', $payload['order_id'] ?? '')->first();

        if (! $order) {
            Log::warning('Midtrans webhook: order tidak ditemukan.', [
                'order_id' => $payload['order_id'] ?? null,
            ]);

            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        // Jangan proses ulang order yang sudah lunas.
        if ($order->payment_status === Order::PAYMENT_PAID) {
            return response()->json(['message' => 'Sudah lunas.']);
        }

        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus = $payload['fraud_status'] ?? null;

        match ($transactionStatus) {
            'capture' => $this->handleCapture($order, $fraudStatus, $payload),
            'settlement' => $this->markPaid($order, $payload),
            'pending' => $order->update(['payment_status' => Order::PAYMENT_WAITING]),
            'deny', 'cancel', 'expire', 'failure' => $order->update([
                'payment_status' => Order::PAYMENT_UNPAID,
                'snap_token' => null,
            ]),
            default => null,
        };

        Log::info('Midtrans webhook diproses.', [
            'order' => $order->order_number,
            'transaction_status' => $transactionStatus,
            'payment_status' => $order->fresh()->payment_status,
        ]);

        return response()->json(['message' => 'OK']);
    }

    protected function handleCapture(Order $order, ?string $fraudStatus, array $payload): void
    {
        // Kartu kredit: hanya lunas bila fraud_status = accept.
        if ($fraudStatus === 'accept') {
            $this->markPaid($order, $payload);
        } elseif ($fraudStatus === 'challenge') {
            $order->update(['payment_status' => Order::PAYMENT_WAITING]);
        }
    }

    protected function markPaid(Order $order, array $payload): void
    {
        $order->update([
            'payment_status' => Order::PAYMENT_PAID,
            'payment_method' => 'midtrans',
            'midtrans_payment_type' => $payload['payment_type'] ?? null,
            'paid_amount' => (int) ($payload['gross_amount'] ?? $order->total),
            'change_amount' => 0,
            'paid_at' => now(),
        ]);
    }
}
