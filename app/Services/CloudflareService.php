<?php

namespace App\Services;

use App\Enums\DomainProvider;
use App\Models\DeploySetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Domain lewat Cloudflare (https://api.cloudflare.com/client/v4).
 *
 * Owner butuh akun Cloudflare + API Token dengan izin Zone:DNS:Edit
 * untuk zone domainnya. Aksi yang didukung: verifikasi token dan
 * membuat DNS record A (domain → IP server). Pembelian/transfer domain
 * tidak lewat API ini — domain harus sudah ada di akun Cloudflare.
 *
 * Dokumen: https://developers.cloudflare.com/api/
 */
class CloudflareService
{
    protected const API_BASE = 'https://api.cloudflare.com/client/v4';

    /** @return array{ok: bool, message: string, data?: mixed} */
    public static function testConnection(array $credentials = []): array
    {
        $token = $credentials['api_token'] ?? self::apiToken();

        if (! filled($token)) {
            return ['ok' => false, 'message' => 'API Token Cloudflare belum diisi.'];
        }

        try {
            $response = self::client($token)->get(self::API_BASE.'/user/tokens/verify');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Cloudflare: '.$e->getMessage()];
        }

        $json = $response->json();

        if ($response->successful() && ($json['success'] ?? false)) {
            return ['ok' => true, 'message' => 'Koneksi berhasil — token valid.'];
        }

        if ($response->status() === 403) {
            return ['ok' => false, 'message' => 'API Token salah atau tidak punya izin (403). Periksa kembali tokennya.'];
        }

        return ['ok' => false, 'message' => 'Cloudflare menjawab HTTP '.$response->status().'. Coba lagi nanti.'];
    }

    /**
     * Buat DNS record A: domain → IP server.
     *
     * @return array{ok: bool, message: string, record_id?: string}
     */
    public static function createDnsRecord(string $domain, string $ip, bool $proxied = false): array
    {
        $token = self::apiToken();

        if (! filled($token)) {
            return ['ok' => false, 'message' => 'API Token Cloudflare belum diisi.'];
        }

        $zoneId = self::zoneIdForDomain($domain, $token);

        if (! $zoneId) {
            return ['ok' => false, 'message' => 'Domain '.$domain.' tidak ditemukan di akun Cloudflare. Tambahkan dulu domainnya ke Cloudflare (DNS → Add site).'];
        }

        try {
            $response = self::client($token)->post(self::API_BASE.'/zones/'.$zoneId.'/dns_records', [
                'type' => 'A',
                'name' => $domain,
                'content' => $ip,
                'ttl' => 1,
                'proxied' => $proxied,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Cloudflare: '.$e->getMessage()];
        }

        $json = $response->json();

        if (! $response->successful() || ! ($json['success'] ?? false)) {
            Log::error('Cloudflare create DNS ditolak.', ['status' => $response->status(), 'body' => $response->body()]);
            $error = $json['errors'][0]['message'] ?? ('HTTP '.$response->status());

            return ['ok' => false, 'message' => 'Cloudflare menolak pembuatan DNS: '.$error];
        }

        return [
            'ok' => true,
            'message' => 'DNS A record '.$domain.' → '.$ip.' berhasil dibuat di Cloudflare.',
            'record_id' => $json['result']['id'] ?? null,
        ];
    }

    protected static function zoneIdForDomain(string $domain, ?string $token = null): ?string
    {
        try {
            $response = self::client($token)->get(self::API_BASE.'/zones', ['name' => $domain]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->json('result.0.id');
    }

    protected static function apiToken(): ?string
    {
        $row = DeploySetting::query()->where('provider', DomainProvider::Cloudflare->value)->first();

        return $row?->credential('api_token');
    }

    protected static function client(?string $token = null): PendingRequest
    {
        return Http::withToken((string) ($token ?? self::apiToken()))
            ->acceptJson()
            ->timeout(15);
    }
}
