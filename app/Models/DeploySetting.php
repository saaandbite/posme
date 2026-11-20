<?php

namespace App\Models;

use App\Enums\DeployProvider;
use App\Enums\DomainProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Kredensial + pilihan deploy per provider — diatur owner dari dashboard
 * (halaman Pengaturan Deploy), tanpa sentuh file .env.
 *
 * Kredensial disimpan terenkripsi (cast encrypted:array). Pilihan
 * non-rahasia (server/site/app/domain) disimpan di kolom `selection`
 * (json biasa) supaya bisa dibaca untuk aksi deploy & DNS.
 */
class DeploySetting extends Model
{
    protected $fillable = [
        'provider', 'is_active', 'credentials', 'selection',
        'last_test_at', 'last_test_status', 'last_test_message',
        'last_deploy_at', 'last_deploy_status', 'last_deploy_message',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credentials' => 'encrypted:array',
            'selection' => 'array',
            'last_test_at' => 'datetime',
            'last_deploy_at' => 'datetime',
        ];
    }

    public static function forProvider(DeployProvider|DomainProvider|string $provider): self
    {
        $value = $provider instanceof DeployProvider || $provider instanceof DomainProvider
            ? $provider->value
            : $provider;

        return static::firstOrCreate(['provider' => $value]);
    }

    /** Provider deploy yang sedang aktif (forge/coolify), null bila tidak ada. */
    public static function activeDeploy(): ?self
    {
        return static::where('is_active', true)
            ->whereIn('provider', [DeployProvider::Forge->value, DeployProvider::Coolify->value])
            ->first();
    }

    /** Provider domain yang sedang aktif (cloudflare/porkbun), null bila tidak ada. */
    public static function activeDomain(): ?self
    {
        return static::where('is_active', true)
            ->whereIn('provider', [DomainProvider::Cloudflare->value, DomainProvider::Porkbun->value])
            ->first();
    }

    /** Satu kredensial (didekripsi otomatis oleh cast). */
    public function credential(string $key): ?string
    {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /** Kredensial dalam bentuk ter-mask untuk ditampilkan di UI. */
    public function maskedCredential(string $key): ?string
    {
        $value = $this->credential($key);

        if (! $value) {
            return null;
        }

        return '••••••••'.substr($value, -4);
    }

    /** Satu nilai pilihan non-rahasia (server_id, site_id, domain, ...). */
    public function selectionValue(string $key): mixed
    {
        return ($this->selection ?? [])[$key] ?? null;
    }

    /** Simpan sebagian pilihan (digabung dengan yang sudah ada). */
    public function setSelection(array $values): void
    {
        $this->update(['selection' => array_merge($this->selection ?? [], $values)]);
    }

    public function recordTest(bool $ok, string $message): void
    {
        $this->update([
            'last_test_at' => now(),
            'last_test_status' => $ok ? 'ok' : 'gagal',
            'last_test_message' => $message,
        ]);
    }

    public function recordDeploy(bool $ok, string $message): void
    {
        $this->update([
            'last_deploy_at' => now(),
            'last_deploy_status' => $ok ? 'ok' : 'gagal',
            'last_deploy_message' => $message,
        ]);
    }
}
