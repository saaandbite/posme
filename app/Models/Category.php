<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /** Item menu yang aktif dan tersedia di kategori ini. */
    public function availableItems(): HasMany
    {
        return $this->menuItems()->where('is_active', true)->where('is_available', true)->orderBy('sort_order');
    }
}
