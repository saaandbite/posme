<?php

namespace App\Services;

use App\Enums\GatewayProvider;
use App\Models\GatewaySetting;

/**
 * Penentu gateway aktif — satu-satunya tempat yang memutuskan provider
 * mana yang dipakai untuk pembayaran customer.
 *
 * Sumber kredensial: database (diatur owner dari dashboard) didahulukan,
 * .env (MIDTRANS_* / XENDIT_*) sebagai fallback bila database kosong.
 * Jadi instalasi lama yang hanya memakai .env tetap jalan tanpa perubahan.
 */
class PaymentGateway
{
    /** Provider yang sedang aktif, null bila owner memilih "tidak ada". */
    public static function activeProvider(): ?GatewayProvider
    {
        return GatewaySetting::active()?->provider;
    }

    /** Kredensial provider: database dulu, env sebagai fallback. */
    public static function credentialsFor(GatewayProvider $provider): array
    {
        $row = GatewaySetting::query()->where('provider', $provider)->first();
        $fromDb = $row?->credentials ?? [];

        $fromEnv = match ($provider) {
            GatewayProvider::Midtrans => [
                'server_key' => config('services.midtrans.server_key'),
                'client_key' => config('services.midtrans.client_key'),
            ],
            GatewayProvider::Xendit => [
                'secret_key' => config('services.xendit.secret_key'),
                'callback_token' => config('services.xendit.callback_token'),
            ],
            default => [],
        };

        $merged = [];
        foreach ($fromEnv as $key => $envValue) {
            $merged[$key] = filled($fromDb[$key] ?? null) ? $fromDb[$key] : $envValue;
        }
        foreach ($fromDb as $key => $value) {
            $merged[$key] = $merged[$key] ?? $value;
        }

        return $merged;
    }

    /** Mode produksi/sandbox: database dulu, env sebagai fallback. */
    public static function isProduction(GatewayProvider $provider): bool
    {
        $row = GatewaySetting::query()->where('provider', $provider)->first();

        if ($row) {
            return $row->is_production;
        }

        return (bool) config('services.midtrans.is_production', false);
    }

    /**
     * Apakah provider ini bisa dipakai? Kredensial harus lengkap DAN
     * provider ini yang aktif (atau belum ada yang dipilih sama sekali —
     * instalasi lama berbasis .env tetap jalan).
     */
    public static function isConfigured(GatewayProvider $provider): bool
    {
        $active = self::activeProvider();

        if ($active !== null && $active !== $provider) {
            return false;
        }

        $creds = self::credentialsFor($provider);

        return match ($provider) {
            GatewayProvider::Midtrans => filled($creds['server_key'] ?? null)
                && filled($creds['client_key'] ?? null),
            GatewayProvider::Xendit => filled($creds['secret_key'] ?? null),
            default => false,
        };
    }

    /** Provider aktif DAN terkonfigurasi — siap dipakai customer untuk bayar. */
    public static function usable(): ?GatewayProvider
    {
        $provider = self::activeProvider();

        if ($provider === null || $provider === GatewayProvider::None) {
            return null;
        }

        return self::isConfigured($provider) ? $provider : null;
    }
}
