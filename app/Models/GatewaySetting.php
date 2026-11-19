<?php

namespace App\Models;

use App\Enums\GatewayProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Kredensial payment gateway per provider — diatur owner dari dashboard
 * (halaman Pengaturan Gateway), tanpa sentuh file .env.
 *
 * Kredensial disimpan terenkripsi (cast encrypted:array). Tepat satu
 * provider boleh aktif dalam satu waktu; bila tidak ada yang aktif,
 * customer memakai fallback "Bayar di kasir".
 */
class GatewaySetting extends Model
{
    protected $fillable = [
        'provider', 'is_active', 'is_production', 'credentials',
        'last_test_at', 'last_test_status', 'last_test_message',
    ];

    protected function casts(): array
    {
        return [
            'provider' => GatewayProvider::class,
            'is_active' => 'boolean',
            'is_production' => 'boolean',
            'credentials' => 'encrypted:array',
            'last_test_at' => 'datetime',
        ];
    }

    public static function forProvider(GatewayProvider|string $provider): self
    {
        $provider = $provider instanceof GatewayProvider ? $provider : GatewayProvider::from($provider);

        return static::firstOrCreate(['provider' => $provider]);
    }

    /** Provider yang sedang aktif, null bila tidak ada gateway yang dipakai. */
    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
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

    public function recordTest(bool $ok, string $message): void
    {
        $this->update([
            'last_test_at' => now(),
            'last_test_status' => $ok ? 'ok' : 'gagal',
            'last_test_message' => $message,
        ]);
    }
}
