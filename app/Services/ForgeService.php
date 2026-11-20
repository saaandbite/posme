<?php

namespace App\Services;

use App\Enums\DeployProvider;
use App\Models\DeploySetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Deploy lewat Laravel Forge (https://forge.laravel.com/api/v1).
 *
 * Dipakai HTTP client Laravel langsung, tanpa SDK: SDK resmi
 * (laravel/forge-sdk) butuh guzzlehttp/guzzle ^6/^7, sementara proyek
 * ini mengunci Guzzle 8.5.2 — menurunkan Guzzle akan merusak Laravel
 * Framework. Semua panggilan memakai Bearer token dari DeploySetting.
 *
 * Forge itu layanan berbayar — owner butuh akun Forge sendiri + akun
 * provider VPS (DigitalOcean/Linode/Vultr/AWS/Hetzner). Buat server
 * butuh ~10 menit provisioning: API mengembalikan status, halaman
 * admin menampilkan statusnya dan tidak memblokir UI menunggu.
 *
 * Dokumen: https://forge.laravel.com/api-documentation
 */
class ForgeService
{
    protected const API_BASE = 'https://forge.laravel.com/api/v1';

    /** @return array{ok: bool, message: string, data?: mixed} */
    public static function testConnection(array $credentials = []): array
    {
        $token = $credentials['api_token'] ?? self::apiToken();

        if (! filled($token)) {
            return ['ok' => false, 'message' => 'API Token Forge belum diisi.'];
        }

        try {
            $response = self::client($token)->get(self::API_BASE.'/user');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Forge: '.$e->getMessage()];
        }

        return match ($response->status()) {
            200 => ['ok' => true, 'message' => 'Koneksi berhasil — token valid (akun: '.($response->json('user.name') ?? $response->json('name') ?? '—').').'],
            401 => ['ok' => false, 'message' => 'API Token salah (401 Unauthorized). Periksa kembali tokennya.'],
            default => ['ok' => false, 'message' => 'Forge menjawab HTTP '.$response->status().'. Coba lagi nanti.'],
        };
    }

    /** @return array{ok: bool, message: string, servers?: array<int, array>} */
    public static function listServers(): array
    {
        $token = self::apiToken();

        if (! filled($token)) {
            return ['ok' => false, 'message' => 'API Token Forge belum diisi.'];
        }

        try {
            $response = self::client($token)->get(self::API_BASE.'/servers');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Forge: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Forge menjawab HTTP '.$response->status().'.'];
        }

        return ['ok' => true, 'message' => 'Daftar server dimuat.', 'servers' => $response->json('servers', [])];
    }

    /**
     * Buat server VPS baru lewat Forge. Server butuh ~10 menit
     * provisioning — respons berisi status awal, bukan server jadi.
     *
     * @param  array{provider: string, name: string, size: string, region: string, php_version: string, database: string, ubuntu_version: string}  $params
     * @return array{ok: bool, message: string, server?: array}
     */
    public static function createServer(array $params): array
    {
        $token = self::apiToken();

        if (! filled($token)) {
            return ['ok' => false, 'message' => 'API Token Forge belum diisi.'];
        }

        $payload = [
            'provider' => $params['provider'],
            'name' => $params['name'],
            'size' => $params['size'],
            'region' => $params['region'],
            'php_version' => $params['php_version'] ?? 'php83',
            'database' => $params['database'] ?? 'mysql8',
        ];

        if (filled($params['ubuntu_version'] ?? null)) {
            $payload['ubuntu_version'] = $params['ubuntu_version'];
        }

        try {
            $response = self::client($token)->post(self::API_BASE.'/servers', $payload);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Forge: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            Log::error('Forge create server ditolak.', ['status' => $response->status(), 'body' => $response->body()]);

            return ['ok' => false, 'message' => 'Forge menolak pembuatan server (HTTP '.$response->status().'): '.self::errorMessage($response)];
        }

        $server = $response->json('server', []);

        return [
            'ok' => true,
            'message' => 'Server "'.$params['name'].'" dibuat — status: '.($server['status'] ?? 'provisioning').'. Provisioning butuh ~10 menit; cek status dengan tombol Refresh.',
            'server' => $server,
        ];
    }

    /** @return array{ok: bool, message: string, server?: array} */
    public static function serverStatus(int|string $serverId): array
    {
        $token = self::apiToken();

        if (! filled($token)) {
            return ['ok' => false, 'message' => 'API Token Forge belum diisi.'];
        }

        try {
            $response = self::client($token)->get(self::API_BASE.'/servers/'.$serverId);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Forge: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Forge menjawab HTTP '.$response->status().'.'];
        }

        $server = $response->json('server', []);

        return [
            'ok' => true,
            'message' => 'Server "' .($server['name'] ?? $serverId).'" — status: '.($server['status'] ?? '—').($server['ip_address'] ?? null ? ', IP: '.$server['ip_address'] : '').'.',
            'server' => $server,
        ];
    }

    /** @return array{ok: bool, message: string, sites?: array<int, array>} */
    public static function listSites(int|string $serverId): array
    {
        $token = self::apiToken();

        if (! filled($token)) {
            return ['ok' => false, 'message' => 'API Token Forge belum diisi.'];
        }

        try {
            $response = self::client($token)->get(self::API_BASE.'/servers/'.$serverId.'/sites');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Forge: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Forge menjawab HTTP '.$response->status().'.'];
        }

        return ['ok' => true, 'message' => 'Daftar site dimuat.', 'sites' => $response->json('sites', [])];
    }

    /**
     * Pasang repo GitHub ke site, lalu jalankan deploy.
     *
     * @return array{ok: bool, message: string, deployment_id?: mixed}
     */
    public static function deploySite(int|string $serverId, int|string $siteId, ?string $repository = null, ?string $branch = null): array
    {
        $token = self::apiToken();

        if (! filled($token)) {
            return ['ok' => false, 'message' => 'API Token Forge belum diisi.'];
        }

        $client = self::client($token);

        // Pasang repo dulu bila diminta (abaikan bila site sudah punya repo).
        if (filled($repository)) {
            try {
                $repoResponse = $client->post(self::API_BASE.'/servers/'.$serverId.'/sites/'.$siteId.'/repository', [
                    'provider' => 'github',
                    'repository' => $repository,
                    'branch' => $branch ?: 'main',
                ]);
            } catch (\Throwable $e) {
                return ['ok' => false, 'message' => 'Tidak bisa menghubungi Forge: '.$e->getMessage()];
            }

            if (! $repoResponse->successful()) {
                Log::error('Forge install repository ditolak.', ['status' => $repoResponse->status(), 'body' => $repoResponse->body()]);

                return ['ok' => false, 'message' => 'Forge menolak pemasangan repo (HTTP '.$repoResponse->status().'): '.self::errorMessage($repoResponse)];
            }
        }

        try {
            $response = $client->post(self::API_BASE.'/servers/'.$serverId.'/sites/'.$siteId.'/deploy');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Forge: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            Log::error('Forge deploy ditolak.', ['status' => $response->status(), 'body' => $response->body()]);

            return ['ok' => false, 'message' => 'Forge menolak deploy (HTTP '.$response->status().'): '.self::errorMessage($response)];
        }

        return [
            'ok' => true,
            'message' => 'Deploy dimulai untuk site #'.$siteId.'. Pantau di dashboard Forge sampai selesai.',
            'deployment_id' => $response->json('id') ?? $response->json('deployment.id'),
        ];
    }

    /** @return array{ok: bool, message: string, deployments?: array<int, array>} */
    public static function listDeployments(int|string $serverId, int|string $siteId): array
    {
        $token = self::apiToken();

        if (! filled($token)) {
            return ['ok' => false, 'message' => 'API Token Forge belum diisi.'];
        }

        try {
            $response = self::client($token)->get(self::API_BASE.'/servers/'.$serverId.'/sites/'.$siteId.'/deployments');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi Forge: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Forge menjawab HTTP '.$response->status().'.'];
        }

        return ['ok' => true, 'message' => 'Riwayat deployment dimuat.', 'deployments' => $response->json('deployments', [])];
    }

    /** Provider VPS yang didukung Forge (nilai API => label). */
    public static function vpsProviders(): array
    {
        return [
            'ocean2' => 'DigitalOcean',
            'linode' => 'Linode (Akamai)',
            'vultr' => 'Vultr',
            'aws' => 'Amazon Web Services',
            'hetzner' => 'Hetzner',
            'custom' => 'Custom VPS (server sendiri)',
        ];
    }

    /** Ukuran server per provider VPS (nilai API => label + harga perkiraan). */
    public static function sizesFor(string $vpsProvider): array
    {
        return match ($vpsProvider) {
            'ocean2' => [
                's-1vcpu-1gb' => 'Basic 1 vCPU / 1 GB — ~$6/bln',
                's-1vcpu-2gb' => 'Basic 1 vCPU / 2 GB — ~$12/bln',
                's-2vcpu-2gb' => 'Basic 2 vCPU / 2 GB — ~$18/bln',
                's-2vcpu-4gb' => 'Basic 2 vCPU / 4 GB — ~$24/bln',
            ],
            'linode' => [
                'g6-nanode-1' => 'Nanode 1 vCPU / 1 GB — ~$6/bln',
                'g6-standard-1' => 'Linode 1 vCPU / 2 GB — ~$12/bln',
                'g6-standard-2' => 'Linode 2 vCPU / 4 GB — ~$24/bln',
            ],
            'vultr' => [
                'vc2-1c-1gb' => 'Cloud 1 vCPU / 1 GB — ~$6/bln',
                'vc2-1c-2gb' => 'Cloud 1 vCPU / 2 GB — ~$12/bln',
                'vc2-2c-4gb' => 'Cloud 2 vCPU / 4 GB — ~$24/bln',
            ],
            'aws' => [
                't3.micro' => 't3.micro 2 vCPU / 1 GB — ~$7,50/bln',
                't3.small' => 't3.small 2 vCPU / 2 GB — ~$15/bln',
            ],
            'hetzner' => [
                'cx22' => 'CX22 2 vCPU / 4 GB — ~€4,15/bln',
                'cx32' => 'CX32 4 vCPU / 8 GB — ~€6,85/bln',
                'cx42' => 'CX42 8 vCPU / 16 GB — ~€10,35/bln',
            ],
            default => [
                'custom' => 'Custom — harga mengikuti server sendiri',
            ],
        };
    }

    /** Region per provider VPS (nilai API => label). */
    public static function regionsFor(string $vpsProvider): array
    {
        return match ($vpsProvider) {
            'ocean2' => [
                'sgp1' => 'Singapura (SGP1)',
                'nyc3' => 'New York (NYC3)',
                'fra1' => 'Frankfurt (FRA1)',
                'ams3' => 'Amsterdam (AMS3)',
            ],
            'linode' => [
                'ap-southeast' => 'Singapura (ap-southeast)',
                'us-east' => 'Newark (us-east)',
                'eu-west' => 'London (eu-west)',
            ],
            'vultr' => [
                'sgp' => 'Singapura (sgp)',
                'fra' => 'Frankfurt (fra)',
                'lax' => 'Los Angeles (lax)',
            ],
            'aws' => [
                'ap-southeast-1' => 'Singapura (ap-southeast-1)',
                'us-east-1' => 'N. Virginia (us-east-1)',
                'eu-west-1' => 'Irlandia (eu-west-1)',
            ],
            'hetzner' => [
                'fsn1' => 'Falkenstein (fsn1)',
                'nbg1' => 'Nuremberg (nbg1)',
                'hel1' => 'Helsinki (hel1)',
            ],
            default => [
                'custom' => 'Custom — region server sendiri',
            ],
        };
    }

    /** Perkiraan biaya bulanan untuk ukuran yang dipilih (teks tampil di konfirmasi). */
    public static function priceEstimate(string $vpsProvider, string $size): ?string
    {
        $sizes = self::sizesFor($vpsProvider);

        if (! isset($sizes[$size])) {
            return null;
        }

        $label = $sizes[$size];

        // "Basic 1 vCPU / 1 GB — ~$6/bln" → "~$6/bln".
        if (preg_match('/—\s*(.+)$/', $label, $m)) {
            return $m[1];
        }

        return null;
    }

    protected static function apiToken(): ?string
    {
        $row = DeploySetting::query()->where('provider', DeployProvider::Forge->value)->first();

        return $row?->credential('api_token');
    }

    protected static function client(?string $token = null): PendingRequest
    {
        return Http::withToken((string) ($token ?? self::apiToken()))
            ->acceptJson()
            ->timeout(30);
    }

    protected static function errorMessage($response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            return (string) ($json['message'] ?? $json['error'] ?? $response->body());
        }

        return (string) $response->body();
    }
}
