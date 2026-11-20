<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Layar dapur (KDS): board order aktif yang auto-refresh.
 * Hanya role kitchen (dan admin) yang bisa membuka halaman ini.
 */
class KitchenBoard extends Component
{
    /**
     * Pertahanan berlapis: route sudah dibatasi middleware role, tapi
     * request Livewire berikutnya tidak melewati mount ulang — jadi role
     * diperiksa lagi di sini dan di tiap aksi sensitif.
     */
    public function mount(): void
    {
        $this->authorizeKitchen();
    }

    protected function authorizeKitchen(): void
    {
        $user = auth()->user();

        if (! $user || ! in_array($user->role, [UserRole::Kitchen, UserRole::Admin], true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman dapur.');
        }
    }

    public function advanceOrder(int $orderId): void
    {
        $this->authorizeKitchen();

        $order = Order::find($orderId);
        if (! $order || $order->status === OrderStatus::Cancelled) {
            return;
        }

        $order->advanceStatus();
    }

    #[Computed]
    public function orders()
    {
        return Order::with(['items', 'diningTable'])
            ->active()
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function maxOrderId(): int
    {
        return (int) Order::max('id');
    }

    public function render()
    {
        return view('livewire.kitchen-board')->layout('layouts.app', ['title' => 'Dapur']);
    }
}
