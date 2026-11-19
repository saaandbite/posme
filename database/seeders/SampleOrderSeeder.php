<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;

/**
 * Beberapa order contoh (kemarin & hari ini) supaya laporan admin
 * tidak kosong saat pertama kali dibuka.
 */
class SampleOrderSeeder extends Seeder
{
    public function run(): void
    {
        $items = MenuItem::all();
        if ($items->isEmpty()) {
            return;
        }

        $tables = DiningTable::all();
        if ($tables->isEmpty()) {
            return;
        }

        $combos = [
            ['created' => now()->subDay()->setTime(12, 5), 'paid' => true, 'method' => PaymentMethod::Cash],
            ['created' => now()->subDay()->setTime(18, 40), 'paid' => true, 'method' => PaymentMethod::Qris],
            ['created' => now()->subHours(3), 'paid' => true, 'method' => PaymentMethod::Cash],
            ['created' => now()->subHours(1), 'paid' => true, 'method' => PaymentMethod::Midtrans],
        ];

        $seqPerDate = [];

        foreach ($combos as $i => $combo) {
            $dateKey = $combo['created']->format('Ymd');
            $seqPerDate[$dateKey] = ($seqPerDate[$dateKey] ?? 0) + 1;

            $order = Order::create([
                'dining_table_id' => $tables[$i % $tables->count()]->id,
                'order_number' => 'POS-'.$dateKey.'-'.str_pad((string) $seqPerDate[$dateKey], 3, '0', STR_PAD_LEFT),
                'status' => OrderStatus::Served,
                'payment_status' => PaymentStatus::Paid,
                'payment_method' => $combo['method'],
                'subtotal' => 0,
                'tax_amount' => 0,
                'service_amount' => 0,
                'total' => 0,
                'paid_amount' => 0,
                'change_amount' => 0,
                'paid_at' => $combo['created']->copy()->addMinutes(10),
                'served_at' => $combo['created']->copy()->addMinutes(8),
            ]);

            // created_at/updated_at bukan fillable, jadi diset langsung.
            $order->timestamps = false;
            $order->created_at = $combo['created'];
            $order->updated_at = $combo['created'];
            $order->save();

            $picked = $items->random(min(2, $items->count()));
            foreach ($picked as $menuItem) {
                $qty = rand(1, 2);
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'price' => $menuItem->price,
                    'qty' => $qty,
                    'subtotal' => $menuItem->price * $qty,
                ]);
            }

            $order->recalculateTotals();
            $order->update([
                'paid_amount' => $order->total,
                'change_amount' => 0,
            ]);
        }
    }
}
