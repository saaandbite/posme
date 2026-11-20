<?php

namespace App\Filament\Pages;

use App\Enums\DeployProvider;
use App\Enums\DomainProvider;
use App\Models\DeploySetting;
use App\Services\CloudflareService;
use App\Services\CoolifyService;
use App\Services\ForgeService;
use App\Services\PorkbunService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Pengaturan deploy (low-code): owner memilih provider deploy & domain,
 * mengisi kredensial (terenkripsi di database), membuat server VPS,
 * deploy site/aplikasi, dan mengatur DNS — tanpa buka web provider.
 *
 * ATURAN BIAYA: setiap aksi yang mengeluarkan uang (buat server) HARUS
 * dikonfirmasi dua lapis — modal konfirmasi dengan harga perkiraan +
 * checkbox persetujuan di form. Tanpa centang, aksi tidak jalan.
 * Pembelian domain tidak tersedia lewat API Porkbun: halaman hanya
 * mengecek ketersediaan, belinya lewat checkout Porkbun (link eksternal).
 */
class DeploySettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-server';
    protected static ?string $navigationLabel = 'Pengaturan Deploy';
    protected static ?string $title = 'Pengaturan Deploy';
    protected static ?string $navigationGroup = 'Sistem';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'deploy-settings';
    protected static string $view = 'filament.pages.deploy-settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        $forge = DeploySetting::forProvider(DeployProvider::Forge);
        $coolify = DeploySetting::forProvider(DeployProvider::Coolify);
        $cloudflare = DeploySetting::forProvider(DomainProvider::Cloudflare);
        $porkbun = DeploySetting::forProvider(DomainProvider::Porkbun);
        $activeDeploy = DeploySetting::activeDeploy();
        $activeDomain = DeploySetting::activeDomain();

        $this->form->fill([
            'active_deploy_provider' => $activeDeploy?->provider->value ?? DeployProvider::None->value,
            'forge_api_token' => $forge->maskedCredential('api_token'),
            'coolify_base_url' => $coolify->credential('base_url'),
            'coolify_api_token' => $coolify->maskedCredential('api_token'),
            'forge_server_id' => $forge->selectionValue('server_id'),
            'forge_site_id' => $forge->selectionValue('site_id'),
            'coolify_app_uuid' => $coolify->selectionValue('app_uuid'),
            'active_domain_provider' => $activeDomain?->provider->value ?? DomainProvider::None->value,
            'cloudflare_api_token' => $cloudflare->maskedCredential('api_token'),
            'porkbun_api_key' => $porkbun->maskedCredential('api_key'),
            'porkbun_secret_api_key' => $porkbun->maskedCredential('secret_api_key'),
            'domain_name' => ($activeDomain ?? $cloudflare)->selectionValue('domain'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Provider Deploy')
                    ->description('Hanya satu provider deploy yang aktif dalam satu waktu. Forge berbayar dan butuh akun Forge + akun provider VPS (DigitalOcean/Linode/Vultr/AWS/Hetzner). Coolify gratis tapi butuh VPS yang sudah terpasang Coolify.')
                    ->schema([
                        Forms\Components\Select::make('active_deploy_provider')
                            ->label('Provider deploy')
                            ->options(DeployProvider::options())
                            ->required()
                            ->native(false),
                    ]),
                Forms\Components\Section::make('Laravel Forge')
                    ->description('Token didapat dari forge.laravel.com → profil → API. Forge memakai token ini untuk membuat server, memasang site, dan deploy.')
                    ->schema([
                        Forms\Components\TextInput::make('forge_api_token')
                            ->label('API Token')
                            ->password()
                            ->revealable()
                            ->helperText('Disimpan terenkripsi. Yang tampil hanya 4 karakter terakhir — isi ulang field ini untuk mengganti token.'),
                        Forms\Components\Placeholder::make('forge_last_test')
                            ->label('Tes koneksi terakhir')
                            ->content(fn () => $this->lastTestText(DeployProvider::Forge)),
                    ]),
                Forms\Components\Section::make('Coolify')
                    ->description('URL + API Token dari instance Coolify milikmu (Keys & Tokens → API Tokens di dashboard Coolify).')
                    ->schema([
                        Forms\Components\TextInput::make('coolify_base_url')
                            ->label('URL instance Coolify')
                            ->placeholder('https://coolify.domain-anda')
                            ->helperText('URL dasar instance Coolify, tanpa / di akhir. Disimpan apa adanya (bukan rahasia).'),
                        Forms\Components\TextInput::make('coolify_api_token')
                            ->label('API Token')
                            ->password()
                            ->revealable()
                            ->helperText('Disimpan terenkripsi. Yang tampil hanya 4 karakter terakhir — isi ulang untuk mengganti token.'),
                        Forms\Components\Placeholder::make('coolify_last_test')
                            ->label('Tes koneksi terakhir')
                            ->content(fn () => $this->lastTestText(DeployProvider::Coolify)),
                    ]),
                Forms\Components\Section::make('Server & Site (Forge)')
                    ->description('ID server & site yang dipakai untuk deploy. Bisa diisi manual dari dashboard Forge, atau dibuat lewat aksi "Buat server baru" di atas halaman.')
                    ->schema([
                        Forms\Components\TextInput::make('forge_server_id')
                            ->label('Server ID')
                            ->placeholder('mis. 123456')
                            ->helperText('ID server di Forge (angka).'),
                        Forms\Components\TextInput::make('forge_site_id')
                            ->label('Site ID')
                            ->placeholder('mis. 789012')
                            ->helperText('ID site di server itu (angka).'),
                        Forms\Components\Placeholder::make('forge_server_status')
                            ->label('Status server')
                            ->content(fn () => $this->serverStatusText()),
                        Forms\Components\Placeholder::make('forge_last_deploy')
                            ->label('Deploy terakhir')
                            ->content(fn () => $this->lastDeployText(DeployProvider::Forge)),
                    ]),
                Forms\Components\Section::make('Aplikasi (Coolify)')
                    ->description('UUID aplikasi Coolify yang mau di-deploy (lihat di dashboard Coolify → Applications).')
                    ->schema([
                        Forms\Components\TextInput::make('coolify_app_uuid')
                            ->label('UUID aplikasi')
                            ->placeholder('mis. a1b2c3d4-...')
                            ->helperText('UUID aplikasi di Coolify.'),
                        Forms\Components\Placeholder::make('coolify_last_deploy')
                            ->label('Deploy terakhir')
                            ->content(fn () => $this->lastDeployText(DeployProvider::Coolify)),
                    ]),
                Forms\Components\Section::make('Provider Domain')
                    ->description('Hanya satu provider domain yang aktif dalam satu waktu. Cloudflare untuk mengatur DNS domain yang sudah ada; Porkbun untuk cek ketersediaan + beli domain baru.')
                    ->schema([
                        Forms\Components\Select::make('active_domain_provider')
                            ->label('Provider domain')
                            ->options(DomainProvider::options())
                            ->required()
                            ->native(false),
                    ]),
                Forms\Components\Section::make('Cloudflare')
                    ->description('Token didapat dari dash.cloudflare.com → My Profile → API Tokens. Butuh izin Zone:DNS:Edit untuk zone domainnya.')
                    ->schema([
                        Forms\Components\TextInput::make('cloudflare_api_token')
                            ->label('API Token')
                            ->password()
                            ->revealable()
                            ->helperText('Disimpan terenkripsi. Yang tampil hanya 4 karakter terakhir — isi ulang untuk mengganti token.'),
                        Forms\Components\Placeholder::make('cloudflare_last_test')
                            ->label('Tes koneksi terakhir')
                            ->content(fn () => $this->lastTestText(DomainProvider::Cloudflare)),
                    ]),
                Forms\Components\Section::make('Porkbun')
                    ->description('Key didapat dari porkbun.com → Account → API Access. Catatan: API Porkbun TIDAK bisa membeli domain — halaman ini hanya mengecek ketersediaan; belinya lewat checkout Porkbun (link diberikan setelah pengecekan).')
                    ->schema([
                        Forms\Components\TextInput::make('porkbun_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->helperText('Disimpan terenkripsi. Yang tampil hanya 4 karakter terakhir — isi ulang untuk mengganti key.'),
                        Forms\Components\TextInput::make('porkbun_secret_api_key')
                            ->label('Secret API Key')
                            ->password()
                            ->revealable()
                            ->helperText('Disimpan terenkripsi. Yang tampil hanya 4 karakter terakhir — isi ulang untuk mengganti key.'),
                        Forms\Components\Placeholder::make('porkbun_last_test')
                            ->label('Tes koneksi terakhir')
                            ->content(fn () => $this->lastTestText(DomainProvider::Porkbun)),
                    ]),
                Forms\Components\Section::make('Domain')
                    ->description('Domain yang mau diarahkan ke server (untuk DNS Cloudflare) atau dicek ketersediaannya (Porkbun).')
                    ->schema([
                        Forms\Components\TextInput::make('domain_name')
                            ->label('Nama domain')
                            ->placeholder('mis. posme.my.id'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('testForge')
                    ->label('Forge')
                    ->action(fn () => $this->testProvider(DeployProvider::Forge)),
                Actions\Action::make('testCoolify')
                    ->label('Coolify')
                    ->action(fn () => $this->testProvider(DeployProvider::Coolify)),
                Actions\Action::make('testCloudflare')
                    ->label('Cloudflare')
                    ->action(fn () => $this->testProvider(DomainProvider::Cloudflare)),
                Actions\Action::make('testPorkbun')
                    ->label('Porkbun')
                    ->action(fn () => $this->testProvider(DomainProvider::Porkbun)),
            ])
                ->label('Tes koneksi')
                ->icon('heroicon-o-signal'),
            Actions\ActionGroup::make([
                Actions\Action::make('createForgeServer')
                    ->label('Buat server baru')
                    ->icon('heroicon-o-server')
                    ->form([
                        Forms\Components\Select::make('vps_provider')
                            ->label('Provider VPS')
                            ->options(ForgeService::vpsProviders())
                            ->default('hetzner')
                            ->required()
                            ->reactive()
                            ->native(false),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama server')
                            ->placeholder('mis. posme-prod')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Select::make('region')
                            ->label('Region')
                            ->options(fn (Get $get) => ForgeService::regionsFor($get('vps_provider') ?? 'hetzner'))
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('size')
                            ->label('Ukuran server')
                            ->options(fn (Get $get) => ForgeService::sizesFor($get('vps_provider') ?? 'hetzner'))
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('ubuntu_version')
                            ->label('Ubuntu')
                            ->options(['22.04' => 'Ubuntu 22.04 LTS', '24.04' => 'Ubuntu 24.04 LTS'])
                            ->default('24.04')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('php_version')
                            ->label('PHP')
                            ->options(['php83' => 'PHP 8.3', 'php84' => 'PHP 8.4'])
                            ->default('php83')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('database')
                            ->label('Database')
                            ->options(['mysql8' => 'MySQL 8', 'mariadb' => 'MariaDB', 'postgres16' => 'PostgreSQL 16', 'none' => 'Tanpa database'])
                            ->default('mysql8')
                            ->required()
                            ->native(false),
                        Forms\Components\Checkbox::make('confirmed')
                            ->label('Saya mengerti: server ini BERBAYAR dan akan ditagih provider VPS setiap bulan.')
                            ->helperText('Harga di modal konfirmasi adalah perkiraan — tagihan akhir mengikuti provider VPS yang dipilih.'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Buat server baru?')
                    ->modalDescription(fn (array $data): string => $this->serverCostText($data))
                    ->modalSubmitActionLabel('Ya, buat server')
                    ->action(fn (array $data) => $this->createForgeServer($data)),
                Actions\Action::make('refreshForgeServer')
                    ->label('Refresh status server')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn () => $this->refreshForgeServer()),
                Actions\Action::make('deployForgeSite')
                    ->label('Deploy site')
                    ->icon('heroicon-o-rocket-launch')
                    ->form([
                        Forms\Components\TextInput::make('site_id')
                            ->label('Site ID')
                            ->default(fn () => DeploySetting::forProvider(DeployProvider::Forge)->selectionValue('site_id'))
                            ->required(),
                        Forms\Components\Checkbox::make('install_repo')
                            ->label('Pasang/ubah repo GitHub dulu')
                            ->helperText('Centang hanya bila site belum punya repo, atau mau ganti repo/branch.'),
                        Forms\Components\TextInput::make('repository')
                            ->label('Repo GitHub')
                            ->placeholder('mis. saaandbite/posme')
                            ->visible(fn (Get $get) => (bool) $get('install_repo')),
                        Forms\Components\TextInput::make('branch')
                            ->label('Branch')
                            ->default('main')
                            ->visible(fn (Get $get) => (bool) $get('install_repo')),
                    ])
                    ->action(fn (array $data) => $this->deployForgeSite($data)),
            ])
                ->label('Forge')
                ->icon('heroicon-o-server'),
            Actions\ActionGroup::make([
                Actions\Action::make('deployCoolifyApp')
                    ->label('Deploy aplikasi')
                    ->icon('heroicon-o-rocket-launch')
                    ->form([
                        Forms\Components\TextInput::make('app_uuid')
                            ->label('UUID aplikasi')
                            ->default(fn () => DeploySetting::forProvider(DeployProvider::Coolify)->selectionValue('app_uuid'))
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->deployCoolifyApp($data)),
            ])
                ->label('Coolify')
                ->icon('heroicon-o-rocket-launch'),
            Actions\ActionGroup::make([
                Actions\Action::make('createCloudflareDns')
                    ->label('Buat DNS A record (Cloudflare)')
                    ->icon('heroicon-o-globe-alt')
                    ->form([
                        Forms\Components\TextInput::make('domain')
                            ->label('Domain')
                            ->default(fn () => $this->domainRow()->selectionValue('domain'))
                            ->required(),
                        Forms\Components\TextInput::make('ip')
                            ->label('IP server')
                            ->default(fn () => DeploySetting::forProvider(DeployProvider::Forge)->selectionValue('server_ip'))
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->createCloudflareDns($data)),
                Actions\Action::make('checkPorkbunDomain')
                    ->label('Cek domain (Porkbun)')
                    ->icon('heroicon-o-magnifying-glass')
                    ->form([
                        Forms\Components\TextInput::make('domain')
                            ->label('Domain')
                            ->default(fn () => $this->domainRow()->selectionValue('domain'))
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->checkPorkbunDomain($data)),
            ])
                ->label('Domain')
                ->icon('heroicon-o-globe-alt'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $activeDeploy = DeployProvider::from($data['active_deploy_provider']);
        $activeDomain = DomainProvider::from($data['active_domain_provider']);

        // Provider deploy: Forge & Coolify.
        foreach ([DeployProvider::Forge, DeployProvider::Coolify] as $provider) {
            $row = DeploySetting::forProvider($provider);
            $prefix = $provider->value;

            $credentials = $row->credentials ?? [];
            foreach ($this->credentialFields($provider) as $field) {
                $submitted = $data["{$prefix}_{$field}"] ?? null;
                // Hanya timpa bila admin mengisi kredensial baru (bukan nilai ter-mask).
                // base_url Coolify bukan rahasia: timpa bila diisi.
                if ($field === 'base_url') {
                    if (filled($submitted)) {
                        $credentials[$field] = $submitted;
                    }
                    continue;
                }
                if (filled($submitted) && ! str_starts_with((string) $submitted, '••••')) {
                    $credentials[$field] = $submitted;
                }
            }

            $selection = $row->selection ?? [];
            if ($provider === DeployProvider::Forge) {
                if (filled($data['forge_server_id'] ?? null)) {
                    $selection['server_id'] = $data['forge_server_id'];
                }
                if (filled($data['forge_site_id'] ?? null)) {
                    $selection['site_id'] = $data['forge_site_id'];
                }
            }
            if ($provider === DeployProvider::Coolify && filled($data['coolify_app_uuid'] ?? null)) {
                $selection['app_uuid'] = $data['coolify_app_uuid'];
            }

            $row->update([
                'is_active' => $provider === $activeDeploy,
                'credentials' => $credentials,
                'selection' => $selection,
            ]);
        }

        // Provider domain: Cloudflare & Porkbun.
        $domainRow = $this->domainRow();
        foreach ([DomainProvider::Cloudflare, DomainProvider::Porkbun] as $provider) {
            $row = DeploySetting::forProvider($provider);
            $prefix = $provider->value;

            $credentials = $row->credentials ?? [];
            foreach ($this->credentialFields($provider) as $field) {
                $submitted = $data["{$prefix}_{$field}"] ?? null;
                if (filled($submitted) && ! str_starts_with((string) $submitted, '••••')) {
                    $credentials[$field] = $submitted;
                }
            }

            $selection = $row->selection ?? [];
            if ($row->is($domainRow) && filled($data['domain_name'] ?? null)) {
                $selection['domain'] = $data['domain_name'];
            }

            $row->update([
                'is_active' => $provider === $activeDomain,
                'credentials' => $credentials,
                'selection' => $selection,
            ]);
        }

        Notification::make()
            ->title('Pengaturan deploy disimpan.')
            ->success()
            ->send();
    }

    /** @return string[] nama field kredensial per provider. */
    protected function credentialFields(DeployProvider|DomainProvider $provider): array
    {
        return match ($provider->value) {
            'forge' => ['api_token'],
            'coolify' => ['base_url', 'api_token'],
            'cloudflare' => ['api_token'],
            'porkbun' => ['api_key', 'secret_api_key'],
            default => [],
        };
    }

    /** Baris domain yang sedang dipakai (yang aktif, atau Cloudflare bila belum ada). */
    protected function domainRow(): DeploySetting
    {
        return DeploySetting::activeDomain() ?? DeploySetting::forProvider(DomainProvider::Cloudflare);
    }

    protected function lastTestText(DeployProvider|DomainProvider $provider): string
    {
        $row = DeploySetting::query()->where('provider', $provider->value)->first();

        if (! $row || ! $row->last_test_at) {
            return 'Belum pernah dites.';
        }

        return ($row->last_test_status === 'ok' ? 'Berhasil' : 'Gagal')
            .' — '.$row->last_test_at->format('d M Y, H:i')
            .' — '.$row->last_test_message;
    }

    protected function lastDeployText(DeployProvider $provider): string
    {
        $row = DeploySetting::query()->where('provider', $provider->value)->first();

        if (! $row || ! $row->last_deploy_at) {
            return 'Belum pernah deploy.';
        }

        return ($row->last_deploy_status === 'ok' ? 'Berhasil' : 'Gagal')
            .' — '.$row->last_deploy_at->format('d M Y, H:i')
            .' — '.$row->last_deploy_message;
    }

    protected function serverStatusText(): string
    {
        $row = DeploySetting::forProvider(DeployProvider::Forge);
        $serverId = $row->selectionValue('server_id');

        if (! $serverId) {
            return 'Belum ada server dipilih.';
        }

        return 'Server #'.$serverId
            .($row->selectionValue('server_name') ? ' ('.$row->selectionValue('server_name').')' : '')
            .' — status: '.($row->selectionValue('server_status') ?? 'belum dicek')
            .($row->selectionValue('server_ip') ? ', IP: '.$row->selectionValue('server_ip') : '');
    }

    /** Teks biaya untuk modal konfirmasi buat server. */
    protected function serverCostText(array $data): string
    {
        $vpsProvider = $data['vps_provider'] ?? 'hetzner';
        $size = $data['size'] ?? '';
        $estimate = $size ? ForgeService::priceEstimate($vpsProvider, $size) : null;
        $providerLabel = ForgeService::vpsProviders()[$vpsProvider] ?? $vpsProvider;

        $text = 'Server "' .($data['name'] ?? '—').'" ('.$providerLabel.', '.$size.') akan dibuat lewat Forge.';

        if ($estimate) {
            $text .= ' Perkiraan biaya VPS: '.$estimate.' — ditagih provider VPS setiap bulan, BUKAN oleh posme.';
        } else {
            $text .= ' Biaya mengikuti provider VPS yang dipilih — cek harga di situs provider sebelum lanjut.';
        }

        $text .= ' Server butuh ~10 menit provisioning setelah dibuat.';

        return $text;
    }

    protected function testProvider(DeployProvider|DomainProvider $provider): void
    {
        $data = $this->form->getState();
        $row = DeploySetting::forProvider($provider);
        $prefix = $provider->value;

        // Kredensial dari form bila diisi (bukan mask), kalau tidak dari DB.
        $credentials = [];
        foreach ($this->credentialFields($provider) as $field) {
            $submitted = $data["{$prefix}_{$field}"] ?? null;
            $credentials[$field] = filled($submitted) && ! str_starts_with((string) $submitted, '••••')
                ? $submitted
                : $row->credential($field);
        }

        $result = match ($provider->value) {
            'forge' => ForgeService::testConnection($credentials),
            'coolify' => CoolifyService::testConnection($credentials),
            'cloudflare' => CloudflareService::testConnection($credentials),
            'porkbun' => PorkbunService::testConnection($credentials),
            default => ['ok' => false, 'message' => 'Provider tidak dikenal.'],
        };

        $row->recordTest($result['ok'], $result['message']);

        Notification::make()
            ->title($result['ok'] ? 'Tes koneksi berhasil' : 'Tes koneksi gagal')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    /** Aksi berbayar — wajib centang persetujuan biaya, selain modal konfirmasi. */
    protected function createForgeServer(array $data): void
    {
        if (empty($data['confirmed'])) {
            Notification::make()
                ->title('Buat server dibatalkan')
                ->body('Centang dulu persetujuan biaya di form — server berbayar tidak boleh dibuat tanpa konfirmasi eksplisit.')
                ->danger()
                ->send();

            return;
        }

        $result = ForgeService::createServer(array_merge($data, [
            'provider' => $data['vps_provider'] ?? null,
        ]));

        if ($result['ok'] && isset($result['server']['id'])) {
            DeploySetting::forProvider(DeployProvider::Forge)->setSelection([
                'server_id' => $result['server']['id'],
                'server_name' => $result['server']['name'] ?? $data['name'],
                'server_status' => $result['server']['status'] ?? 'provisioning',
                'server_ip' => $result['server']['ip_address'] ?? null,
            ]);
        }

        Notification::make()
            ->title($result['ok'] ? 'Server dibuat' : 'Gagal membuat server')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    protected function refreshForgeServer(): void
    {
        $row = DeploySetting::forProvider(DeployProvider::Forge);
        $serverId = $row->selectionValue('server_id');

        if (! $serverId) {
            Notification::make()
                ->title('Belum ada server')
                ->body('Isi dulu Server ID di form (atau buat server baru), lalu refresh lagi.')
                ->warning()
                ->send();

            return;
        }

        $result = ForgeService::serverStatus($serverId);

        if ($result['ok'] && isset($result['server'])) {
            $row->setSelection([
                'server_status' => $result['server']['status'] ?? null,
                'server_ip' => $result['server']['ip_address'] ?? null,
                'server_name' => $result['server']['name'] ?? null,
            ]);
        }

        Notification::make()
            ->title($result['ok'] ? 'Status server diperbarui' : 'Gagal memeriksa status')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    protected function deployForgeSite(array $data): void
    {
        $row = DeploySetting::forProvider(DeployProvider::Forge);
        $serverId = $row->selectionValue('server_id');
        $siteId = $data['site_id'] ?? $row->selectionValue('site_id');

        if (! $serverId || ! $siteId) {
            Notification::make()
                ->title('Server / Site belum dipilih')
                ->body('Isi dulu Server ID dan Site ID di form Pengaturan Deploy.')
                ->warning()
                ->send();

            return;
        }

        $result = ForgeService::deploySite(
            $serverId,
            $siteId,
            ! empty($data['install_repo']) ? ($data['repository'] ?? null) : null,
            $data['branch'] ?? null,
        );

        $row->recordDeploy($result['ok'], $result['message']);

        Notification::make()
            ->title($result['ok'] ? 'Deploy dimulai' : 'Deploy gagal')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    protected function deployCoolifyApp(array $data): void
    {
        $row = DeploySetting::forProvider(DeployProvider::Coolify);
        $uuid = $data['app_uuid'] ?? $row->selectionValue('app_uuid');

        if (! $uuid) {
            Notification::make()
                ->title('Aplikasi belum dipilih')
                ->body('Isi dulu UUID aplikasi Coolify di form Pengaturan Deploy.')
                ->warning()
                ->send();

            return;
        }

        $result = CoolifyService::deployApplication($uuid);

        $row->recordDeploy($result['ok'], $result['message']);

        Notification::make()
            ->title($result['ok'] ? 'Deploy dimulai' : 'Deploy gagal')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    protected function createCloudflareDns(array $data): void
    {
        $result = CloudflareService::createDnsRecord($data['domain'], $data['ip']);

        if ($result['ok'] && isset($result['record_id'])) {
            $this->domainRow()->setSelection(['dns_record_id' => $result['record_id']]);
        }

        Notification::make()
            ->title($result['ok'] ? 'DNS dibuat' : 'Gagal membuat DNS')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    protected function checkPorkbunDomain(array $data): void
    {
        $result = PorkbunService::checkDomain($data['domain']);

        $body = $result['message'];

        if ($result['ok'] && ($result['available'] ?? false)) {
            $body .= ' Beli di sini: '.PorkbunService::buyUrl($data['domain']);
        }

        Notification::make()
            ->title($result['ok'] ? 'Cek domain selesai' : 'Cek domain gagal')
            ->body($body)
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }
}
