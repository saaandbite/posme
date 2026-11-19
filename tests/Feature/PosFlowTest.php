<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class PosFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rate limiter order memakai cache array yang hidup sepanjang proses
        // PHPUnit — bersihkan supaya tiap test mulai dari nol.
        \Illuminate\Support\Facades\Cache::flush();

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

    protected function signedMenuUrl(DiningTable $table): string
    {
        return URL::signedRoute('customer.menu', ['table' => $table->code]);
    }

    public function test_customer_dapat_membuat_order_lewat_signed_url(): void
    {
        $table = DiningTable::first();
        $item = MenuItem::first();

        Livewire::test('customer-menu', ['table' => $table])
            ->call('addToCart', $item->id)
            ->call('addToCart', $item->id)
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::latest()->first();

        $this->assertNotNull($order);
        $this->assertEquals($table->id, $order->dining_table_id);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(Order::PAYMENT_UNPAID, $order->payment_status);
        $this->assertEquals(2 * $item->price, $order->subtotal);
        $this->assertEquals(2 * $item->price, $order->total);
        $this->assertCount(1, $order->items);
        $this->assertEquals(2, $order->items->first()->qty);
    }

    public function test_harga_order_diambil_dari_server_bukan_dari_request(): void
    {
        $table = DiningTable::first();
        $item = MenuItem::first();

        // Customer nakal mencoba menyelipkan harga palsu Rp 1 di cart.
        Livewire::test('customer-menu', ['table' => $table])
            ->set('cart', [
                $item->id => ['qty' => 3, 'note' => '', 'price' => 1],
            ])
            ->call('submitOrder');

        $order = Order::latest()->first();

        $this->assertNotNull($order);
        $this->assertEquals($item->price, $order->items->first()->price);
        $this->assertEquals(3 * $item->price, $order->total);
    }

    public function test_url_menu_tanpa_signature_ditolak(): void
    {
        $table = DiningTable::first();

        $this->get('/m/'.$table->code)->assertForbidden();
    }

    public function test_customer_tidak_bisa_membuka_halaman_staf(): void
    {
        $this->get('/kitchen')->assertRedirect('/login');
        $this->get('/cashier')->assertRedirect('/login');
        $this->get('/admin')->assertRedirectContains('login');
    }

    public function test_kitchen_tidak_bisa_membuka_panel_admin_dan_kasir(): void
    {
        $this->actingAs($this->kitchen());

        $this->get('/admin')->assertStatus(403);
        $this->get('/cashier')->assertStatus(403);
        // Board dapur boleh dibuka.
        $this->get('/kitchen')->assertOk();
    }

    public function test_cashier_tidak_bisa_membuka_panel_admin_dan_dapur(): void
    {
        $this->actingAs($this->cashier());

        $this->get('/admin')->assertStatus(403);
        $this->get('/kitchen')->assertStatus(403);
        $this->get('/cashier')->assertOk();
    }

    public function test_admin_bisa_membuka_panel_admin(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin')->assertOk();
    }

    public function test_cashier_bisa_menandai_order_lunas_dan_kembalian_benar(): void
    {
        $table = DiningTable::first();
        $item = MenuItem::where('price', 10000)->first();

        Livewire::test('customer-menu', ['table' => $table])
            ->call('addToCart', $item->id)
            ->call('submitOrder');

        $order = Order::latest()->first();
        $this->assertEquals(10000, $order->total);

        $this->actingAs($this->cashier());

        Livewire::test('cashier-terminal')
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 50000)
            ->call('confirmPayment')
            ->assertHasNoErrors();

        $order->refresh();

        $this->assertEquals(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertEquals('cash', $order->payment_method);
        $this->assertEquals(50000, $order->paid_amount);
        $this->assertEquals(40000, $order->change_amount);
        $this->assertNotNull($order->paid_at);
    }

    public function test_cashier_tidak_bisa_membayar_dengan_uang_kurang(): void
    {
        $table = DiningTable::first();
        $item = MenuItem::first();

        Livewire::test('customer-menu', ['table' => $table])
            ->call('addToCart', $item->id)
            ->call('submitOrder');

        $order = Order::latest()->first();

        $this->actingAs($this->cashier());

        Livewire::test('cashier-terminal')
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 100)
            ->call('confirmPayment')
            ->assertHasErrors('payment');

        $this->assertEquals(Order::PAYMENT_UNPAID, $order->fresh()->payment_status);
    }

    public function test_order_batal_tidak_bisa_dibayar(): void
    {
        $table = DiningTable::first();
        $item = MenuItem::first();

        Livewire::test('customer-menu', ['table' => $table])
            ->call('addToCart', $item->id)
            ->call('submitOrder');

        $order = Order::latest()->first();
        $order->update(['status' => Order::STATUS_CANCELLED, 'cancelled_reason' => 'Stok habis']);

        $this->actingAs($this->cashier());

        Livewire::test('cashier-terminal')
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('cashReceived', 50000)
            ->call('confirmPayment')
            ->assertHasErrors('payment');

        $this->assertEquals(Order::PAYMENT_UNPAID, $order->fresh()->payment_status);
    }

    public function test_dapur_bisa_memajukan_status_order(): void
    {
        $table = DiningTable::first();
        $item = MenuItem::first();

        Livewire::test('customer-menu', ['table' => $table])
            ->call('addToCart', $item->id)
            ->call('submitOrder');

        $order = Order::latest()->first();

        $this->actingAs($this->kitchen());

        Livewire::test('kitchen-board')
            ->call('advanceOrder', $order->id);

        $this->assertEquals(Order::STATUS_PREPARING, $order->fresh()->status);

        Livewire::test('kitchen-board')
            ->call('advanceOrder', $order->id)
            ->call('advanceOrder', $order->id);

        $order->refresh();
        $this->assertEquals(Order::STATUS_SERVED, $order->status);
        $this->assertNotNull($order->served_at);
    }

    public function test_webhook_midtrans_dengan_signature_valid_menandai_lunas(): void
    {
        config(['services.midtrans.server_key' => 'test-server-key']);

        $table = DiningTable::first();
        $item = MenuItem::first();

        Livewire::test('customer-menu', ['table' => $table])
            ->call('addToCart', $item->id)
            ->call('submitOrder');

        $order = Order::latest()->first();

        $payload = [
            'order_id' => $order->order_number,
            'status_code' => '200',
            'gross_amount' => (string) $order->total,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-server-key');

        $this->postJson('/midtrans/notification', $payload)->assertOk();

        $order->refresh();
        $this->assertEquals(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertEquals('midtrans', $order->payment_method);
        $this->assertEquals('qris', $order->midtrans_payment_type);
    }

    public function test_webhook_midtrans_dengan_signature_salah_ditolak(): void
    {
        config(['services.midtrans.server_key' => 'test-server-key']);

        $table = DiningTable::first();
        $item = MenuItem::first();

        Livewire::test('customer-menu', ['table' => $table])
            ->call('addToCart', $item->id)
            ->call('submitOrder');

        $order = Order::latest()->first();

        $this->postJson('/midtrans/notification', [
            'order_id' => $order->order_number,
            'status_code' => '200',
            'gross_amount' => (string) $order->total,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'signature_key' => 'tanda-tangan-palsu',
        ])->assertForbidden();

        $this->assertEquals(Order::PAYMENT_UNPAID, $order->fresh()->payment_status);
    }

    public function test_gateway_belum_dikonfigurasi_tidak_error(): void
    {
        config(['services.midtrans.server_key' => null, 'services.midtrans.client_key' => null]);

        $this->assertFalse(MidtransService::configured());

        $table = DiningTable::first();

        // Halaman menu tetap bisa dibuka; fallback "Bayar di kasir" tampil.
        $this->get($this->signedMenuUrl($table))->assertOk();
    }
}
