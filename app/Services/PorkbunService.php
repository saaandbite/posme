<?php

namespace App\Services;

use App\Enums\DomainProvider;
use App\Models\DeploySetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Domain lewat Porkbun (https://api.porkbun.com/api/json/v3).
 *
 * API Porkbun TIDAK menyediakan pembelian domain — yang bisa lewat API
 * hanya cek ketersediaan + pengelolaan DNS domain yang sudah dimiliki.
 * Karena itu aksi "beli" di halaman admin hanya membuka checkout
 * Porkbun di tab baru (https://porkbun.com/checkout/search?q=domain);
 * pembayarannya tetap di situs Porkbun, bukan dari dashboard posme.
 *
 * Dokumen: https://porkbun.com/api/json/v3/documentation
 */
class PorkbunService
{
    protected const API_BASE = 'https://api.porkbun.com/api/json/v3';

    /** @return array{ok: bool, message: string, data?: mixed} */
    public static function testConnection(array $credentials = []): array
    {
        $apiKey = $credentials['api_key'] ?? self::apiKey();
        $secret = $credentials['secret_api_key'] ?? self::secretApiKey();

        if (! filled($apiKey) || ! filled($secret)) {
            return ['ok' => false, 'message' => 'API Key / Secret API Key Porkbun belum diisi.'];
        }

        try {
            $response = self::client()->post(self::API_BASE.'/ping', [
                'apikey' => $apiKey,
                'secretapikey' => $secret,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Porkbun: '.$e->getMessage()];
        }

        $json = $response->json();

        if (($json['status'] ?? null) === 'SUCCESS') {
            return ['ok' => true, 'message' => 'Koneksi berhasil — key valid.'];
        }

        return ['ok' => false, 'message' => 'Key Porkbun salah atau ditolak: '.($json['message'] ?? 'HTTP '.$response->status()).'.'];
    }

    /**
     * Cek ketersediaan domain + harga registrasi (bila tersedia).
     *
     * @return array{ok: bool, message: string, available?: bool, price?: string|null}
     */
    public static function checkDomain(string $domain): array
    {
        $apiKey = self::apiKey();
        $secret = self::secretApiKey();

        if (! filled($apiKey) || ! filled($secret)) {
            return ['ok' => false, 'message' => 'API Key / Secret API Key Porkbun belum diisi.'];
        }

        try {
            $response = self::client()->post(self::API_BASE.'/domain/checkDomain', [
                'apikey' => $apiKey,
                'secretapikey' => $secret,
                'domain' => $domain,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Porkbun: '.$e->getMessage()];
        }

        $json = $response->json();

        if (($json['status'] ?? null) !== 'SUCCESS') {
            Log::error('Porkbun checkDomain ditolak.', ['status' => $response->status(), 'body' => $response->body()]);

            return ['ok' => false, 'message' => 'Porkbun menolak pengecekan: '.($json['message'] ?? 'HTTP '.$response->status()).'.'];
        }

        $available = ($json['avail'] ?? 'no') === 'yes';
        $price = $json['price'] ?? null;

        return [
            'ok' => true,
            'message' => $available
                ? 'Domain '.$domain.' TERSEDIA'.($price ? ' — harga registrasi ±$'.$price.'/tahun.' : '.').' Beli lewat checkout Porkbun (tombol di bawah).'
                : 'Domain '.$domain.' sudah dipakai orang lain.',
            'available' => $available,
            'price' => $price,
        ];
    }

    /** URL checkout Porkbun untuk membeli domain (dibuka di tab baru). */
    public static function buyUrl(string $domain): string
    {
        return 'https://porkbun.com/checkout/search?q='.urlencode($domain);
    }

    protected static function apiKey(): ?string
    {
        $row = DeploySetting::query()->where('provider', DomainProvider::Porkbun->value)->first();

        return $row?->credential('api_key');
    }

    protected static function secretApiKey(): ?string
    {
        $row = DeploySetting::query()->where('provider', DomainProvider::Porkbun->value)->first();

        return $row?->credential('secret_api_key');
    }

    protected static function client(): PendingRequest
    {
        return Http::acceptJson()->timeout(15);
    }
}
