<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan resto — satu baris (id = 1), diubah lewat halaman
 * Pengaturan di panel admin.
 */
class Setting extends Model
{
    protected $fillable = [
        'restaurant_name', 'tax_percent', 'service_percent',
        'accept_cash', 'accept_qris',
    ];

    protected $casts = [
        'tax_percent' => 'decimal:2',
        'service_percent' => 'decimal:2',
        'accept_cash' => 'boolean',
        'accept_qris' => 'boolean',
    ];

    /** Memo per proses (bukan cache file) — model Eloquent yang diserialisasi
     *  ke cache file bisa kembali sebagai __PHP_Incomplete_Class. */
    protected static ?self $cached = null;

    public static function current(): self
    {
        return static::$cached ??= static::firstOrCreate(
            ['id' => 1],
            [
                'restaurant_name' => 'Posme Cafe',
                'tax_percent' => 0,
                'service_percent' => 0,
                'accept_cash' => true,
                'accept_qris' => true,
            ]
        );
    }

    public static function flush(): void
    {
        static::$cached = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
    }
}
