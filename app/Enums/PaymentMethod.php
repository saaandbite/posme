<?php

namespace App\Enums;

/** Cara bayar sebuah order. */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Midtrans = 'midtrans';
    case Xendit = 'xendit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Qris => 'QRIS',
            self::Midtrans => 'Gateway (Midtrans)',
            self::Xendit => 'Gateway (Xendit)',
        };
    }

    /** Metode gateway (online) atau manual (kasir). */
    public function isGateway(): bool
    {
        return in_array($this, [self::Midtrans, self::Xendit], true);
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
