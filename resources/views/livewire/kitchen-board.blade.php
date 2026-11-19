<div class="bg-stone-950 -mx-4 -my-6 min-h-screen px-4 py-6 text-stone-100" wire:poll.5s>
    <div class="mx-auto max-w-7xl" data-max-order-id="{{ $this->maxOrderId }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Dapur — Order Masuk</h1>
                <p class="text-sm text-stone-400">{{ now()->format('d M Y, H:i') }} WIB • refresh otomatis tiap 5 detik</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-full bg-red-600 px-4 py-2 text-lg font-bold">{{ $this->orders->where('status', 'pending')->count() }} Baru</span>
                <span class="rounded-full bg-amber-500 px-4 py-2 text-lg font-bold text-stone-950">{{ $this->orders->where('status', 'preparing')->count() }} Disiapkan</span>
                <span class="rounded-full bg-green-600 px-4 py-2 text-lg font-bold">{{ $this->orders->where('status', 'ready')->count() }} Siap</span>
            </div>
        </div>

        @if ($this->orders->isEmpty())
            <div class="mt-10 rounded-2xl border-2 border-dashed border-stone-700 p-12 text-center">
                <p class="text-xl font-semibold text-stone-300">Belum ada order masuk</p>
                <p class="mt-1 text-stone-500">Order baru dari customer akan muncul di sini otomatis.</p>
            </div>
        @else
            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->orders as $order)
                    @php
                        $isNew = $order->created_at->diffInSeconds(now()) < 90;
                        $badge = match ($order->status) {
                            'pending' => 'bg-red-600',
                            'preparing' => 'bg-amber-500 text-stone-950',
                            'ready' => 'bg-green-600',
                            default => 'bg-stone-600',
                        };
                        $nextLabel = match ($order->status) {
                            'pending' => 'Mulai Siapkan',
                            'preparing' => 'Tandai Siap',
                            'ready' => 'Tandai Disajikan',
                            default => null,
                        };
                    @endphp
                    <article class="rounded-2xl bg-stone-900 p-5 shadow-lg ring-1 ring-stone-800 {{ $isNew ? 'animate-pulse ring-4 ring-red-500' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-2xl font-extrabold">{{ $order->diningTable->name }}</p>
                                <p class="text-sm text-stone-400">{{ $order->order_number }} • {{ $order->created_at->format('H:i') }} ({{ $order->created_at->diffForHumans() }})</p>
                            </div>
                            <span class="shrink-0 rounded-full px-3 py-1.5 text-sm font-bold {{ $badge }}">
                                {{ order_status_label($order->status) }}
                                @if ($isNew)
                                    • BARU
                                @endif
                            </span>
                        </div>

                        <ul class="mt-4 space-y-2 border-t border-stone-800 pt-3">
                            @foreach ($order->items as $item)
                                <li class="flex justify-between gap-2 text-lg">
                                    <span class="font-semibold">{{ $item->qty }}× {{ $item->name }}
                                        @if ($item->note)
                                            <span class="block text-sm font-normal text-amber-300">Catatan: {{ $item->note }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        @if ($order->customer_note)
                            <p class="mt-3 rounded-lg bg-stone-800 px-3 py-2 text-sm text-amber-200">Catatan pesanan: {{ $order->customer_note }}</p>
                        @endif

                        <div class="mt-4 flex items-center justify-between gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $order->payment_status === 'paid' ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300' }}">
                                {{ payment_status_label($order->payment_status) }}
                            </span>
                            @if ($nextLabel)
                                <button wire:click="advanceOrder({{ $order->id }})"
                                    class="flex-1 rounded-xl bg-amber-500 px-4 py-3 text-lg font-extrabold text-stone-950 hover:bg-amber-400">
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
