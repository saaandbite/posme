<?php

namespace Tests\Feature;

use App\Enums\DeployProvider;
use App\Enums\DomainProvider;
use App\Filament\Pages\DeploySettingsPage;
use App\Models\DeploySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DeploySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** @test */
    public function test_guest_cannot_open_deploy_settings_page(): void
    {
        $this->get('/admin/deploy-settings')->assertRedirect('/admin/login');
    }

    /** @test */
    public function test_credentials_are_stored_encrypted_and_shown_masked(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(DeploySettingsPage::class)
            ->set('data.forge_api_token', 'forge-token-123456')
            ->set('data.active_deploy_provider', DeployProvider::Forge->value)
            ->call('save');

        $row = DeploySetting::forProvider(DeployProvider::Forge);

        $this->assertTrue($row->is_active);
        $this->assertSame('forge-token-123456', $row->credential('api_token'));
        $this->assertSame('••••••••3456', $row->maskedCredential('api_token'));
        // Nilai mentah di database harus terenkripsi, bukan plaintext.
        $this->assertStringNotContainsString('forge-token-123456', (string) $row->getRawOriginal('credentials'));

        // Form menampilkan versi ter-mask setelah simpan.
        Livewire::test(DeploySettingsPage::class)
            ->assertSet('data.forge_api_token', '••••••••3456');
    }

    /** @test */
    public function test_saving_keeps_old_credentials_when_field_left_masked(): void
    {
        $this->actingAs($this->admin);

        DeploySetting::forProvider(DeployProvider::Forge)->update([
            'credentials' => ['api_token' => 'forge-token-123456'],
        ]);

        Livewire::test(DeploySettingsPage::class)
            ->set('data.active_deploy_provider', DeployProvider::Forge->value)
            ->call('save');

        $this->assertSame('forge-token-123456', DeploySetting::forProvider(DeployProvider::Forge)->credential('api_token'));
    }

    /** @test */
    public function test_only_one_deploy_and_one_domain_provider_can_be_active(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(DeploySettingsPage::class)
            ->set('data.active_deploy_provider', DeployProvider::Coolify->value)
            ->set('data.active_domain_provider', DomainProvider::Porkbun->value)
            ->call('save');

        $this->assertFalse(DeploySetting::forProvider(DeployProvider::Forge)->is_active);
        $this->assertTrue(DeploySetting::forProvider(DeployProvider::Coolify)->is_active);
        $this->assertFalse(DeploySetting::forProvider(DomainProvider::Cloudflare)->is_active);
        $this->assertTrue(DeploySetting::forProvider(DomainProvider::Porkbun)->is_active);
    }

    /** @test */
    public function test_forge_test_connection_calls_user_endpoint_and_records_result(): void
    {
        $this->actingAs($this->admin);

        DeploySetting::forProvider(DeployProvider::Forge)->update([
            'credentials' => ['api_token' => 'forge-token-123456'],
        ]);

        Http::fake([
            'https://forge.laravel.com/api/v1/user' => Http::response(['user' => ['name' => 'Haris']], 200),
        ]);

        Livewire::test(DeploySettingsPage::class)->callAction('testForge');

        Http::assertSent(fn ($request) => $request->url() === 'https://forge.laravel.com/api/v1/user'
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer forge-token-123456'));

        $row = DeploySetting::forProvider(DeployProvider::Forge);
        $this->assertSame('ok', $row->last_test_status);
        $this->assertNotNull($row->last_test_at);
    }

    /** @test */
    public function test_coolify_test_connection_calls_version_endpoint(): void
    {
        $this->actingAs($this->admin);

        DeploySetting::forProvider(DeployProvider::Coolify)->update([
            'credentials' => ['base_url' => 'https://coolify.example.test', 'api_token' => 'coolify-token-123'],
        ]);

        Http::fake([
            'https://coolify.example.test/api/v1/version' => Http::response(['version' => '4.0.0'], 200),
        ]);

        Livewire::test(DeploySettingsPage::class)->callAction('testCoolify');

        Http::assertSent(fn ($request) => $request->url() === 'https://coolify.example.test/api/v1/version'
            && $request->method() === 'GET');

        $this->assertSame('ok', DeploySetting::forProvider(DeployProvider::Coolify)->last_test_status);
    }

    /** @test */
    public function test_create_forge_server_does_nothing_without_cost_confirmation(): void
    {
        $this->actingAs($this->admin);

        DeploySetting::forProvider(DeployProvider::Forge)->update([
            'credentials' => ['api_token' => 'forge-token-123456'],
        ]);

        Http::fake();

        Livewire::test(DeploySettingsPage::class)->callAction('createForgeServer', [
            'vps_provider' => 'hetzner',
            'name' => 'posme-prod',
            'region' => 'fsn1',
            'size' => 'cx22',
            'ubuntu_version' => '24.04',
            'php_version' => 'php83',
            'database' => 'mysql8',
            // 'confirmed' sengaja tidak dicentang.
        ]);

        Http::assertSentCount(0);
        $this->assertNull(DeploySetting::forProvider(DeployProvider::Forge)->selectionValue('server_id'));
    }

    /** @test */
    public function test_create_forge_server_posts_to_forge_when_confirmed(): void
    {
        $this->actingAs($this->admin);

        DeploySetting::forProvider(DeployProvider::Forge)->update([
            'credentials' => ['api_token' => 'forge-token-123456'],
        ]);

        Http::fake([
            'https://forge.laravel.com/api/v1/servers' => Http::response([
                'server' => ['id' => 555, 'name' => 'posme-prod', 'status' => 'provisioning', 'ip_address' => null],
            ], 201),
        ]);

        Livewire::test(DeploySettingsPage::class)->callAction('createForgeServer', [
            'vps_provider' => 'hetzner',
            'name' => 'posme-prod',
            'region' => 'fsn1',
            'size' => 'cx22',
            'ubuntu_version' => '24.04',
            'php_version' => 'php83',
            'database' => 'mysql8',
            'confirmed' => true,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://forge.laravel.com/api/v1/servers'
                && $request->method() === 'POST'
                && $request['provider'] === 'hetzner'
                && $request['name'] === 'posme-prod'
                && $request['size'] === 'cx22'
                && $request['region'] === 'fsn1';
        });

        $row = DeploySetting::forProvider(DeployProvider::Forge);
        $this->assertSame(555, $row->selectionValue('server_id'));
        $this->assertSame('provisioning', $row->selectionValue('server_status'));
    }

    /** @test */
    public function test_deploy_forge_site_posts_deploy_without_installing_repo_by_default(): void
    {
        $this->actingAs($this->admin);

        DeploySetting::forProvider(DeployProvider::Forge)->update([
            'credentials' => ['api_token' => 'forge-token-123456'],
            'selection' => ['server_id' => 123, 'site_id' => 789],
        ]);

        Http::fake([
            'https://forge.laravel.com/api/v1/servers/123/sites/789/deploy' => Http::response(['id' => 42], 200),
        ]);

        Livewire::test(DeploySettingsPage::class)->callAction('deployForgeSite', [
            'site_id' => 789,
            'install_repo' => false,
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sites/789/deploy') && $request->method() === 'POST');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/repository'));

        $row = DeploySetting::forProvider(DeployProvider::Forge);
        $this->assertSame('ok', $row->last_deploy_status);
    }

    /** @test */
    public function test_deploy_forge_site_installs_repo_first_when_requested(): void
    {
        $this->actingAs($this->admin);

        DeploySetting::forProvider(DeployProvider::Forge)->update([
            'credentials' => ['api_token' => 'forge-token-123456'],
            'selection' => ['server_id' => 123, 'site_id' => 789],
        ]);

        Http::fake([
            'https://forge.laravel.com/api/v1/servers/123/sites/789/repository' => Http::response([], 200),
            'https://forge.laravel.com/api/v1/servers/123/sites/789/deploy' => Http::response(['id' => 42], 200),
        ]);

        Livewire::test(DeploySettingsPage::class)->callAction('deployForgeSite', [
            'site_id' => 789,
            'install_repo' => true,
            'repository' => 'saaandbite/posme',
            'branch' => 'main',
        ]);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/sites/789/repository')
                && $request->method() === 'POST'
                && $request['repository'] === 'saaandbite/posme'
                && $request['branch'] === 'main';
        });
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sites/789/deploy') && $request->method() === 'POST');
    }

    /** @test */
    public function test_deploy_coolify_app_posts_to_coolify_api(): void
    {
        $this->actingAs($this->admin);

        DeploySetting::forProvider(DeployProvider::Coolify)->update([
            'credentials' => ['base_url' => 'https://coolify.example.test', 'api_token' => 'coolify-token-123'],
            'selection' => ['app_uuid' => 'app-uuid-1'],
        ]);

        Http::fake([
            'https://coolify.example.test/api/v1/applications/app-uuid-1/deploy' => Http::response([], 200),
        ]);

        Livewire::test(DeploySettingsPage::class)->callAction('deployCoolifyApp', [
            'app_uuid' => 'app-uuid-1',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/applications/app-uuid-1/deploy')
            && $request->method() === 'POST');

        $this->assertSame('ok', DeploySetting::forProvider(DeployProvider::Coolify)->last_deploy_status);
    }

    /** @test */
    public function test_cloudflare_dns_record_posts_correct_payload(): void
    {
        $this->actingAs($this->admin);

        DeploySetting::forProvider(DomainProvider::Cloudflare)->update([
            'credentials' => ['api_token' => 'cf-token-123456'],
        ]);

        Http::fake(function ($request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/zones')) {
                return Http::response(['success' => true, 'result' => [['id' => 'zone-1']]], 200);
            }

            if (str_contains($request->url(), '/dns_records')) {
                return Http::response(['success' => true, 'result' => ['id' => 'rec-1']], 200);
            }

            return Http::response(['success' => false], 404);
        });

        Livewire::test(DeploySettingsPage::class)->callAction('createCloudflareDns', [
            'domain' => 'posme.my.id',
            'ip' => '1.2.3.4',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/zones/zone-1/dns_records')
                && $request->method() === 'POST'
                && $request['type'] === 'A'
                && $request['name'] === 'posme.my.id'
                && $request['content'] === '1.2.3.4';
        });
    }

    /** @test */
    public function test_porkbun_check_domain_reports_availability(): void
    {
        $this->actingAs($this->admin);

        DeploySetting::forProvider(DomainProvider::Porkbun)->update([
            'credentials' => ['api_key' => 'pk1_abc', 'secret_api_key' => 'sk1_abc'],
        ]);

        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'yes',
                'price' => '10.37',
            ], 200),
        ]);

        Livewire::test(DeploySettingsPage::class)->callAction('checkPorkbunDomain', [
            'domain' => 'posme.my.id',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/domain/checkDomain')
                && $request->method() === 'POST'
                && $request['domain'] === 'posme.my.id'
                && $request['apikey'] === 'pk1_abc';
        });
    }
}
