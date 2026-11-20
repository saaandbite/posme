<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Livewire\CashierTerminal;
use App\Livewire\KitchenBoard;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Pertahanan berlapis staf + lacak pesanan customer.
 * (Login throttle, otorisasi di dalam komponen Livewire, debug mati di
 * produksi, order meja lain tidak muncul di lacak pesanan.)
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->seed();
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@posme.test')->first();
    }

    protected function cashier(): User
    {
        return User::where('email', 'kasir@posme.test')->first();
    }

    protected function kitchen(): User
    {
        return User::where('email', 'dapur@posme.test')->first();
    }

    protected function buatOrder(OrderStatus $status = OrderStatus::Pending): Order
    {
        $table = DiningTable::first();
        $item = MenuItem::first();

        Livewire::test('customer-menu', ['table' => $table])
            ->call('addToCart', $item->id)
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::latest()->first();
        $order->update(['status' => $status]);

        return $order;
    }

    // ============ 1. Throttle login ============

    public function test_login_gagal_5_kali_lalu_dikunci_sementara(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'kasir@posme.test', 'password' => 'salah']);
        }

        // Percobaan ke-6: terkunci, bukan lagi "email atau password salah".
        $this->post('/login', ['email' => 'kasir@posme.test', 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            session('errors')->get('email')[0]
        );
        $this->assertGuest();
    }

    public function test_login_benar_tetap_bisa_setelah_kunci_habis(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'kasir@posme.test', 'password' => 'salah']);
        }

        $this->post('/login', ['email' => 'kasir@posme.test', 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        // Simulasi kunci habis (lewat 1 menit): login benar jalan lagi.
        RateLimiter::clear('kasir@posme.test|127.0.0.1');

        $this->post('/login', ['email' => 'kasir@posme.test', 'password' => 'password'])
            ->assertRedirect('/cashier');

        $this->assertAuthenticatedAs($this->cashier());
    }

    public function test_login_benar_membersihkan_hitungan_gagal(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => 'kasir@posme.test', 'password' => 'salah']);
        }

        // Belum terkunci (baru 4 gagal): login benar berhasil.
        $this->post('/login', ['email' => 'kasir@posme.test', 'password' => 'password'])
            ->assertRedirect('/cashier');
    }

    public function test_pesan_login_gagal_tetap_generik(): void
    {
        $this->post('/login', ['email' => 'tidak-ada@posme.test', 'password' => 'salah'])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);
    }

    // ============ 2. Otorisasi di dalam komponen Livewire ============

    public function test_kitchen_tidak_bisa_membuka_komponen_kasir(): void
    {
        $this->actingAs($this->kitchen());

        // abort(403) di mount() diubah Livewire-test menjadi response 403.
        Livewire::test(CashierTerminal::class)->assertStatus(403);
    }

    public function test_cashier_tidak_bisa_membuka_komponen_dapur(): void
    {
        $this->actingAs($this->cashier());

        Livewire::test(KitchenBoard::class)->assertStatus(403);
    }

    public function test_kitchen_tidak_bisa_memanggil_aksi_kasir(): void
    {
        $order = $this->buatOrder(OrderStatus::Ready);

        // Panggil aksi langsung (tanpa mount) sebagai kitchen: harus 403
        // dan status order tidak berubah.
        $this->actingAs($this->kitchen());

        try {
            app(CashierTerminal::class)->markServed($order->id);
            $this->fail('Aksi markServed seharusnya menolak role kitchen.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        $this->assertEquals(OrderStatus::Ready, $order->fresh()->status);
    }

    public function test_cashier_tidak_bisa_memanggil_aksi_dapur(): void
    {
        $order = $this->buatOrder(OrderStatus::Pending);

        $this->actingAs($this->cashier());

        try {
            app(KitchenBoard::class)->advanceOrder($order->id);
            $this->fail('Aksi advanceOrder seharusnya menolak role cashier.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        $this->assertEquals(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_admin_bisa_membuka_komponen_kasir_dan_dapur(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CashierTerminal::class)->assertOk();
        Livewire::test(KitchenBoard::class)->assertOk();
    }

    // ============ 3. Debug mati saat production ============

    public function test_debug_dimatikan_saat_production(): void
    {
        // environment() dibaca dari app, bukan config('app.env').
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse(config('app.debug'));
    }

    public function test_debug_tetap_mengikuti_config_di_lokal(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertTrue(config('app.debug'));
    }

    // ============ 4. Lacak pesanan hanya milik meja sendiri ============

    public function test_order_meja_lain_tidak_muncul_di_lacak_pesanan(): void
    {
        $tableA = DiningTable::where('code', 'MEJA1')->first();
        $tableB = DiningTable::where('code', 'MEJA2')->first();
        $item = MenuItem::first();

        Livewire::test('customer-menu', ['table' => $tableB])
            ->call('addToCart', $item->id)
            ->call('submitOrder')
            ->assertHasNoErrors();

        $orderB = Order::latest()->first();
        $this->assertEquals($tableB->id, $orderB->dining_table_id);

        // Session seolah tertukar: order meja B tersimpan di kunci meja A.
        session()->put('posme.order.'.$tableA->code, $orderB->id);

        Livewire::test('customer-menu', ['table' => $tableA])
            ->assertSet('order', null);
    }
}
