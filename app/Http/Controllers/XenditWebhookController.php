<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\XenditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    /**
     * Terima notifikasi pembayaran dari Xendit.
     * Route ini dikecualikan dari CSRF (server-to-server), jadi verifikasi
     * header x-callback-token WAJIB lolos sebelum status order apa pun diubah.
     */
    public function handle(Request $request): JsonResponse
    {
        if (! XenditService::verifyCallbackToken($request->header('x-callback-token'))) {
            Log::warning('Xendit webhook: callback token tidak valid.');

            return response()->json(['message' => 'Token tidak valid.'], 403);
        }

        $payload = $request->all();

        $order = Order::where('order_number', $payload['external_id'] ?? '')->first();

        if (! $order) {
            Log::warning('Xendit webhook: order tidak ditemukan.', [
                'external_id' => $payload['external_id'] ?? null,
            ]);

            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        // Jangan proses ulang order yang sudah lunas.
        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Sudah lunas.']);
        }

        match ($payload['status'] ?? '') {
            'PAID' => $this->markPaid($order, $payload),
            'EXPIRED' => $order->update([
                'payment_status' => PaymentStatus::Unpaid,
                'xendit_invoice_id' => null,
            ]),
            default => null,
        };

        Log::info('Xendit webhook diproses.', [
            'order' => $order->order_number,
            'status' => $payload['status'] ?? null,
            'payment_status' => $order->fresh()->payment_status,
        ]);

        return response()->json(['message' => 'OK']);
    }

    protected function markPaid(Order $order, array $payload): void
    {
        $order->update([
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Xendit,
            'xendit_invoice_id' => $payload['id'] ?? $order->xendit_invoice_id,
            'paid_amount' => (int) ($payload['amount'] ?? $order->total),
            'change_amount' => 0,
            'paid_at' => now(),
        ]);
    }
}
