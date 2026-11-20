<?php

namespace Tests\Feature;

use App\Enums\GatewayProvider;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Pages\GatewaySettingsPage;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\GatewaySetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class LowcodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Cache::flush();

        $this->seed();
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@posme.test')->first();
    }

    protected function signedMenuUrl(DiningTable $table): string
    {
        return $table->orderUrl();
    }

    /** Buat order lewat Livewire customer-menu, kembalikan modelnya. */
    protected function makeOrder(DiningTable $table, MenuItem $item, int $qty = 1): Order
    {
        Livewire::test('customer-menu', ['table' => $table])
            ->call('addToCart', $item->id)
            ->call('submitOrder')
            ->assertHasNoErrors();

        // latest('id') — bukan latest() — supaya tidak tertukar bila dua
        // order dibuat dalam detik yang sama (created_at presisi detik).
        return Order::orderByDesc('id')->first();
    }

    public function test_reorder_kategori_dan_item_tersimpan_dan_tercermin_di_menu(): void
    {
        $table = DiningTable::first();

        $catB = Category::create(['name' => 'Kategori B Unik', 'sort_order' => 1, 'is_active' => true]);
        $catA = Category::create(['name' => 'Kategori A Unik', 'sort_order' => 2, 'is_active' => true]);

        // Kategori tanpa item tidak tampil di menu — beri masing-masing 1 item.
        MenuItem::create([
            'category_id' => $catB->id, 'name' => 'Item Kategori B', 'price' => 5000,
            'sort_order' => 1, 'is_active' => true, 'is_available' => true,
        ]);

        $item2 = MenuItem::create([
            'category_id' => $catA->id, 'name' => 'Item Dua Unik', 'price' => 5000,
            'sort_order' => 1, 'is_active' => true, 'is_available' => true,
        ]);
        $item1 = MenuItem::create([
            'category_id' => $catA->id, 'name' => 'Item Satu Unik', 'price' => 5000,
            'sort_order' => 2, 'is_active' => true, 'is_available' => true,
        ]);

        // Awal: B dulu, lalu A; di dalam A: Dua dulu, lalu Satu.
        $this->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertSeeInOrder(['Kategori B Unik', 'Kategori A Unik'])
            ->assertSeeInOrder(['Item Dua Unik', 'Item Satu Unik']);

        // Tukar urutan (seperti drag-and-drop di admin).
        $catA->update(['sort_order' => 0]);
        $item1->update(['sort_order' => 0]);

        $this->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertSeeInOrder(['Kategori A Unik', 'Kategori B Unik'])
            ->assertSeeInOrder(['Item Satu Unik', 'Item Dua Unik']);
    }

    public function test_kategori_dan_item_nonaktif_tidak_tampil_di_menu(): void
    {
        $table = DiningTable::first();

        $hiddenCat = Category::create(['name' => 'Kategori Sembunyi', 'sort_order' => 1, 'is_active' => false]);
        MenuItem::create([
            'category_id' => $hiddenCat->id, 'name' => 'Item Sembunyi', 'price' => 5000,
            'is_active' => true, 'is_available' => true,
        ]);

        $visibleCat = Category::first();
        $hiddenItem = MenuItem::create([
            'category_id' => $visibleCat->id, 'name' => 'Item Nonaktif Unik', 'price' => 5000,
            'is_active' => false, 'is_available' => true,
        ]);

        $this->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertDontSee('Kategori Sembunyi')
            ->assertDontSee('Item Sembunyi')
            ->assertDontSee('Item Nonaktif Unik');

        // Aktifkan lagi — harus muncul.
        $hiddenCat->update(['is_active' => true]);
        $hiddenItem->update(['is_active' => true]);

        $this->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertSee('Kategori Sembunyi')
            ->assertSee('Item Nonaktif Unik');
    }

    public function test_toggle_foto_dan_deskripsi_menyembunyikan_field_di_menu(): void
    {
        $table = DiningTable::first();
        $category = Category::first();

        $item = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Item Toggle Unik',
            'description' => 'Deskripsi Toggle Unik',
            'price' => 5000,
            'image' => 'menu-images/toggle-unik.jpg',
            'is_active' => true,
            'is_available' => true,
        ]);

        // Default: foto & deskripsi tampil.
        $this->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertSee('Deskripsi Toggle Unik')
            ->assertSee('menu-images/toggle-unik.jpg');

        // Matikan per item.
        $item->update(['show_image' => false, 'show_description' => false]);

        $this->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertDontSee('Deskripsi Toggle Unik')
            ->assertDontSee('menu-images/toggle-unik.jpg');

        // Default global mati, tapi toggle per item "Ya" mengalahkan.
        Setting::current()->update([
            'menu_default_show_image' => false,
            'menu_default_show_description' => false,
        ]);
        Setting::flush();

        $item->update(['show_image' => true, 'show_description' => true]);

        $this->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertSee('Deskripsi Toggle Unik')
            ->assertSee('menu-images/toggle-unik.jpg');

        // Default global mati dan per item null (ikuti default) → tersembunyi.
        $item->update(['show_image' => null, 'show_description' => null]);

        $this->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertDontSee('Deskripsi Toggle Unik')
            ->assertDontSee('menu-images/toggle-unik.jpg');
    }

    public function test_badge_tampil_dan_layout_grid_atau_list_mengikuti_pengaturan(): void
    {
        $table = DiningTable::first();
        $category = Category::first();

        MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Item Badge Unik',
            'description' => 'Deskripsi badge',
            'price' => 5000,
            'badge' => 'Best Seller',
            'is_active' => true,
            'is_available' => true,
        ]);

        // Default layout grid: editorial — satu menu andalan besar di atas,
        // sisanya baris daftar; tidak ada lagi grid 2 kolom yang seragam.
        $this->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertSee('Best Seller')
            ->assertSee('Andalan kami')
            ->assertDontSee('grid grid-cols-2 gap-3', false);

        // Ganti ke list: tanpa menu andalan, semua jadi baris daftar.
        Setting::current()->update(['menu_layout' => 'list']);
        Setting::flush();

        $this->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertSee('Best Seller')
            ->assertDontSee('Andalan kami')
            ->assertDontSee('grid grid-cols-2 gap-3', false);
    }

    public function test_gateway_settings_tersimpan_terenkripsi_dan_tampil_ter_mask(): void
    {
        $row = GatewaySetting::forProvider(GatewayProvider::Midtrans);
        $row->update([
            'credentials' => ['server_key' => 'test-server-key-1234', 'client_key' => 'test-client-key-5678'],
        ]);

        // Nilai mentah di database TIDAK mengandung key aslinya.
        $raw = DB::table('gateway_settings')->where('provider', 'midtrans')->value('credentials');
        $this->assertStringNotContainsString('test-server-key-1234', (string) $raw);
        $this->assertStringNotContainsString('test-client-key-5678', (string) $raw);

        // Lewat model, key terbaca kembali (didekripsi otomatis).
        $this->assertSame('test-server-key-1234', $row->fresh()->credential('server_key'));

        // Tampilan ter-mask: hanya 4 karakter terakhir.
        $this->assertSame('••••••••1234', $row->fresh()->maskedCredential('server_key'));
        $this->assertSame('••••••••5678', $row->fresh()->maskedCredential('client_key'));
    }

    public function test_halaman_pengaturan_gateway_bisa_dibuka_dan_disimpan_admin(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/gateway-settings')->assertOk();

        Livewire::test(GatewaySettingsPage::class)
            ->set('data.active_provider', 'midtrans')
            ->set('data.midtrans_server_key', 'server-key-baru-9999')
            ->set('data.midtrans_client_key', 'client-key-baru-8888')
            ->set('data.midtrans_is_production', false)
            ->call('save')
            ->assertHasNoErrors();

        $row = GatewaySetting::forProvider(GatewayProvider::Midtrans);
        $this->assertTrue($row->is_active);
        $this->assertSame('server-key-baru-9999', $row->credential('server_key'));
        $this->assertSame('client-key-baru-8888', $row->credential('client_key'));
        $this->assertFalse($row->is_production);

        // Simpan ulang tanpa mengubah key → key lama tetap (nilai ter-mask diabaikan).
        Livewire::test(GatewaySettingsPage::class)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('server-key-baru-9999', $row->fresh()->credential('server_key'));
    }

    public function test_alur_bayar_memakai_gateway_aktif_dari_database(): void
    {
        // Hanya database yang diisi — .env kosong.
        config(['services.midtrans.server_key' => null, 'services.midtrans.client_key' => null]);

        GatewaySetting::forProvider(GatewayProvider::Midtrans)->update([
            'is_active' => true,
            'credentials' => ['server_key' => 'db-server-key', 'client_key' => 'db-client-key'],
        ]);

        $this->assertSame(GatewayProvider::Midtrans, PaymentGateway::activeProvider());
        $this->assertSame(GatewayProvider::Midtrans, PaymentGateway::usable());
        $this->assertTrue(MidtransService::configured());
        $this->assertSame('db-client-key', MidtransService::clientKey());

        // Menu customer menampilkan tombol bayar (gateway aktif).
        $table = DiningTable::first();
        $item = MenuItem::first();
        $order = $this->makeOrder($table, $item);

        $this->withSession(['posme.order.'.$table->code => $order->id])
            ->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertSee('Bayar Sekarang')
            ->assertSee('lewat Midtrans');
    }

    public function test_tanpa_gateway_aktif_fallback_kasir_jalan_tanpa_error(): void
    {
        config(['services.midtrans.server_key' => null, 'services.midtrans.client_key' => null]);

        $this->assertNull(PaymentGateway::activeProvider());
        $this->assertNull(PaymentGateway::usable());
        $this->assertFalse(MidtransService::configured());

        $table = DiningTable::first();
        $item = MenuItem::first();
        $order = $this->makeOrder($table, $item);

        $this->withSession(['posme.order.'.$table->code => $order->id])
            ->get($this->signedMenuUrl($table))
            ->assertOk()
            ->assertSee('bayar di kasir')
            ->assertDontSee('Bayar Sekarang');

        // startPayment tanpa gateway → error ramah, bukan exception.
        Livewire::test('customer-menu', ['table' => $table])
            ->set('order', $order)
            ->call('startPayment')
            ->assertHasErrors('payment');
    }

    public function test_kredensial_db_mengalahkan_env_untuk_verifikasi_signature(): void
    {
        config(['services.midtrans.server_key' => 'env-key-lama']);

        GatewaySetting::forProvider(GatewayProvider::Midtrans)->update([
            'is_active' => true,
            'credentials' => ['server_key' => 'db-key-baru', 'client_key' => 'db-client-key'],
        ]);

        $table = DiningTable::first();
        $item = MenuItem::first();
        $order = $this->makeOrder($table, $item);

        $payload = [
            'order_id' => $order->order_number,
            'status_code' => '200',
            'gross_amount' => (string) $order->total,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
        ];

        // Signature dengan key DB → diterima.
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'db-key-baru');
        $this->postJson('/midtrans/notification', $payload)->assertOk();
        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertEquals(PaymentMethod::Midtrans, $order->fresh()->payment_method);

        // Signature dengan key env lama → ditolak (DB yang berlaku).
        $order2 = $this->makeOrder($table, $item);
        $payload['order_id'] = $order2->order_number;
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'env-key-lama');
        $this->postJson('/midtrans/notification', $payload)->assertForbidden();
        $this->assertEquals(PaymentStatus::Unpaid, $order2->fresh()->payment_status);
    }

    public function test_webhook_xendit_token_salah_ditolak_token_benar_menandai_lunas(): void
    {
        GatewaySetting::forProvider(GatewayProvider::Xendit)->update([
            'is_active' => true,
            'credentials' => ['secret_key' => 'xendit-secret-test', 'callback_token' => 'token-rahasia-123'],
        ]);

        $table = DiningTable::first();
        $item = MenuItem::first();
        $order = $this->makeOrder($table, $item);

        $payload = [
            'id' => 'inv-test-001',
            'external_id' => $order->order_number,
            'status' => 'PAID',
            'amount' => $order->total,
        ];

        // Tanpa token → 403.
        $this->postJson('/xendit/notification', $payload)->assertForbidden();
        $this->assertEquals(PaymentStatus::Unpaid, $order->fresh()->payment_status);

        // Token salah → 403.
        $this->withHeader('x-callback-token', 'token-salah')
            ->postJson('/xendit/notification', $payload)->assertForbidden();
        $this->assertEquals(PaymentStatus::Unpaid, $order->fresh()->payment_status);

        // Token benar + PAID → lunas.
        $this->withHeader('x-callback-token', 'token-rahasia-123')
            ->postJson('/xendit/notification', $payload)->assertOk();

        $order->refresh();
        $this->assertEquals(PaymentStatus::Paid, $order->payment_status);
        $this->assertEquals(PaymentMethod::Xendit, $order->payment_method);
        $this->assertEquals('inv-test-001', $order->xendit_invoice_id);
        $this->assertNotNull($order->paid_at);
    }

    public function test_webhook_xendit_expired_mengembalikan_status_belum_bayar(): void
    {
        GatewaySetting::forProvider(GatewayProvider::Xendit)->update([
            'is_active' => true,
            'credentials' => ['secret_key' => 'xendit-secret-test', 'callback_token' => 'token-rahasia-123'],
        ]);

        $table = DiningTable::first();
        $item = MenuItem::first();
        $order = $this->makeOrder($table, $item);
        $order->update(['payment_status' => PaymentStatus::Waiting, 'xendit_invoice_id' => 'inv-test-002']);

        $this->withHeader('x-callback-token', 'token-rahasia-123')
            ->postJson('/xendit/notification', [
                'id' => 'inv-test-002',
                'external_id' => $order->order_number,
                'status' => 'EXPIRED',
                'amount' => $order->total,
            ])->assertOk();

        $this->assertEquals(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->xendit_invoice_id);
    }

    public function test_enum_status_dan_role_bekerja(): void
    {
        $this->assertSame('Dipesan', OrderStatus::Pending->label());
        $this->assertSame('Lunas', PaymentStatus::Paid->label());
        $this->assertSame('Kasir', \App\Enums\UserRole::Cashier->label());
        $this->assertSame('Midtrans', GatewayProvider::Midtrans->label());
        $this->assertSame(OrderStatus::Preparing, OrderStatus::Pending->next());
        $this->assertNull(OrderStatus::Served->next());

        $table = DiningTable::first();
        $item = MenuItem::first();
        $order = $this->makeOrder($table, $item);

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertTrue($order->isActive());

        // Format Rupiah memakai Number::currency (satu tempat: helper rupiah()).
        $this->assertStringStartsWith('Rp', rupiah(15000));
        $this->assertStringContainsString('15.000', rupiah(15000));
    }
}
