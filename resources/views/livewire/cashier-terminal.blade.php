<div wire:poll.5s.visible>
    <h1 class="mb-4 text-2xl font-bold">Kasir</h1>

    {{-- Tab --}}
    <div class="mb-5 flex gap-2">
        @foreach (['monitor' => 'Monitor Order', 'terminal' => 'Pembayaran', 'history' => 'Riwayat Hari Ini'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')"
                class="rounded-full px-4 py-2 text-sm font-bold {{ $tab === $key ? 'bg-amber-600 text-white' : 'bg-white text-stone-700 shadow-sm' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ================= MONITOR (kasir merangkap waiter) ================= --}}
    @if ($tab === 'monitor')
        @if ($this->monitorOrders->isEmpty())
            <div class="rounded-2xl bg-white p-10 text-center shadow-sm">
                <p class="text-lg font-semibold text-stone-600">Belum ada order aktif</p>
                <p class="text-sm text-stone-400">Order baru dari customer akan muncul di sini otomatis.</p>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->monitorOrders as $order)
                    <article class="rounded-2xl bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xl font-extrabold">{{ $order->diningTable->name }}</p>
                                <p class="text-sm text-stone-500">{{ $order->order_number }} • {{ $order->created_at->format('H:i') }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold
                                {{ $order->status === 'pending' ? 'bg-red-100 text-red-700' : ($order->status === 'preparing' ? 'bg-amber-100 text-amber-800' : ($order->status === 'ready' ? 'bg-green-100 text-green-700' : 'bg-stone-200 text-stone-600')) }}">
                                {{ order_status_label($order->status) }}
                            </span>
                        </div>
                        <ul class="mt-3 space-y-1 border-t border-stone-100 pt-3 text-sm">
                            @foreach ($order->items as $item)
                                <li class="flex justify-between gap-2">
                                    <span>{{ $item->qty }}× {{ $item->name }}</span>
                                    <span class="text-stone-500">{{ rupiah($item->subtotal) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-3 flex items-center justify-between text-sm">
                            <span class="font-bold">Total: {{ rupiah($order->total) }}</span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $order->payment_status === 'paid' ? 'bg-green-100 text-green-700' : ($order->payment_status === 'waiting' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-700') }}">
                                {{ payment_status_label($order->payment_status) }}
                                @if ($order->payment_status === 'paid')
                                    ({{ $order->payment_method === 'midtrans' ? 'gateway' : $order->payment_method }})
                                @endif
                            </span>
                        </div>
                        <div class="mt-4 flex gap-2">
                            @if ($order->status === 'ready')
                                <button wire:click="markServed({{ $order->id }})"
                                    class="flex-1 rounded-xl bg-green-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-green-700">
                                    Antar / Disajikan
                                </button>
                            @endif
                            @if ($order->payment_status !== 'paid')
                                <button wire:click="selectOrder({{ $order->id }})"
                                    class="flex-1 rounded-xl bg-amber-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-amber-700">
                                    Terima Bayar
                                </button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ================= TERMINAL PEMBAYARAN ================= --}}
    @if ($tab === 'terminal')
        @if (! $this->selectedOrder)
            <div class="rounded-2xl bg-white p-10 text-center shadow-sm">
                <p class="text-lg font-semibold text-stone-600">Pilih order dulu dari tab Monitor Order</p>
                <p class="text-sm text-stone-400">Atau tunggu order masuk — semua order aktif tampil di monitor.</p>
                <button wire:click="$set('tab', 'monitor')" class="mt-4 rounded-xl bg-amber-600 px-5 py-2.5 font-bold text-white">Buka Monitor</button>
            </div>
        @else
            @php $order = $this->selectedOrder; @endphp
            <div class="grid gap-5 lg:grid-cols-2">
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold">{{ $order->diningTable->name }} — {{ $order->order_number }}</h2>
                    <p class="text-sm text-stone-500">{{ $order->created_at->format('d M Y, H:i') }} • Status: {{ order_status_label($order->status) }}</p>
                    <ul class="mt-4 space-y-2 border-t border-stone-100 pt-3">
                        @foreach ($order->items as $item)
                            <li class="flex justify-between gap-2">
                                <span>{{ $item->qty }}× {{ $item->name }}
                                    @if ($item->note)<span class="block text-xs text-stone-500">{{ $item->note }}</span>@endif
                                </span>
                                <span>{{ rupiah($item->subtotal) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-3 space-y-1 border-t border-dashed border-stone-300 pt-3 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><span>{{ rupiah($order->subtotal) }}</span></div>
                        @if ($order->tax_amount > 0)<div class="flex justify-between"><span>Pajak</span><span>{{ rupiah($order->tax_amount) }}</span></div>@endif
                        @if ($order->service_amount > 0)<div class="flex justify-between"><span>Service</span><span>{{ rupiah($order->service_amount) }}</span></div>@endif
                        <div class="flex justify-between text-xl font-extrabold"><span>Total</span><span>{{ rupiah($order->total) }}</span></div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    @if ($order->payment_status === 'paid')
                        <div class="rounded-xl bg-green-50 px-4 py-4 text-center">
                            <p class="text-lg font-bold text-green-800">✓ Sudah Lunas</p>
                            <p class="text-sm text-green-700">
                                {{ $order->payment_method === 'midtrans' ? 'Via gateway (' . ($order->midtrans_payment_type ?? 'online') . ')' : 'Via ' . $order->payment_method }}
                                • {{ $order->paid_at?->format('H:i') }}
                            </p>
                            @if ($order->change_amount > 0)
                                <p class="mt-1 text-sm text-green-700">Kembalian: {{ rupiah($order->change_amount) }}</p>
                            @endif
                        </div>
                        <a href="{{ route('cashier.receipt', $order) }}" target="_blank"
                            class="mt-4 block rounded-xl bg-stone-800 py-3 text-center font-bold text-white hover:bg-stone-700">
                            Cetak / Lihat Struk
                        </a>
                    @elseif ($order->status === 'cancelled')
                        <div class="rounded-xl bg-red-50 px-4 py-4 text-center font-bold text-red-700">Order dibatalkan</div>
                    @else
                        <h3 class="text-lg font-bold">Pembayaran</h3>
                        <div class="mt-3 flex gap-2">
                            @if ($settings->accept_cash)
                                <label class="flex-1 cursor-pointer rounded-xl border-2 px-4 py-3 text-center font-bold {{ $paymentMethod === 'cash' ? 'border-amber-600 bg-amber-50 text-amber-800' : 'border-stone-200' }}">
                                    <input type="radio" wire:model.live="paymentMethod" value="cash" class="hidden"> Tunai
                                </label>
                            @endif
                            @if ($settings->accept_qris)
                                <label class="flex-1 cursor-pointer rounded-xl border-2 px-4 py-3 text-center font-bold {{ $paymentMethod === 'qris' ? 'border-amber-600 bg-amber-50 text-amber-800' : 'border-stone-200' }}">
                                    <input type="radio" wire:model.live="paymentMethod" value="qris" class="hidden"> QRIS
                                </label>
                            @endif
                        </div>

                        @if ($paymentMethod === 'cash')
                            <label class="mt-4 block text-sm font-semibold">Uang diterima</label>
                            <input type="number" wire:model.live="cashReceived" min="0" step="500" placeholder="mis. 50000"
                                class="mt-1 w-full rounded-xl border-2 border-stone-300 px-4 py-3 text-2xl font-bold focus:border-amber-500 focus:outline-none">
                            @if ($this->change !== null)
                                <p class="mt-3 rounded-xl px-4 py-3 text-center text-xl font-extrabold {{ $this->change >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                    Kembalian: {{ rupiah(max(0, $this->change)) }}
                                    @if ($this->change < 0)
                                        <span class="block text-sm font-normal">Uang kurang {{ rupiah(abs($this->change)) }}</span>
                                    @endif
                                </p>
                            @endif
                        @else
                            <p class="mt-4 rounded-xl bg-stone-100 px-4 py-3 text-sm text-stone-600">
                                Minta customer scan QRIS kasir sebesar <b>{{ rupiah($order->total) }}</b>, lalu tekan tombol di bawah setelah pembayaran masuk.
                            </p>
                        @endif

                        @error('payment')
                            <div class="mt-3 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $message }}</div>
                        @enderror
                        @error('cashReceived')
                            <div class="mt-3 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $message }}</div>
                        @enderror

                        <button wire:click="confirmPayment" wire:loading.attr="disabled"
                            class="mt-4 w-full rounded-xl bg-amber-600 py-3.5 text-lg font-bold text-white hover:bg-amber-700 disabled:opacity-50">
                            Tandai Lunas • {{ rupiah($order->total) }}
                        </button>

                        @if ($this->lastPaidOrderId)
                            <a href="{{ route('cashier.receipt', $this->lastPaidOrderId) }}" target="_blank"
                                class="mt-3 block rounded-xl bg-stone-800 py-3 text-center font-bold text-white hover:bg-stone-700">
                                Cetak / Lihat Struk
                            </a>
                        @endif

                        <div class="mt-6 border-t border-stone-200 pt-4">
                            @if (! $showCancelForm)
                                <button wire:click="$set('showCancelForm', true)" class="text-sm font-semibold text-red-600 hover:underline">
                                    Batalkan order ini
                                </button>
                            @else
                                <label class="block text-sm font-semibold">Alasan pembatalan (wajib)</label>
                                <input type="text" wire:model="cancelReason" maxlength="200" placeholder="mis. stok habis / salah input"
                                    class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-red-400 focus:outline-none">
                                @error('cancelReason')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <div class="mt-2 flex gap-2">
                                    <button wire:click="cancelOrder" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white">Ya, batalkan</button>
                                    <button wire:click="$set('showCancelForm', false)" class="rounded-lg bg-stone-200 px-4 py-2 text-sm font-bold">Urungkan</button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif
    @endif

    {{-- ================= RIWAYAT ================= --}}
    @if ($tab === 'history')
        <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-stone-100 text-xs uppercase text-stone-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Meja</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Bayar</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->history as $order)
                        <tr class="border-t border-stone-100">
                            <td class="px-4 py-3">{{ $order->updated_at->format('H:i') }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $order->order_number }}</td>
                            <td class="px-4 py-3">{{ $order->diningTable->name }}</td>
                            <td class="px-4 py-3 font-bold">{{ rupiah($order->total) }}</td>
                            <td class="px-4 py-3">{{ $order->payment_status === 'paid' ? ($order->payment_method === 'midtrans' ? 'Gateway' : ucfirst($order->payment_method)) : '—' }}</td>
                            <td class="px-4 py-3">{{ $order->status === 'cancelled' ? 'Dibatalkan' : payment_status_label($order->payment_status) }}</td>
                            <td class="px-4 py-3">
                                @if ($order->payment_status === 'paid')
                                    <a href="{{ route('cashier.receipt', $order) }}" target="_blank" class="font-semibold text-amber-700 hover:underline">Struk</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-stone-400">Belum ada transaksi hari ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
