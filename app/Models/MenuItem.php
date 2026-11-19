<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    protected $fillable = [
        'category_id', 'name', 'description', 'price',
        'image', 'is_available', 'is_active', 'sort_order',
        'show_image', 'show_description', 'badge',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Tri-state: true / false / null (null = ikuti default global).
     * Cast boolean biasa akan mengubah null menjadi false, jadi dipakai
     * Attribute kustom agar null tetap null.
     */
    protected function showImage(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : (bool) $value,
            set: fn ($value) => $value === null || $value === '' ? null : (bool) $value,
        );
    }

    protected function showDescription(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : (bool) $value,
            set: fn ($value) => $value === null || $value === '' ? null : (bool) $value,
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    /**
     * Apakah foto item tampil di menu customer? Toggle per item mengalahkan
     * default global (null = ikuti default global).
     */
    public function showsImage(Setting $settings): bool
    {
        return $this->show_image ?? $settings->menu_default_show_image;
    }

    /** Apakah deskripsi item tampil di menu customer? (null = ikut default). */
    public function showsDescription(Setting $settings): bool
    {
        return $this->show_description ?? $settings->menu_default_show_description;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)->where('is_available', true);
    }
}
