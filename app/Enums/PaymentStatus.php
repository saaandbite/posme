<?php

namespace App\Enums;

/** Status pembayaran sebuah order. */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Waiting = 'waiting';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum bayar',
            self::Waiting => 'Menunggu pembayaran',
            self::Paid => 'Lunas',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::Waiting => 'warning',
            self::Paid => 'success',
        };
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
