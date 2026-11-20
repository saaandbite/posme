<?php

namespace Tests\Feature;

use App\Filament\Resources\DiningTableResource\Pages\ListDiningTables;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Hardening QR customer: token acak per meja + signature kedaluwarsa,
 * TANPA PIN (harus seamless — customer scan langsung pesan).
 */
class QrHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Cache::flush();

        $this->seed();
    }

    public function test_url_qr_memakai_token_acak_bukan_kode_meja(): void
    {
        $table = DiningTable::where('code', 'MEJA5')->first();

        $this->assertNotNull($table->token);
        $this->assertEquals(32, strlen($table->token));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $table->token);

        // URL QR berisi token, bukan kode meja; ada parameter expires.
        $this->assertStringContainsString('/m/'.$table->token, $table->orderUrl());
        $this->assertStringNotContainsString('MEJA5', $table->orderUrl());
        $this->assertStringContainsString('expires=', $table->orderUrl());
    }

    public function test_token_tiap_meja_berbeda_dan_tidak_bisa_ditebak(): void
    {
        $tokens = DiningTable::pluck('token')->all();

        $this->assertCount(6, array_unique($tokens));

        foreach ($tokens as $token) {
            $this->assertStringNotContainsString('MEJA', $token);
        }

        // Menebak pola kode lama tidak menemukan meja apa pun.
        $this->get('/m/MEJA8')->assertNotFound();
    }

    public function test_token_dibuat_otomatis_untuk_meja_baru(): void
    {
        $a = DiningTable::create(['name' => 'Meja 7', 'code' => 'MEJA7', 'is_active' => true]);
        $b = DiningTable::create(['name' => 'Meja 8', 'code' => 'MEJA8', 'is_active' => true]);

        $this->assertNotEmpty($a->token);
        $this->assertEquals(32, strlen($a->token));
        $this->assertNotEquals($a->token, $b->token);
    }

    public function test_url_qr_valid_bisa_dibuka(): void
    {
        $table = DiningTable::where('code', 'MEJA5')->first();

        $this->get($table->orderUrl())->assertOk();
    }

    public function test_url_tanpa_signature_ditolak(): void
    {
        $table = DiningTable::where('code', 'MEJA5')->first();

        $this->get('/m/'.$table->token)->assertForbidden();
    }

    public function test_url_dengan_token_acak_yang_tidak_ada_ditolak(): void
    {
        $table = DiningTable::where('code', 'MEJA5')->first();

        // Ganti token di path dengan token acak lain, signature tetap.
        $tampered = str_replace('/m/'.$table->token, '/m/'.Str::random(32), $table->orderUrl());

        $this->get($tampered)->assertNotFound();
    }

    public function test_url_dengan_token_meja_lain_ditolak(): void
    {
        $table5 = DiningTable::where('code', 'MEJA5')->first();
        $table6 = DiningTable::where('code', 'MEJA6')->first();

        // Pakai URL meja 5 tapi ganti token-nya dengan token meja 6:
        // signature tidak lagi cocok dengan URL-nya.
        $tampered = str_replace('/m/'.$table5->token, '/m/'.$table6->token, $table5->orderUrl());

        $this->get($tampered)->assertForbidden();
    }

    public function test_signature_kedaluwarsa_ditolak(): void
    {
        $table = DiningTable::where('code', 'MEJA5')->first();

        $expired = URL::temporarySignedRoute(
            'customer.menu',
            now()->subMinute(),
            ['table' => $table->token]
        );

        $this->get($expired)->assertForbidden();
    }

    public function test_masa_berlaku_default_30_hari(): void
    {
        $this->assertEquals(30, config('posme.qr_url_expiry_days'));

        $table = DiningTable::first();
        $expiresAt = $table->qrExpiresAt();

        $this->assertTrue($expiresAt->isAfter(now()->addDays(29)));
        $this->assertTrue($expiresAt->isBefore(now()->addDays(31)));

        // Parameter expires di URL QR ≈ 30 hari dari sekarang.
        parse_str((string) parse_url($table->orderUrl(), PHP_URL_QUERY), $query);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, (int) $query['expires'], 5);
    }

    public function test_setelah_rotasi_token_url_lama_tidak_berlaku(): void
    {
        $table = DiningTable::where('code', 'MEJA5')->first();
        $oldUrl = $table->orderUrl();
        $oldToken = $table->token;

        $this->get($oldUrl)->assertOk();

        $table->rotateToken();

        $this->assertNotEquals($oldToken, $table->fresh()->token);
        $this->assertNotEquals($oldUrl, $table->fresh()->orderUrl());

        // Token lama tidak ditemukan lagi — URL lama mati.
        $this->get($oldUrl)->assertNotFound();

        // URL baru dari token baru bisa dibuka.
        $this->get($table->fresh()->orderUrl())->assertOk();
    }

    public function test_halaman_print_qr_menampilkan_tanggal_kedaluwarsa(): void
    {
        $admin = User::where('email', 'admin@posme.test')->first();
        $table = DiningTable::where('code', 'MEJA5')->first();

        $this->actingAs($admin)
            ->get('/admin/print-qr-codes')
            ->assertOk()
            ->assertSee('QR berlaku sampai')
            ->assertSee($table->qrExpiresAt()->format('d M Y'))
            ->assertSee($table->name);
    }

    public function test_aksi_putar_token_di_admin_mengganti_token(): void
    {
        $admin = User::where('email', 'admin@posme.test')->first();
        $table = DiningTable::where('code', 'MEJA5')->first();
        $oldToken = $table->token;

        $this->actingAs($admin);

        Livewire::test(ListDiningTables::class)
            ->mountTableAction('rotateToken', $table)
            ->callMountedTableAction();

        $this->assertNotEquals($oldToken, $table->fresh()->token);
        $this->assertEquals(32, strlen($table->fresh()->token));
    }

    public function test_order_dari_token_meja_5_masuk_ke_meja_5(): void
    {
        $table5 = DiningTable::where('code', 'MEJA5')->first();
        $table6 = DiningTable::where('code', 'MEJA6')->first();
        $item = MenuItem::first();

        // Buka URL QR meja 5 (token) — halaman bisa dibuka.
        $this->get($table5->orderUrl())->assertOk();

        Livewire::test('customer-menu', ['table' => $table5])
            ->call('addToCart', $item->id)
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::latest()->first();

        $this->assertNotNull($order);
        $this->assertEquals($table5->id, $order->dining_table_id);
        $this->assertNotEquals($table6->id, $order->dining_table_id);
    }
}
