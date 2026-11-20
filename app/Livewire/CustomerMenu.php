<?php

namespace App\Livewire;

use App\Enums\GatewayProvider;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Services\MidtransService;
use App\Services\PaymentGateway;
use App\Services\XenditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Halaman menu customer (tanpa login, dibuka lewat QR / signed URL).
 * Keranjang disimpan di session per meja supaya tidak hilang saat refresh.
 * Harga SELALU dihitung ulang dari database di server — tidak ada harga
 * yang dipercaya dari request.
 */
class CustomerMenu extends Component
{
    public DiningTable $table;

    /** [menu_item_id => ['qty' => int, 'note' => string]] */
    public array $cart = [];

    public ?Order $order = null;

    /** Gateway yang aktif dan terkonfigurasi — null = bayar di kasir saja. */
    public ?GatewayProvider $activeGateway = null;

    public string $customerNote = '';

    public function mount(DiningTable $table): void
    {
        $this->table = $table;
        $this->cart = session()->get('posme.cart.'.$table->code, []);
        $this->activeGateway = PaymentGateway::usable();

        // Lanjutkan lacak pesanan yang masih aktif di meja ini.
        // Order harus milik meja yang sedang dibuka — order meja lain
        // (mis. session tertukar) diabaikan, tidak ditampilkan.
        $orderId = session()->get('posme.order.'.$table->code);
        if ($orderId) {
            $order = Order::with('items')->find($orderId);
            if ($order && $order->status !== OrderStatus::Cancelled && $order->dining_table_id === $table->id) {
                $this->order = $order;
            }
        }
    }

    public function addToCart(int $menuItemId): void
    {
        $item = MenuItem::available()->find($menuItemId);
        if (! $item) {
            return;
        }

        $qty = ($this->cart[$menuItemId]['qty'] ?? 0) + 1;
        if ($qty > 20) {
            $qty = 20;
        }

        $this->cart[$menuItemId] = [
            'qty' => $qty,
            'note' => $this->cart[$menuItemId]['note'] ?? '',
        ];
        $this->saveCart();
    }

    public function decreaseQty(int $menuItemId): void
    {
        if (! isset($this->cart[$menuItemId])) {
            return;
        }

        $this->cart[$menuItemId]['qty']--;

        if ($this->cart[$menuItemId]['qty'] <= 0) {
            unset($this->cart[$menuItemId]);
        }

        $this->saveCart();
    }

    public function removeFromCart(int $menuItemId): void
    {
        unset($this->cart[$menuItemId]);
        $this->saveCart();
    }

    public function setNote(int $menuItemId, string $note): void
    {
        if (! isset($this->cart[$menuItemId])) {
            return;
        }

        $this->cart[$menuItemId]['note'] = mb_substr(trim($note), 0, 200);
        $this->saveCart();
    }

    protected function saveCart(): void
    {
        session()->put('posme.cart.'.$this->table->code, $this->cart);
    }

    /** Item keranjang lengkap dengan harga dari database. */
    #[Computed]
    public function cartItems(): array
    {
        if (empty($this->cart)) {
            return [];
        }

        $items = MenuItem::whereIn('id', array_keys($this->cart))->get()->keyBy('id');
        $result = [];

        foreach ($this->cart as $id => $line) {
            $menuItem = $items->get($id);
            if (! $menuItem || ! $menuItem->is_active || ! $menuItem->is_available) {
                continue;
            }

            $result[] = [
                'menu_item' => $menuItem,
                'qty' => (int) $line['qty'],
                'note' => $line['note'] ?? '',
                'subtotal' => $menuItem->price * (int) $line['qty'],
            ];
        }

        return $result;
    }

    #[Computed]
    public function cartCount(): int
    {
        return (int) collect($this->cart)->sum('qty');
    }

    #[Computed]
    public function totals(): array
    {
        $subtotal = (int) collect($this->cartItems)->sum('subtotal');
        $settings = Setting::current();

        $tax = (int) round($subtotal * ((float) $settings->tax_percent / 100));
        $service = (int) round($subtotal * ((float) $settings->service_percent / 100));

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'service' => $service,
            'total' => $subtotal + $tax + $service,
        ];
    }

    public function submitOrder(): void
    {
        // Rate limit: maks. 5 order per 5 menit per meja + IP.
        $key = 'order-submit:'.$this->table->id.':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('order', 'Terlalu banyak percobaan. Coba lagi sebentar lagi.');

            return;
        }
        RateLimiter::hit($key, 300);

        $lines = $this->cartItems;

        if (empty($lines)) {
            $this->addError('order', 'Keranjang masih kosong.');

            return;
        }

        $order = DB::transaction(function () use ($lines) {
            $order = Order::create([
                'dining_table_id' => $this->table->id,
                'order_number' => Order::generateNumber(),
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'customer_note' => mb_substr(trim($this->customerNote), 0, 500) ?: null,
                'subtotal' => 0,
                'tax_amount' => 0,
                'service_amount' => 0,
                'total' => 0,
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $line['menu_item']->id,
                    'name' => $line['menu_item']->name,
                    'price' => $line['menu_item']->price,
                    'qty' => $line['qty'],
                    'note' => $line['note'] ?: null,
                    'subtotal' => $line['subtotal'],
                ]);
            }

            $order->recalculateTotals();

            return $order;
        });

        $this->cart = [];
        $this->customerNote = '';
        session()->forget('posme.cart.'.$this->table->code);
        session()->put('posme.order.'.$this->table->code, $order->id);

        $this->order = $order->load('items');
    }

    /**
     * Mulai pembayaran lewat gateway yang aktif:
     * Midtrans → popup Snap; Xendit → redirect ke halaman Invoice;
     * tidak ada gateway → bayar di kasir (tidak error).
     */
    public function startPayment(): void
    {
        if (! $this->order || ! $this->order->canBePaid()) {
            return;
        }

        $order = $this->order->fresh();

        match ($this->activeGateway) {
            GatewayProvider::Midtrans => $this->startMidtransPayment($order),
            GatewayProvider::Xendit => $this->startXenditPayment($order),
            default => $this->addError('payment', 'Pembayaran online tidak aktif. Silakan bayar di kasir.'),
        };
    }

    /** Minta Snap token lalu buka popup Midtrans di HP customer. */
    protected function startMidtransPayment(Order $order): void
    {
        $token = MidtransService::createSnapToken($order);

        if (! $token) {
            $this->addError('payment', 'Pembayaran online sedang tidak tersedia. Silakan bayar di kasir.');

            return;
        }

        $this->dispatch('snap-ready', token: $token);
    }

    /** Buat Invoice Xendit lalu arahkan customer ke halaman pembayarannya. */
    protected function startXenditPayment(Order $order): void
    {
        $url = XenditService::createInvoice($order);

        if (! $url) {
            $this->addError('payment', 'Pembayaran online sedang tidak tersedia. Silakan bayar di kasir.');

            return;
        }

        $this->redirect($url);
    }

    /** Dipanggil polling dari halaman lacak status. */
    public function refreshOrder(): void
    {
        if ($this->order) {
            $this->order = $this->order->fresh()->load('items');
        }
    }

    /** Kembali ke menu untuk membuat pesanan baru di meja yang sama. */
    public function newOrder(): void
    {
        $this->order = null;
        session()->forget('posme.order.'.$this->table->code);
    }

    public function render()
    {
        $categories = \App\Models\Category::where('is_active', true)
            ->orderBy('sort_order')
            ->with(['menuItems' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->get();

        return view('livewire.customer-menu', [
            'categories' => $categories,
            'settings' => Setting::current(),
        ])->layout('layouts.app', ['title' => 'Menu — '.$this->table->name]);
    }
}
