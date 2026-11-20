<?php

namespace App\Enums;

/** Provider deploy yang bisa diatur dari dashboard (Pengaturan Deploy). */
enum DeployProvider: string
{
    case None = 'none';
    case Forge = 'forge';
    case Coolify = 'coolify';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tidak ada',
            self::Forge => 'Laravel Forge',
            self::Coolify => 'Coolify (self-hosted)',
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
