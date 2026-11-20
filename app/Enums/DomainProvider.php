<?php

namespace App\Enums;

/** Provider domain yang bisa diatur dari dashboard (Pengaturan Deploy). */
enum DomainProvider: string
{
    case None = 'none';
    case Cloudflare = 'cloudflare';
    case Porkbun = 'porkbun';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tidak ada',
            self::Cloudflare => 'Cloudflare',
            self::Porkbun => 'Porkbun',
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
