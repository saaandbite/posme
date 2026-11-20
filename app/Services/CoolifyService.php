<?php

namespace App\Services;

use App\Enums\DeployProvider;
use App\Models\DeploySetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Deploy lewat Coolify (self-hosted, gratis) — API v1 di instance
 * Coolify milik owner sendiri (mis. https://coolify.domain-anda).
 *
 * Owner butuh VPS yang sudah terpasang Coolify + API token dari
 * instance tersebut (Keys & Tokens → API Tokens). Deploy di Coolify
 * berjalan async — endpoint mengembalikan penerimaan, bukan hasil akhir.
 *
 * Dokumen: https://coolify.io/docs/api-reference
 */
class CoolifyService
{
    /** @return array{ok: bool, message: string, data?: mixed} */
    public static function testConnection(array $credentials = []): array
    {
        $baseUrl = rtrim($credentials['base_url'] ?? self::baseUrl() ?? '', '/');
        $token = $credentials['api_token'] ?? self::apiToken();

        if (! filled($baseUrl)) {
            return ['ok' => false, 'message' => 'URL instance Coolify belum diisi.'];
        }

        if (! filled($token)) {
            return ['ok' => false, 'message' => 'API Token Coolify belum diisi.'];
        }

        try {
            $response = self::client($baseUrl, $token)->get($baseUrl.'/api/v1/version');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Coolify: '.$e->getMessage()];
        }

        return match ($response->status()) {
            200 => ['ok' => true, 'message' => 'Koneksi berhasil — Coolify versi '.($response->json('version') ?? '—').'.'],
            401 => ['ok' => false, 'message' => 'API Token salah (401 Unauthorized). Periksa kembali tokennya.'],
            default => ['ok' => false, 'message' => 'Coolify menjawab HTTP '.$response->status().'. Coba lagi nanti.'],
        };
    }

    /** @return array{ok: bool, message: string, applications?: array<int, array>} */
    public static function listApplications(): array
    {
        $baseUrl = rtrim(self::baseUrl() ?? '', '/');
        $token = self::apiToken();

        if (! filled($baseUrl) || ! filled($token)) {
            return ['ok' => false, 'message' => 'Kredensial Coolify belum diisi.'];
        }

        try {
            $response = self::client($baseUrl, $token)->get($baseUrl.'/api/v1/applications');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Coolify: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Coolify menjawab HTTP '.$response->status().'.'];
        }

        return ['ok' => true, 'message' => 'Daftar aplikasi dimuat.', 'applications' => $response->json() ?? []];
    }

    /** @return array{ok: bool, message: string} */
    public static function deployApplication(string $uuid): array
    {
        $baseUrl = rtrim(self::baseUrl() ?? '', '/');
        $token = self::apiToken();

        if (! filled($baseUrl) || ! filled($token)) {
            return ['ok' => false, 'message' => 'Kredensial Coolify belum diisi.'];
        }

        try {
            $response = self::client($baseUrl, $token)->post($baseUrl.'/api/v1/applications/'.$uuid.'/deploy');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Coolify: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            Log::error('Coolify deploy ditolak.', ['status' => $response->status(), 'body' => $response->body()]);

            return ['ok' => false, 'message' => 'Coolify menolak deploy (HTTP '.$response->status().').'];
        }

        return ['ok' => true, 'message' => 'Deploy dimulai untuk aplikasi '.$uuid.'. Pantau di dashboard Coolify sampai selesai.'];
    }

    protected static function baseUrl(): ?string
    {
        $row = DeploySetting::query()->where('provider', DeployProvider::Coolify->value)->first();

        return $row?->credential('base_url');
    }

    protected static function apiToken(): ?string
    {
        $row = DeploySetting::query()->where('provider', DeployProvider::Coolify->value)->first();

        return $row?->credential('api_token');
    }

    protected static function client(string $baseUrl, ?string $token = null): PendingRequest
    {
        return Http::withToken((string) ($token ?? self::apiToken()))
            ->acceptJson()
            ->timeout(30);
    }
}
