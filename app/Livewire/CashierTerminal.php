<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Setting;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
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

    #[Validate('required|string|in:cash,qris')]
    public string $paymentMethod = 'cash';

    #[Validate('required_if:paymentMethod,cash|nullable|integer|min:0', message: 'Masukkan uang yang diterima.')]
    public ?int $cashReceived = null;

    #[Validate('required|string|max:200', message: 'Alasan pembatalan wajib diisi.')]
    public string $cancelReason = '';

    public bool $showCancelForm = false;

    public ?int $lastPaidOrderId = null;

    /**
     * Pertahanan berlapis: route sudah dibatasi middleware role, tapi
     * request Livewire berikutnya tidak melewati mount ulang — jadi role
     * diperiksa lagi di sini dan di tiap aksi sensitif.
     */
    public function mount(): void
    {
        $this->authorizeCashier();
    }

    protected function authorizeCashier(): void
    {
        $user = auth()->user();

        if (! $user || ! in_array($user->role, [UserRole::Cashier, UserRole::Admin], true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman kasir.');
        }
    }

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
        $this->authorizeCashier();

        $order = Order::find($orderId);
        if (! $order || $order->status !== OrderStatus::Ready) {
            return;
        }

        $order->advanceStatus();
    }

    #[Computed]
    public function monitorOrders()
    {
        return Order::with(['items', 'diningTable'])
            ->today()
            ->whereNotIn('status', [OrderStatus::Cancelled])
            ->where(function ($q) {
                $q->where('status', '!=', OrderStatus::Served)
                    ->orWhere('payment_status', '!=', PaymentStatus::Paid);
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
        $this->authorizeCashier();

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

        $this->validateOnly('paymentMethod');
        $this->validateOnly('cashReceived');

        if (! in_array($this->paymentMethod, $allowed, true)) {
            $this->addError('payment', 'Metode pembayaran ini tidak diaktifkan di Pengaturan.');

            return;
        }

        if ($this->paymentMethod === 'cash' && $this->cashReceived < $order->total) {
            $this->addError('payment', 'Uang diterima kurang dari total ('.rupiah($order->total).').');

            return;
        }

        $paidAmount = $this->paymentMethod === 'cash' ? (int) $this->cashReceived : $order->total;

        $order->markPaidManually(PaymentMethod::from($this->paymentMethod), $paidAmount, auth()->id());
        $this->lastPaidOrderId = $order->id;
        $this->cashReceived = null;
    }

    public function cancelOrder(): void
    {
        $this->authorizeCashier();

        $order = $this->selectedOrder;

        if (! $order || ! $order->canBeCancelled()) {
            $this->addError('payment', 'Order ini tidak bisa dibatalkan (sudah lunas).');

            return;
        }

        $this->validateOnly('cancelReason');

        $order->update([
            'status' => OrderStatus::Cancelled,
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
                $q->where('payment_status', PaymentStatus::Paid)
                    ->orWhere('status', OrderStatus::Cancelled);
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
