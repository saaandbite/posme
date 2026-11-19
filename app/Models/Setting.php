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
        'menu_layout', 'menu_default_show_image', 'menu_default_show_description',
    ];

    protected function casts(): array
    {
        return [
            'tax_percent' => 'decimal:2',
            'service_percent' => 'decimal:2',
            'accept_cash' => 'boolean',
            'accept_qris' => 'boolean',
            'menu_default_show_image' => 'boolean',
            'menu_default_show_description' => 'boolean',
        ];
    }

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
                'menu_layout' => 'grid',
                'menu_default_show_image' => true,
                'menu_default_show_description' => true,
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
