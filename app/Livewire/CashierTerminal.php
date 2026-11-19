<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Setting;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Terminal kasir — kasir merangkap waiter:
 * 1. Monitor order live semua meja (status order + status bayar).
 * 2. Terminal pembayaran (tunai/QRIS manual, kembalian otomatis).
 * 3. Riwayat transaksi hari ini.
 */
class CashierTerminal extends Component
{
    public string $tab = 'monitor'; // monitor | terminal | history

    public ?int $selectedOrderId = null;

    public string $paymentMethod = 'cash';

    public ?int $cashReceived = null;

    public string $cancelReason = '';

    public bool $showCancelForm = false;

    public ?int $lastPaidOrderId = null;

    public function selectOrder(int $orderId): void
    {
        $this->selectedOrderId = $orderId;
        $this->tab = 'terminal';
        $this->showCancelForm = false;
        $this->cancelReason = '';
        $this->cashReceived = null;
        $this->lastPaidOrderId = null;
    }

    /** Kasir/waiter menandai order sudah diantar ke meja. */
    public function markServed(int $orderId): void
    {
        $order = Order::find($orderId);
        if (! $order || $order->status !== Order::STATUS_READY) {
            return;
        }

        $order->advanceStatus();
    }

    #[Computed]
    public function monitorOrders()
    {
        return Order::with(['items', 'diningTable'])
            ->today()
            ->whereNotIn('status', [Order::STATUS_CANCELLED])
            ->where(function ($q) {
                $q->where('status', '!=', Order::STATUS_SERVED)
                    ->orWhere('payment_status', '!=', Order::PAYMENT_PAID);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function selectedOrder(): ?Order
    {
        if (! $this->selectedOrderId) {
            return null;
        }

        return Order::with(['items', 'diningTable'])->find($this->selectedOrderId);
    }

    #[Computed]
    public function change(): ?int
    {
        $order = $this->selectedOrder;
        if (! $order || $this->paymentMethod !== 'cash' || $this->cashReceived === null) {
            return null;
        }

        return $this->cashReceived - $order->total;
    }

    public function confirmPayment(): void
    {
        $order = $this->selectedOrder;

        if (! $order || ! $order->canBePaid()) {
            $this->addError('payment', 'Order ini tidak bisa dibayar (sudah lunas atau dibatalkan).');

            return;
        }

        $settings = Setting::current();
        $allowed = [];
        if ($settings->accept_cash) {
            $allowed[] = 'cash';
        }
        if ($settings->accept_qris) {
            $allowed[] = 'qris';
        }

        $this->validate([
            'paymentMethod' => ['required', 'in:'.implode(',', $allowed)],
            'cashReceived' => ['required_if:paymentMethod,cash', 'nullable', 'integer', 'min:0'],
        ], [
            'cashReceived.required_if' => 'Masukkan uang yang diterima.',
        ]);

        if ($this->paymentMethod === 'cash' && $this->cashReceived < $order->total) {
            $this->addError('payment', 'Uang diterima kurang dari total ('.rupiah($order->total).').');

            return;
        }

        $paidAmount = $this->paymentMethod === 'cash' ? (int) $this->cashReceived : $order->total;

        $order->markPaidManually($this->paymentMethod, $paidAmount, auth()->id());
        $this->lastPaidOrderId = $order->id;
        $this->cashReceived = null;
    }

    public function cancelOrder(): void
    {
        $order = $this->selectedOrder;

        if (! $order || ! $order->canBeCancelled()) {
            $this->addError('payment', 'Order ini tidak bisa dibatalkan (sudah lunas).');

            return;
        }

        $this->validate([
            'cancelReason' => ['required', 'string', 'max:200'],
        ], [
            'cancelReason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_reason' => $this->cancelReason,
        ]);

        $this->showCancelForm = false;
        $this->cancelReason = '';
    }

    #[Computed]
    public function history()
    {
        return Order::with(['items', 'diningTable'])
            ->today()
            ->where(function ($q) {
                $q->where('payment_status', Order::PAYMENT_PAID)
                    ->orWhere('status', Order::STATUS_CANCELLED);
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        return view('livewire.cashier-terminal', [
            'settings' => Setting::current(),
        ])->layout('layouts.app', ['title' => 'Kasir']);
    }
}
