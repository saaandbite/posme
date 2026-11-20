<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    protected $fillable = ['name', 'code', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Kode unik dibuat otomatis bila kosong (identifikasi internal).
        static::creating(function (DiningTable $table) {
            if (empty($table->code)) {
                $table->code = strtoupper(Str::random(6));
            }
            if (empty($table->token)) {
                $table->token = static::generateToken();
            }
        });

        // Pengaman: token tidak boleh kosong meski diubah lewat update.
        static::saving(function (DiningTable $table) {
            if (empty($table->token)) {
                $table->token = static::generateToken();
            }
        });
    }

    /** Token acak 32 karakter (URL-safe) yang belum dipakai meja lain. */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(32);
        } while (static::where('token', $token)->exists());

        return $token;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * URL halaman order customer untuk meja ini.
     *
     * Memakai token acak + signature sementara (kedaluwarsa) — bukan
     * kode meja yang bisa ditebak. Signature hanya dicek saat pertama
     * membuka link; sesi Livewire yang sudah terbuka tetap jalan.
     */
    public function orderUrl(): string
    {
        return URL::temporarySignedRoute(
            'customer.menu',
            $this->qrExpiresAt(),
            ['table' => $this->token]
        );
    }

    /** Tanggal kedaluwarsa URL QR yang dibuat hari ini. */
    public function qrExpiresAt(): Carbon
    {
        return now()->addDays((int) config('posme.qr_url_expiry_days', 30));
    }

    /**
     * Putar token meja: URL QR lama langsung tidak berlaku (token lama
     * tidak ditemukan lagi), QR harus dicetak ulang. Order yang sedang
     * berjalan tidak terganggu — order sudah terikat ke id meja, bukan
     * ke token.
     */
    public function rotateToken(): static
    {
        // Set langsung + save — token sengaja tidak fillable.
        $this->token = static::generateToken();
        $this->save();

        return $this;
    }

    /** Order aktif di meja ini (belum disajikan dan belum dibatalkan). */
    public function activeOrders(): HasMany
    {
        return $this->orders()->whereNotIn('status', [OrderStatus::Served, OrderStatus::Cancelled]);
    }
}
