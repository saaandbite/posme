<?php

namespace App\Enums;

/** Provider payment gateway yang aktif di resto. */
enum GatewayProvider: string
{
    case None = 'none';
    case Midtrans = 'midtrans';
    case Xendit = 'xendit';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tidak ada (bayar di kasir saja)',
            self::Midtrans => 'Midtrans',
            self::Xendit => 'Xendit',
        };
    }

    /** @return array<string, string> nilai => label (untuk Select). */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
