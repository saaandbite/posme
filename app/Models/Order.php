<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'dining_table_id', 'order_number', 'status', 'payment_status',
        'payment_method', 'midtrans_payment_type', 'snap_token',
        'xendit_invoice_id',
        'subtotal', 'tax_amount', 'service_amount', 'total',
        'paid_amount', 'change_amount', 'customer_note',
        'cancelled_reason', 'paid_at', 'served_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal' => 'integer',
            'tax_amount' => 'integer',
            'service_amount' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'change_amount' => 'integer',
            'paid_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Nomor order unik harian, mis. POS-20261120-001.
     */
    public static function generateNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = 'POS-'.$date.'-';

        $last = static::where('order_number', 'like', $prefix.'%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Hitung ulang subtotal/pajak/service/total dari item-item order.
     * Harga SELALU diambil dari database (snapshot di order_items),
     * tidak pernah dari request customer.
     */
    public function recalculateTotals(): void
    {
        $subtotal = (int) $this->items()->sum('subtotal');
        $settings = Setting::current();

        $tax = (int) round($subtotal * ((float) $settings->tax_percent / 100));
        $service = (int) round($subtotal * ((float) $settings->service_percent / 100));

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'service_amount' => $service,
            'total' => $subtotal + $tax + $service,
        ]);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canBePaid(): bool
    {
        return $this->status !== OrderStatus::Cancelled
            && $this->payment_status !== PaymentStatus::Paid;
    }

    public function canBeCancelled(): bool
    {
        return $this->status !== OrderStatus::Cancelled
            && $this->payment_status !== PaymentStatus::Paid;
    }

    public function nextStatus(): ?OrderStatus
    {
        return $this->status->next();
    }

    /** Majukan status satu langkah (dipakai dapur & kasir/waiter). */
    public function advanceStatus(): bool
    {
        $next = $this->nextStatus();

        if (! $next) {
            return false;
        }

        $this->update(['status' => $next]);

        if ($next === OrderStatus::Served) {
            $this->update(['served_at' => now()]);
        }

        return true;
    }

    /** Tandai lunas lewat pembayaran manual kasir (tunai/QRIS). */
    public function markPaidManually(PaymentMethod $method, int $paidAmount, ?int $userId = null): void
    {
        $this->update([
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => $method,
            'paid_amount' => $paidAmount,
            'change_amount' => max(0, $paidAmount - $this->total),
            'paid_at' => now(),
        ]);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [OrderStatus::Served, OrderStatus::Cancelled]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
