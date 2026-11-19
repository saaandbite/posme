<?php

namespace App\Enums;

/** Status alur pesanan di dapur. */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Dipesan',
            self::Preparing => 'Disiapkan',
            self::Ready => 'Siap',
            self::Served => 'Disajikan',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Warna badge (Filament / Tailwind). */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'danger',
            self::Preparing => 'warning',
            self::Ready => 'success',
            self::Served => 'gray',
            self::Cancelled => 'gray',
        };
    }

    /** Masih aktif (belum disajikan dan belum dibatalkan). */
    public function isActive(): bool
    {
        return ! in_array($this, [self::Served, self::Cancelled], true);
    }

    /** Status berikutnya dalam alur dapur, null bila sudah di ujung. */
    public function next(): ?self
    {
        return match ($this) {
            self::Pending => self::Preparing,
            self::Preparing => self::Ready,
            self::Ready => self::Served,
            default => null,
        };
    }

    /** @return self[] */
    public static function flow(): array
    {
        return [self::Pending, self::Preparing, self::Ready, self::Served];
    }

    /** @return array<string, string> nilai => label (untuk Select/Filter). */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
