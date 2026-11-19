<?php

namespace App\Filament\Pages;

use App\Enums\GatewayProvider;
use App\Models\GatewaySetting;
use App\Services\MidtransService;
use App\Services\XenditService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Pengaturan gateway pembayaran (low-code): owner memilih provider aktif,
 * mengisi kredensial (terenkripsi di database), memilih mode
 * Sandbox/Produksi, dan mengetes koneksi — tanpa sentuh file .env.
 */
class GatewaySettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Pengaturan Gateway';
    protected static ?string $title = 'Pengaturan Gateway';
    protected static ?string $navigationGroup = 'Sistem';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'gateway-settings';
    protected static string $view = 'filament.pages.gateway-settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        $midtrans = GatewaySetting::forProvider(GatewayProvider::Midtrans);
        $xendit = GatewaySetting::forProvider(GatewayProvider::Xendit);
        $active = GatewaySetting::active();

        $this->form->fill([
            'active_provider' => $active?->provider->value ?? GatewayProvider::None->value,
            'midtrans_server_key' => $midtrans->maskedCredential('server_key'),
            'midtrans_client_key' => $midtrans->maskedCredential('client_key'),
            'midtrans_is_production' => $midtrans->is_production,
            'xendit_secret_key' => $xendit->maskedCredential('secret_key'),
            'xendit_callback_token' => $xendit->maskedCredential('callback_token'),
            'xendit_is_production' => $xendit->is_production,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Provider aktif')
                    ->description('Hanya satu provider yang aktif dalam satu waktu. Pilih "Tidak ada" bila customer cukup bayar di kasir.')
                    ->schema([
                        Forms\Components\Select::make('active_provider')
                            ->label('Gateway pembayaran')
                            ->options(GatewayProvider::options())
                            ->required()
                            ->native(false),
                    ]),
                Forms\Components\Section::make('Midtrans')
                    ->description('Snap — QRIS, virtual account, e-wallet, retail. Key didapat dari dashboard Midtrans (Settings → Access Keys).')
                    ->schema([
                        Forms\Components\TextInput::make('midtrans_server_key')
                            ->label('Server Key')
                            ->password()
                            ->revealable()
                            ->helperText('Disimpan terenkripsi. Yang tampil hanya 4 karakter terakhir — isi ulang field ini untuk mengganti key.'),
                        Forms\Components\TextInput::make('midtrans_client_key')
                            ->label('Client Key')
                            ->password()
                            ->revealable()
                            ->helperText('Disimpan terenkripsi. Isi ulang untuk mengganti key.'),
                        Forms\Components\Toggle::make('midtrans_is_production')
                            ->label('Mode Produksi')
                            ->helperText('Matikan untuk Sandbox (uang mainan). Nyalakan hanya bila sudah pakai key produksi asli.'),
                        Forms\Components\Placeholder::make('midtrans_last_test')
                            ->label('Tes koneksi terakhir')
                            ->content(fn () => $this->lastTestText(GatewayProvider::Midtrans)),
                    ]),
                Forms\Components\Section::make('Xendit')
                    ->description('Invoice — QRIS, virtual account, e-wallet, retail. Key didapat dari dashboard Xendit (Settings → Developers → API Keys).')
                    ->schema([
                        Forms\Components\TextInput::make('xendit_secret_key')
                            ->label('Secret Key')
                            ->password()
                            ->revealable()
                            ->helperText('Disimpan terenkripsi. Yang tampil hanya 4 karakter terakhir — isi ulang field ini untuk mengganti key.'),
                        Forms\Components\TextInput::make('xendit_callback_token')
                            ->label('Webhook Callback Token')
                            ->password()
                            ->revealable()
                            ->helperText('Token verifikasi webhook (Xendit → Settings → Developers → Webhooks). Tanpa token ini, webhook Xendit ditolak.'),
                        Forms\Components\Toggle::make('xendit_is_production')
                            ->label('Mode Produksi')
                            ->helperText('Matikan untuk Sandbox (uang mainan). Nyalakan hanya bila sudah pakai key produksi asli.'),
                        Forms\Components\Placeholder::make('xendit_last_test')
                            ->label('Tes koneksi terakhir')
                            ->content(fn () => $this->lastTestText(GatewayProvider::Xendit)),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('testMidtrans')
                ->label('Tes koneksi Midtrans')
                ->icon('heroicon-o-signal')
                ->action(fn () => $this->testProvider(GatewayProvider::Midtrans)),
            Actions\Action::make('testXendit')
                ->label('Tes koneksi Xendit')
                ->icon('heroicon-o-signal')
                ->action(fn () => $this->testProvider(GatewayProvider::Xendit)),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $active = GatewayProvider::from($data['active_provider']);

        foreach ([GatewayProvider::Midtrans, GatewayProvider::Xendit] as $provider) {
            $row = GatewaySetting::forProvider($provider);
            $prefix = $provider->value;

            $credentials = $row->credentials ?? [];
            foreach ($this->credentialFields($provider) as $field) {
                $submitted = $data["{$prefix}_{$field}"] ?? null;
                // Hanya timpa bila admin mengisi key baru (bukan nilai ter-mask).
                if (filled($submitted) && ! str_starts_with((string) $submitted, '••••')) {
                    $credentials[$field] = $submitted;
                }
            }

            $row->update([
                'is_active' => $provider === $active,
                'is_production' => (bool) ($data["{$prefix}_is_production"] ?? false),
                'credentials' => $credentials,
            ]);
        }

        Notification::make()
            ->title('Pengaturan gateway disimpan.')
            ->success()
            ->send();
    }

    /** @return string[] nama field kredensial per provider. */
    protected function credentialFields(GatewayProvider $provider): array
    {
        return match ($provider) {
            GatewayProvider::Midtrans => ['server_key', 'client_key'],
            GatewayProvider::Xendit => ['secret_key', 'callback_token'],
            default => [],
        };
    }

    protected function lastTestText(GatewayProvider $provider): string
    {
        $row = GatewaySetting::query()->where('provider', $provider)->first();

        if (! $row || ! $row->last_test_at) {
            return 'Belum pernah dites.';
        }

        return ($row->last_test_status === 'ok' ? 'Berhasil' : 'Gagal')
            .' — '.$row->last_test_at->format('d M Y, H:i')
            .' — '.$row->last_test_message;
    }

    protected function testProvider(GatewayProvider $provider): void
    {
        $data = $this->form->getState();
        $row = GatewaySetting::forProvider($provider);
        $prefix = $provider->value;

        // Kredensial dari form bila diisi (bukan mask), kalau tidak dari DB.
        $credentials = [];
        foreach ($this->credentialFields($provider) as $field) {
            $submitted = $data["{$prefix}_{$field}"] ?? null;
            $credentials[$field] = filled($submitted) && ! str_starts_with((string) $submitted, '••••')
                ? $submitted
                : $row->credential($field);
        }

        $result = match ($provider) {
            GatewayProvider::Midtrans => MidtransService::testConnection($credentials),
            GatewayProvider::Xendit => XenditService::testConnection($credentials),
            default => ['ok' => false, 'message' => 'Provider tidak dikenal.'],
        };

        $row->recordTest($result['ok'], $result['message']);

        Notification::make()
            ->title($result['ok'] ? 'Tes koneksi berhasil' : 'Tes koneksi gagal')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }
}
