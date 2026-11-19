<?php

namespace App\Livewire;

use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Layar dapur (KDS): board order aktif yang auto-refresh.
 * Hanya role kitchen (dan admin) yang bisa membuka halaman ini.
 */
class KitchenBoard extends Component
{
    public function advanceOrder(int $orderId): void
    {
        $order = Order::find($orderId);
        if (! $order || $order->status === Order::STATUS_CANCELLED) {
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
