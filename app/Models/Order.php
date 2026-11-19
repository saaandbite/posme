<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_SERVED = 'served';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Dipesan',
        self::STATUS_PREPARING => 'Disiapkan',
        self::STATUS_READY => 'Siap',
        self::STATUS_SERVED => 'Disajikan',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    /** Urutan alur status dapur. */
    public const FLOW = [
        self::STATUS_PENDING,
        self::STATUS_PREPARING,
        self::STATUS_READY,
        self::STATUS_SERVED,
    ];

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_WAITING = 'waiting';
    public const PAYMENT_PAID = 'paid';

    protected $fillable = [
        'dining_table_id', 'order_number', 'status', 'payment_status',
        'payment_method', 'midtrans_payment_type', 'snap_token',
        'subtotal', 'tax_amount', 'service_amount', 'total',
        'paid_amount', 'change_amount', 'customer_note',
        'cancelled_reason', 'paid_at', 'served_at',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'service_amount' => 'integer',
        'total' => 'integer',
        'paid_amount' => 'integer',
        'change_amount' => 'integer',
        'paid_at' => 'datetime',
        'served_at' => 'datetime',
    ];

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
        return ! in_array($this->status, [self::STATUS_SERVED, self::STATUS_CANCELLED], true);
    }

    public function canBePaid(): bool
    {
        return $this->status !== self::STATUS_CANCELLED
            && $this->payment_status !== self::PAYMENT_PAID;
    }

    public function canBeCancelled(): bool
    {
        return $this->status !== self::STATUS_CANCELLED
            && $this->payment_status !== self::PAYMENT_PAID;
    }

    public function nextStatus(): ?string
    {
        $idx = array_search($this->status, self::FLOW, true);

        if ($idx === false || $idx >= count(self::FLOW) - 1) {
            return null;
        }

        return self::FLOW[$idx + 1];
    }

    /** Majukan status satu langkah (dipakai dapur & kasir/waiter). */
    public function advanceStatus(): bool
    {
        $next = $this->nextStatus();

        if (! $next) {
            return false;
        }

        $this->update(['status' => $next]);

        if ($next === self::STATUS_SERVED) {
            $this->update(['served_at' => now()]);
        }

        return true;
    }

    /** Tandai lunas lewat pembayaran manual kasir (tunai/QRIS). */
    public function markPaidManually(string $method, int $paidAmount, ?int $userId = null): void
    {
        $this->update([
            'payment_status' => self::PAYMENT_PAID,
            'payment_method' => $method,
            'paid_amount' => $paidAmount,
            'change_amount' => max(0, $paidAmount - $this->total),
            'paid_at' => now(),
        ]);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_SERVED, self::STATUS_CANCELLED]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
