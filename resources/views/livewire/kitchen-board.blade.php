<div class="-mx-4 -my-6 min-h-screen bg-coal px-4 py-6 text-sand" wire:poll.5s>
    <div class="mx-auto max-w-7xl" data-max-order-id="{{ $this->maxOrderId }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-3xl font-bold tracking-tight">Dapur — Order Masuk</h1>
                <p class="text-sm text-sand-dim">{{ now()->format('d M Y, H:i') }} WIB • refresh otomatis tiap 5 detik</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-full bg-danger px-4 py-2 text-lg font-bold text-white">{{ $this->orders->where('status', \App\Enums\OrderStatus::Pending)->count() }} Baru</span>
                <span class="rounded-full bg-butter px-4 py-2 text-lg font-bold text-espresso">{{ $this->orders->where('status', \App\Enums\OrderStatus::Preparing)->count() }} Disiapkan</span>
                <span class="rounded-full bg-leaf px-4 py-2 text-lg font-bold text-white">{{ $this->orders->where('status', \App\Enums\OrderStatus::Ready)->count() }} Siap</span>
            </div>
        </div>

        @if ($this->orders->isEmpty())
            <div class="mt-10 rounded-xl border-2 border-dashed border-coal-line p-12 text-center">
                <p class="font-display text-xl font-semibold text-sand">Belum ada order masuk</p>
                <p class="mt-1 text-sand-dim">Order baru dari customer akan muncul di sini otomatis.</p>
            </div>
        @else
            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->orders as $order)
                    @php
                        $isNew = $order->created_at->diffInSeconds(now()) < 90;
                        $badge = match ($order->status) {
                            \App\Enums\OrderStatus::Pending => 'bg-danger text-white',
                            \App\Enums\OrderStatus::Preparing => 'bg-butter text-espresso',
                            \App\Enums\OrderStatus::Ready => 'bg-leaf text-white',
                            default => 'bg-coal-line text-sand',
                        };
                        $nextLabel = match ($order->status) {
                            \App\Enums\OrderStatus::Pending => 'Mulai Siapkan',
                            \App\Enums\OrderStatus::Preparing => 'Tandai Siap',
                            \App\Enums\OrderStatus::Ready => 'Tandai Disajikan',
                            default => null,
                        };
                    @endphp
                    <article class="rounded-xl bg-coal-card p-5 shadow-lg ring-1 ring-coal-line {{ $isNew ? 'animate-pulse ring-4 ring-danger' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-display text-2xl font-extrabold">{{ $order->diningTable->name }}</p>
                                <p class="text-sm text-sand-dim">{{ $order->order_number }} • {{ $order->created_at->format('H:i') }} ({{ $order->created_at->diffForHumans() }})</p>
                            </div>
                            <span class="shrink-0 rounded-full px-3 py-1.5 text-sm font-bold {{ $badge }}">
                                {{ $order->status->label() }}
                                @if ($isNew)
                                    • BARU
                                @endif
                            </span>
                        </div>

                        <ul class="mt-4 space-y-2 border-t border-coal-line pt-3">
                            @foreach ($order->items as $item)
                                <li class="flex justify-between gap-2 text-lg">
                                    <span class="font-semibold">{{ $item->qty }}× {{ $item->name }}
                                        @if ($item->note)
                                            <span class="block text-sm font-normal text-honey">Catatan: {{ $item->note }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        @if ($order->customer_note)
                            <p class="mt-3 rounded-lg bg-coal px-3 py-2 text-sm text-honey">Catatan pesanan: {{ $order->customer_note }}</p>
                        @endif

                        <div class="mt-4 flex items-center justify-between gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $order->payment_status === \App\Enums\PaymentStatus::Paid ? 'bg-leaf-soft text-leaf-dark' : 'bg-danger-soft text-danger' }}">
                                {{ $order->payment_status->label() }}
                            </span>
                            @if ($nextLabel)
                                <button wire:click="advanceOrder({{ $order->id }})"
                                    class="flex-1 rounded-lg bg-brand px-4 py-3 text-lg font-extrabold text-white hover:bg-brand-dark">
                                    {{ $nextLabel }}
                                </button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <script>
        // Bunyi "ding" saat ada order baru (tanpa library tambahan).
        (function () {
            let lastMax = Number(document.querySelector('[data-max-order-id]')?.dataset.maxOrderId || 0);
            let ctx = null;
            function beep() {
                try {
                    ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
                    [880, 1174].forEach(function (freq, i) {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.frequency.value = freq;
                        osc.connect(gain); gain.connect(ctx.destination);
                        const t = ctx.currentTime + i * 0.18;
                        gain.gain.setValueAtTime(0.15, t);
                        gain.gain.exponentialRampToValueAtTime(0.001, t + 0.5);
                        osc.start(t); osc.stop(t + 0.5);
                    });
                } catch (e) { /* abaikan bila browser memblokir audio */ }
            }
            document.addEventListener('livewire:navigated', function () { lastMax = Number(document.querySelector('[data-max-order-id]')?.dataset.maxOrderId || 0); });
            // Livewire morph selesai -> cek apakah ada order baru.
            document.addEventListener('livewire:morph-updated', function () {
                const el = document.querySelector('[data-max-order-id]');
                if (!el) return;
                const current = Number(el.dataset.maxOrderId || 0);
                if (current > lastMax) { beep(); lastMax = current; }
            });
        })();
    </script>
</div>
