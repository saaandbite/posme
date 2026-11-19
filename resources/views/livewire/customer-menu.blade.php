<div class="mx-auto max-w-2xl">
    {{-- Header resto & meja --}}
    <div class="mb-4 rounded-2xl bg-amber-600 px-5 py-4 text-white shadow">
        <h1 class="text-xl font-bold">{{ $settings->restaurant_name }}</h1>
        <p class="text-sm text-amber-100">Anda memesan di <span class="font-bold text-white">{{ $table->name }}</span> — pesan dari HP, tanpa antre di kasir.</p>
    </div>

    @error('order')
        <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $message }}</div>
    @enderror

    @if ($order)
        {{-- ============ LACAK PESANAN ============ --}}
        <div wire:poll.10s="refreshOrder">
            <div class="rounded-2xl bg-white p-5 shadow">
                <p class="text-sm text-stone-500">Nomor pesanan</p>
                <p class="text-2xl font-bold tracking-wide">{{ $order->order_number }}</p>
                <p class="mt-1 text-sm text-stone-500">{{ $table->name }} • {{ $order->created_at->format('H:i') }} WIB</p>

                {{-- Langkah status --}}
                @php
                    $steps = ['pending' => 'Dipesan', 'preparing' => 'Disiapkan', 'ready' => 'Siap', 'served' => 'Disajikan'];
                    $currentIdx = array_search($order->status, array_keys($steps));
                    $isCancelled = $order->status === 'cancelled';
                @endphp

                @if ($isCancelled)
                    <div class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-center font-bold text-red-700">
                        Pesanan dibatalkan
                        @if ($order->cancelled_reason)
                            <span class="block text-sm font-normal">({{ $order->cancelled_reason }})</span>
                        @endif
                    </div>
                @else
                    <ol class="mt-5 space-y-0">
                        @foreach ($steps as $key => $label)
                            @php $idx = $loop->index; @endphp
                            <li class="flex items-start gap-3 pb-4 last:pb-0">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold
                                    {{ $idx < $currentIdx ? 'bg-green-600 text-white' : ($idx === $currentIdx ? 'bg-amber-600 text-white ring-4 ring-amber-200' : 'bg-stone-200 text-stone-500') }}">
                                    {{ $idx < $currentIdx ? '✓' : $idx + 1 }}
                                </span>
                                <span class="pt-1 text-base {{ $idx === $currentIdx ? 'font-bold text-stone-900' : ($idx < $currentIdx ? 'text-stone-600' : 'text-stone-400') }}">
                                    {{ $label }}
                                    @if ($idx === $currentIdx)
                                        <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700">sedang berlangsung</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif

                {{-- Item yang dipesan --}}
                <div class="mt-5 border-t border-stone-200 pt-4">
                    @foreach ($order->items as $item)
                        <div class="flex justify-between py-1 text-sm">
                            <span>{{ $item->qty }}× {{ $item->name }}
                                @if ($item->note)<span class="block text-xs text-stone-500">Catatan: {{ $item->note }}</span>@endif
                            </span>
                            <span class="font-medium">{{ rupiah($item->subtotal) }}</span>
                        </div>
                    @endforeach
                    <div class="mt-2 flex justify-between border-t border-dashed border-stone-300 pt-2 text-base font-bold">
                        <span>Total</span><span>{{ rupiah($order->total) }}</span>
                    </div>
                </div>

                {{-- Status bayar + tombol bayar --}}
                <div class="mt-4 rounded-xl px-4 py-3 text-center
                    {{ $order->payment_status === 'paid' ? 'bg-green-50 text-green-800' : ($order->payment_status === 'waiting' ? 'bg-yellow-50 text-yellow-800' : 'bg-stone-100 text-stone-700') }}">
                    @if ($order->payment_status === 'paid')
                        <p class="font-bold">✓ Lunas — terima kasih!</p>
                        <p class="text-sm">{{ $order->payment_method === 'midtrans' ? 'Dibayar lewat ' . ($order->midtrans_payment_type ?? 'payment gateway') : 'Dibayar di kasir (' . $order->payment_method . ')' }}</p>
                    @elseif ($order->payment_status === 'waiting')
                        <p class="font-bold">Menunggu konfirmasi pembayaran…</p>
                        <p class="text-sm">Jika sudah bayar, status akan berubah otomatis. Kalau belum, silakan ulangi pembayaran.</p>
                    @else
                        <p class="font-bold">Belum bayar — {{ rupiah($order->total) }}</p>
                        @if ($gatewayConfigured)
                            <button wire:click="startPayment" wire:loading.attr="disabled"
                                class="mt-3 w-full rounded-xl bg-emerald-600 py-3 text-base font-bold text-white hover:bg-emerald-700 disabled:opacity-50">
                                <span wire:loading.remove>Bayar Sekarang</span>
                                <span wire:loading>Menyiapkan pembayaran…</span>
                            </button>
                            <p class="mt-2 text-xs">QRIS, virtual account, atau e-wallet — lewat Midtrans.</p>
                        @else
                            <p class="mt-2 text-sm">Silakan bayar di kasir (tunai / QRIS) dengan menunjukkan nomor pesanan ini.</p>
                        @endif
                    @endif
                </div>

                @error('payment')
                    <div class="mt-3 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $message }}</div>
                @enderror

                <button wire:click="newOrder" class="mt-4 w-full rounded-xl border border-stone-300 py-3 text-base font-semibold text-stone-700 hover:bg-stone-50">
                    + Pesan lagi di {{ $table->name }}
                </button>
            </div>
        </div>
    @else
        {{-- ============ MENU ============ --}}
        @foreach ($categories as $category)
            @if ($category->menuItems->isNotEmpty())
                <h2 class="mb-2 mt-6 text-lg font-bold text-stone-800 first:mt-0">{{ $category->name }}</h2>
                <div class="space-y-3">
                    @foreach ($category->menuItems as $item)
                        @php $inCart = $cart[$item->id]['qty'] ?? 0; @endphp
                        <div class="rounded-2xl bg-white p-4 shadow-sm {{ $item->is_available ? '' : 'opacity-60' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-base font-bold">{{ $item->name }}</h3>
                                    @if ($item->description)
                                        <p class="mt-0.5 text-sm text-stone-500">{{ $item->description }}</p>
                                    @endif
                                    <p class="mt-1 text-base font-bold text-amber-700">{{ rupiah($item->price) }}</p>
                                    @if (! $item->is_available)
                                        <p class="mt-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">Habis</p>
                                    @endif
                                </div>
                                @if ($item->is_available)
                                    @if ($inCart > 0)
                                        <div class="flex shrink-0 items-center gap-2">
                                            <button wire:click="decreaseQty({{ $item->id }})" aria-label="Kurangi"
                                                class="flex h-10 w-10 items-center justify-center rounded-full bg-stone-200 text-xl font-bold">−</button>
                                            <span class="w-6 text-center text-lg font-bold">{{ $inCart }}</span>
                                            <button wire:click="addToCart({{ $item->id }})" aria-label="Tambah"
                                                class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-xl font-bold text-white">+</button>
                                        </div>
                                    @else
                                        <button wire:click="addToCart({{ $item->id }})"
                                            class="shrink-0 rounded-full bg-amber-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-700">
                                            + Tambah
                                        </button>
                                    @endif
                                @endif
                            </div>
                            @if ($inCart > 0)
                                <input type="text" placeholder="Catatan, mis. tidak pedas / es sedikit"
                                    value="{{ $cart[$item->id]['note'] ?? '' }}"
                                    wire:change="setNote({{ $item->id }}, $event.target.value)"
                                    class="mt-3 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none">
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach

        @if (empty($cart))
            <p class="mt-8 rounded-2xl bg-white p-5 text-center text-sm text-stone-500 shadow-sm">
                Belum ada item di keranjang. Pilih menu di atas untuk mulai memesan.
            </p>
        @endif

        {{-- Bar keranjang (sticky) --}}
        @if ($this->cartCount > 0)
            <div class="sticky bottom-0 -mx-4 mt-6 border-t border-stone-200 bg-white px-4 py-4 shadow-[0_-4px_16px_rgba(0,0,0,0.08)]">
                <div class="mx-auto max-w-2xl">
                    <details class="mb-3">
                        <summary class="cursor-pointer text-sm font-semibold text-stone-700">
                            Lihat keranjang ({{ $this->cartCount }} item) — {{ rupiah($this->totals['total']) }}
                        </summary>
                        <div class="mt-3 space-y-2">
                            @foreach ($this->cartItems as $line)
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <span class="min-w-0 flex-1">
                                        {{ $line['qty'] }}× {{ $line['menu_item']->name }}
                                        @if ($line['note'])<span class="block text-xs text-stone-500">{{ $line['note'] }}</span>@endif
                                    </span>
                                    <span class="font-medium">{{ rupiah($line['subtotal']) }}</span>
                                    <button wire:click="removeFromCart({{ $line['menu_item']->id }})" class="text-red-600" aria-label="Hapus">✕</button>
                                </div>
                            @endforeach
                            <div class="border-t border-dashed border-stone-300 pt-2 text-sm text-stone-600">
                                <div class="flex justify-between"><span>Subtotal</span><span>{{ rupiah($this->totals['subtotal']) }}</span></div>
                                @if ($this->totals['tax'] > 0)
                                    <div class="flex justify-between"><span>Pajak {{ $settings->tax_percent }}%</span><span>{{ rupiah($this->totals['tax']) }}</span></div>
                                @endif
                                @if ($this->totals['service'] > 0)
                                    <div class="flex justify-between"><span>Service {{ $settings->service_percent }}%</span><span>{{ rupiah($this->totals['service']) }}</span></div>
                                @endif
                                <div class="flex justify-between text-base font-bold text-stone-900"><span>Total</span><span>{{ rupiah($this->totals['total']) }}</span></div>
                            </div>
                            <input type="text" placeholder="Catatan untuk pesanan ini (opsional)"
                                wire:model.defer="customerNote"
                                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none">
                        </div>
                    </details>
                    <button wire:click="submitOrder" wire:loading.attr="disabled"
                        class="w-full rounded-xl bg-amber-600 py-3.5 text-lg font-bold text-white hover:bg-amber-700 disabled:opacity-50">
                        <span wire:loading.remove>Kirim Pesanan • {{ rupiah($this->totals['total']) }}</span>
                        <span wire:loading>Mengirim pesanan…</span>
                    </button>
                    <p class="mt-2 text-center text-xs text-stone-400">Pesanan langsung masuk ke dapur. Pembayaran bisa lewat HP atau di kasir.</p>
                </div>
            </div>
        @endif
    @endif

    @if ($gatewayConfigured)
        <script src="{{ \App\Services\MidtransService::snapJsUrl() }}" data-client-key="{{ \App\Services\MidtransService::clientKey() }}"></script>
        <script>
            // Setelah popup Snap ditutup/berhasil, muat ulang halaman supaya
            // status bayar terbaru tampil (webhook Midtrans yang memastikan).
            window.addEventListener('snap-ready', function (event) {
                if (typeof snap === 'undefined') { return; }
                snap.pay(event.detail.token, {
                    onSuccess: function () { location.reload(); },
                    onPending: function () { location.reload(); },
                    onError: function () { alert('Pembayaran gagal. Silakan coba lagi atau bayar di kasir.'); },
                    onClose: function () { location.reload(); }
                });
            });
        </script>
    @endif
</div>
