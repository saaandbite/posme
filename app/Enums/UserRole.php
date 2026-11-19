<?php

namespace App\Enums;

/** Role pengguna staf. */
enum UserRole: string
{
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Kitchen = 'kitchen';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Cashier => 'Kasir',
            self::Kitchen => 'Dapur',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Cashier => 'warning',
            self::Kitchen => 'info',
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
