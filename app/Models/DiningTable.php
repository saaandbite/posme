<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    protected $fillable = ['name', 'code', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Kode unik dibuat otomatis bila kosong (dipakai di URL QR).
        static::creating(function (DiningTable $table) {
            if (empty($table->code)) {
                $table->code = strtoupper(Str::random(6));
            }
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** URL halaman order customer untuk meja ini (harus dibuat via signedRoute). */
    public function orderUrl(): string
    {
        return \Illuminate\Support\Facades\URL::signedRoute('customer.menu', ['table' => $this->code]);
    }

    /** Order aktif di meja ini (belum disajikan dan belum dibatalkan). */
    public function activeOrders(): HasMany
    {
        return $this->orders()->whereNotIn('status', ['served', 'cancelled']);
    }
}
