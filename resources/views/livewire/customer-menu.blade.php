<div class="mx-auto max-w-2xl">
    {{-- Masthead editorial: foto full-bleed, lalu nama resto besar di atas kertas.
         Tanpa panel gelap, tanpa pil oranye — tipografi dan foto yang bicara. --}}
    <div class="-mx-4">
        <img src="{{ asset('images/hero-kafe.jpg') }}" alt="Suasana {{ $settings->restaurant_name }}"
            class="h-56 w-full object-cover">
    </div>
    <div class="pt-5">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand">Kamu duduk di {{ $table->name }}</p>
        <h1 class="mt-2 font-display text-4xl font-bold leading-tight text-espresso">{{ $settings->restaurant_name }}</h1>
        <p class="mt-2 text-[15px] leading-relaxed text-cocoa">Pesan dari HP, bayar di kasir atau lewat HP. Pesananmu langsung masuk ke dapur.</p>
    </div>

    @error('order')
        <p class="mt-5 border-l-2 border-danger pl-3 text-sm font-medium text-danger">{{ $message }}</p>
    @enderror

    @if ($order)
        {{-- ============ LACAK PESANAN ============ --}}
        <div wire:poll.10s="refreshOrder" class="mt-7 border-t border-line pt-6">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand">Pesananmu</p>
            <p class="mt-2 font-display text-4xl font-bold tracking-wide text-espresso">{{ $order->order_number }}</p>
            <p class="mt-1 text-sm text-cocoa">{{ $table->name }} • {{ $order->created_at->format('H:i') }} WIB</p>

            {{-- Langkah status --}}
            @php
                $steps = \App\Enums\OrderStatus::flow();
                $currentIdx = array_search($order->status, $steps, true);
                $isCancelled = $order->status === \App\Enums\OrderStatus::Cancelled;
            @endphp

            @if ($isCancelled)
                <p class="mt-6 text-center font-display text-xl font-bold text-danger">Pesanan dibatalkan</p>
                @if ($order->cancelled_reason)
                    <p class="mt-1 text-center text-sm text-cocoa">({{ $order->cancelled_reason }})</p>
                @endif
            @else
                <ol class="mt-6">
                    @foreach ($steps as $step)
                        @php $idx = $loop->index; @endphp
                        <li class="flex items-baseline gap-4 border-b border-line py-3.5 last:border-b-0">
                            <span class="w-6 shrink-0 text-center font-display text-lg font-bold
                                {{ $idx < $currentIdx ? 'text-leaf' : ($idx === $currentIdx ? 'text-brand' : 'text-cocoa/50') }}">
                                {{ $idx < $currentIdx ? '✓' : $idx + 1 }}
                            </span>
                            <span class="text-base {{ $idx === $currentIdx ? 'font-bold text-espresso' : ($idx < $currentIdx ? 'text-cocoa' : 'text-cocoa/60') }}">
                                {{ $step->label() }}
                                @if ($idx === $currentIdx)
                                    <span class="block text-sm font-normal text-brand">sedang berlangsung</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif

            {{-- Item yang dipesan --}}
            <div class="mt-6 border-t border-line pt-4">
                @foreach ($order->items as $item)
                    <div class="flex justify-between py-1 text-sm">
                        <span>{{ $item->qty }}× {{ $item->name }}
                            @if ($item->note)<span class="block text-xs text-cocoa">Catatan: {{ $item->note }}</span>@endif
                        </span>
                        <span class="font-medium">{{ rupiah($item->subtotal) }}</span>
                    </div>
                @endforeach
                <div class="mt-2 flex justify-between border-t border-dashed border-line pt-2 font-display text-lg font-bold">
                    <span>Total</span><span>{{ rupiah($order->total) }}</span>
                </div>
            </div>

            {{-- Status bayar + tombol bayar --}}
            <div class="mt-6 border-t border-line pt-5 text-center">
                @if ($order->payment_status === \App\Enums\PaymentStatus::Paid)
                    <p class="font-display text-xl font-bold text-leaf-dark">Lunas — terima kasih!</p>
                    <p class="mt-1 text-sm text-cocoa">{{ $order->payment_method?->isGateway() ? 'Dibayar lewat ' . ($order->midtrans_payment_type ?? 'payment gateway') : 'Dibayar di kasir (' . ($order->payment_method?->label() ?? '—') . ')' }}</p>
                @elseif ($order->payment_status === \App\Enums\PaymentStatus::Waiting)
                    <p class="font-bold">Menunggu konfirmasi pembayaran…</p>
                    <p class="mt-1 text-sm text-cocoa">Jika sudah bayar, status akan berubah otomatis. Kalau belum, silakan ulangi pembayaran.</p>
                @else
                    <p class="font-bold">Belum bayar — {{ rupiah($order->total) }}</p>
                    @if ($activeGateway)
                        <button wire:click="startPayment" wire:loading.attr="disabled"
                            class="mt-4 h-12 w-full rounded-lg bg-brand text-base font-bold text-white hover:bg-brand-dark disabled:opacity-50">
                            <span wire:loading.remove>Bayar Sekarang</span>
                            <span wire:loading>Menyiapkan pembayaran…</span>
                        </button>
                        <p class="mt-2 text-xs text-cocoa">QRIS, virtual account, atau e-wallet — lewat {{ $activeGateway->label() }}.</p>
                    @else
                        <p class="mt-1 text-sm text-cocoa">Silakan bayar di kasir (tunai / QRIS) dengan menunjukkan nomor pesanan ini.</p>
                    @endif
                @endif
            </div>

            @error('payment')
                <p class="mt-4 border-l-2 border-danger pl-3 text-left text-sm font-medium text-danger">{{ $message }}</p>
            @enderror

            <button wire:click="newOrder" class="mt-6 h-12 w-full rounded-lg border border-espresso/30 text-base font-semibold text-espresso hover:bg-cream-dark">
                + Pesan lagi di {{ $table->name }}
            </button>
        </div>
    @else
        {{-- ============ MENU ============
             Editorial, bukan katalog: satu menu andalan tampil besar di atas,
             sisanya baris daftar dengan foto di kiri dan cerita di kanan.
             Tanpa kartu, tanpa bayangan — pemisahnya garis tipis. --}}
        @php $featureShown = false; @endphp
        @foreach ($categories as $category)
            @if ($category->menuItems->isNotEmpty())
                <div class="{{ $loop->first ? 'mt-7' : 'mt-10' }} flex items-baseline gap-3">
                    <h2 class="font-display text-[28px] font-bold leading-none text-espresso">{{ $category->name }}</h2>
                    <span class="h-px flex-1 bg-line"></span>
                </div>
                <div>
                    @foreach ($category->menuItems as $item)
                        @php
                            $inCart = $cart[$item->id]['qty'] ?? 0;
                            $showImage = $item->showsImage($settings) && $item->image_url;
                            $showDescription = $item->showsDescription($settings);
                            $isFeature = ($settings->menu_layout ?? 'grid') === 'grid' && ! $featureShown && $item->is_available;
                            if ($isFeature) {
                                $featureShown = true;
                            }
                        @endphp
                        @if ($isFeature)
                            {{-- Menu andalan: foto besar, nama besar, satu tombol utama. --}}
                            <div class="pt-5">
                                @if ($showImage)
                                    <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="h-52 w-full rounded-xl object-cover">
                                @endif
                                <p class="mt-4 text-xs font-bold uppercase tracking-[0.18em] text-brand">Andalan kami @if ($item->badge) — {{ $item->badge }} @endif</p>
                                <h3 class="mt-1.5 font-display text-[28px] font-bold leading-tight text-espresso">{{ $item->name }}</h3>
                                @if ($showDescription && $item->description)
                                    <p class="mt-1.5 text-[15px] leading-relaxed text-cocoa">{{ $item->description }}</p>
                                @endif
                                <p class="mt-3 font-display text-xl font-bold text-espresso">{{ rupiah($item->price) }}</p>
                                @if ($inCart > 0)
                                    <div class="mt-4 flex items-center gap-2">
                                        <button wire:click="decreaseQty({{ $item->id }})" aria-label="Kurangi {{ $item->name }}"
                                            class="flex h-12 flex-1 items-center justify-center rounded-lg border border-line bg-paper text-xl font-bold text-espresso">−</button>
                                        <span class="w-10 text-center font-display text-lg font-bold">{{ $inCart }}</span>
                                        <button wire:click="addToCart({{ $item->id }})" aria-label="Tambah {{ $item->name }}"
                                            class="flex h-12 flex-1 items-center justify-center rounded-lg bg-brand text-xl font-bold text-white">+</button>
                                    </div>
                                @else
                                    <button wire:click="addToCart({{ $item->id }})" aria-label="Tambah {{ $item->name }}"
                                        class="mt-4 h-12 w-full rounded-lg bg-brand text-base font-bold text-white hover:bg-brand-dark">
                                        + Tambah
                                    </button>
                                @endif
                                @if ($inCart > 0)
                                    <input type="text" placeholder="Catatan, mis. tidak pedas / es sedikit"
                                        value="{{ $cart[$item->id]['note'] ?? '' }}"
                                        wire:change="setNote({{ $item->id }}, $event.target.value)"
                                        class="mt-3 w-full rounded-lg border border-line bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                                @endif
                            </div>
                        @else
                            {{-- Baris daftar: foto kiri, cerita kanan, tombol bundar yang tenang. --}}
                            <div class="flex gap-4 border-b border-line py-5 {{ $item->is_available ? '' : 'opacity-60' }}">
                                @if ($showImage)
                                    <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="h-[88px] w-[88px] shrink-0 rounded-lg object-cover">
                                @endif
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-display text-lg font-bold leading-snug text-espresso">{{ $item->name }}
                                        @if ($item->badge)
                                            <span class="ml-1 font-sans text-[11px] font-bold uppercase tracking-[0.14em] text-brand">{{ $item->badge }}</span>
                                        @endif
                                    </h3>
                                    @if ($showDescription && $item->description)
                                        <p class="mt-0.5 text-sm leading-relaxed text-cocoa">{{ $item->description }}</p>
                                    @endif
                                    <p class="mt-1.5 text-[15px] font-bold text-espresso">{{ rupiah($item->price) }}</p>
                                    @if (! $item->is_available)
                                        <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.14em] text-danger">Habis</p>
                                    @endif
                                    @if ($inCart > 0)
                                        <input type="text" placeholder="Catatan, mis. tidak pedas / es sedikit"
                                            value="{{ $cart[$item->id]['note'] ?? '' }}"
                                            wire:change="setNote({{ $item->id }}, $event.target.value)"
                                            class="mt-3 w-full rounded-lg border border-line bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                                    @endif
                                </div>
                                @if ($item->is_available)
                                    <div class="flex shrink-0 flex-col items-center justify-center gap-2">
                                        @if ($inCart > 0)
                                            <button wire:click="decreaseQty({{ $item->id }})" aria-label="Kurangi {{ $item->name }}"
                                                class="flex h-11 w-11 items-center justify-center rounded-full border border-line bg-paper text-xl font-bold text-espresso">−</button>
                                            <span class="text-base font-bold">{{ $inCart }}</span>
                                            <button wire:click="addToCart({{ $item->id }})" aria-label="Tambah {{ $item->name }}"
                                                class="flex h-11 w-11 items-center justify-center rounded-full bg-brand text-xl font-bold text-white">+</button>
                                        @else
                                            <button wire:click="addToCart({{ $item->id }})" aria-label="Tambah {{ $item->name }}"
                                                class="flex h-11 w-11 items-center justify-center rounded-full border border-espresso/30 text-xl font-bold text-espresso">+</button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        @endforeach

        @if (empty($cart))
            <p class="mt-10 text-center text-sm leading-relaxed text-cocoa">
                Belum ada yang dipilih. Geser ke bawah, pilih yang kamu suka.
            </p>
        @endif

        {{-- Bar keranjang (sticky): kertas terang dengan garis atas, bukan panel gelap. --}}
        @if ($this->cartCount > 0)
            <div class="sticky bottom-0 -mx-4 mt-8 border-t border-line bg-paper px-4 py-4 shadow-[0_-6px_20px_rgba(43,33,26,0.08)]">
                <div class="mx-auto max-w-2xl">
                    <details class="mb-3">
                        <summary class="cursor-pointer text-sm font-semibold text-espresso">
                            Lihat keranjang ({{ $this->cartCount }} item) — {{ rupiah($this->totals['total']) }}
                        </summary>
                        <div class="mt-3 space-y-2">
                            @foreach ($this->cartItems as $line)
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <span class="min-w-0 flex-1">
                                        {{ $line['qty'] }}× {{ $line['menu_item']->name }}
                                        @if ($line['note'])<span class="block text-xs text-cocoa">{{ $line['note'] }}</span>@endif
                                    </span>
                                    <span class="font-medium">{{ rupiah($line['subtotal']) }}</span>
                                    <button wire:click="removeFromCart({{ $line['menu_item']->id }})" class="text-danger" aria-label="Hapus">✕</button>
                                </div>
                            @endforeach
                            <div class="border-t border-dashed border-line pt-2 text-sm text-cocoa">
                                <div class="flex justify-between"><span>Subtotal</span><span>{{ rupiah($this->totals['subtotal']) }}</span></div>
                                @if ($this->totals['tax'] > 0)
                                    <div class="flex justify-between"><span>Pajak {{ $settings->tax_percent }}%</span><span>{{ rupiah($this->totals['tax']) }}</span></div>
                                @endif
                                @if ($this->totals['service'] > 0)
                                    <div class="flex justify-between"><span>Service {{ $settings->service_percent }}%</span><span>{{ rupiah($this->totals['service']) }}</span></div>
                                @endif
                                <div class="flex justify-between font-display text-lg font-bold text-espresso"><span>Total</span><span>{{ rupiah($this->totals['total']) }}</span></div>
                            </div>
                            <input type="text" placeholder="Catatan untuk pesanan ini (opsional)"
                                wire:model.defer="customerNote"
                                class="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                        </div>
                    </details>
                    <button wire:click="submitOrder" wire:loading.attr="disabled"
                        class="h-[52px] w-full rounded-lg bg-brand text-lg font-bold text-white hover:bg-brand-dark disabled:opacity-50">
                        <span wire:loading.remove>Kirim Pesanan • {{ rupiah($this->totals['total']) }}</span>
                        <span wire:loading>Mengirim pesanan…</span>
                    </button>
                    <p class="mt-2 text-center text-xs text-cocoa">Pesanan langsung masuk ke dapur. Pembayaran bisa lewat HP atau di kasir.</p>
                </div>
            </div>
        @endif
    @endif

    @if ($activeGateway === \App\Enums\GatewayProvider::Midtrans)
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
