<div wire:poll.5s.visible>
    <h1 class="mb-4 font-display text-3xl font-bold">Kasir</h1>

    {{-- Tab --}}
    <div class="mb-5 flex gap-2">
        @foreach (['monitor' => 'Monitor Order', 'terminal' => 'Pembayaran', 'history' => 'Riwayat Hari Ini'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')"
                class="rounded-full px-4 py-2 text-sm font-bold {{ $tab === $key ? 'bg-brand text-white' : 'bg-paper text-espresso shadow-sm' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ================= MONITOR (kasir merangkap waiter) ================= --}}
    @if ($tab === 'monitor')
        @if ($this->monitorOrders->isEmpty())
            <div class="rounded-xl bg-paper p-10 text-center shadow-sm">
                <p class="text-lg font-semibold text-espresso">Belum ada order aktif</p>
                <p class="text-sm text-cocoa">Order baru dari customer akan muncul di sini otomatis.</p>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->monitorOrders as $order)
                    <article class="rounded-xl bg-paper p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-display text-xl font-extrabold">{{ $order->diningTable->name }}</p>
                                <p class="text-sm text-cocoa">{{ $order->order_number }} • {{ $order->created_at->format('H:i') }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold
                                {{ match ($order->status) { \App\Enums\OrderStatus::Pending => 'bg-danger-soft text-danger', \App\Enums\OrderStatus::Preparing => 'bg-butter-soft text-butter-dark', \App\Enums\OrderStatus::Ready => 'bg-leaf-soft text-leaf-dark', default => 'bg-cream-dark text-cocoa' } }}">
                                {{ $order->status->label() }}
                            </span>
                        </div>
                        <ul class="mt-3 space-y-1 border-t border-line pt-3 text-sm">
                            @foreach ($order->items as $item)
                                <li class="flex justify-between gap-2">
                                    <span>{{ $item->qty }}× {{ $item->name }}</span>
                                    <span class="text-cocoa">{{ rupiah($item->subtotal) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-3 flex items-center justify-between text-sm">
                            <span class="font-bold">Total: {{ rupiah($order->total) }}</span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ match ($order->payment_status) { \App\Enums\PaymentStatus::Paid => 'bg-leaf-soft text-leaf-dark', \App\Enums\PaymentStatus::Waiting => 'bg-butter-soft text-butter-dark', default => 'bg-danger-soft text-danger' } }}">
                                {{ $order->payment_status->label() }}
                                @if ($order->payment_status === \App\Enums\PaymentStatus::Paid)
                                    ({{ $order->payment_method?->isGateway() ? 'gateway' : ($order->payment_method?->label() ?? '—') }})
                                @endif
                            </span>
                        </div>
                        <div class="mt-4 flex gap-2">
                            @if ($order->status === \App\Enums\OrderStatus::Ready)
                                <button wire:click="markServed({{ $order->id }})"
                                    class="flex-1 rounded-lg bg-leaf px-3 py-2.5 text-sm font-bold text-white hover:bg-leaf-dark">
                                    Antar / Disajikan
                                </button>
                            @endif
                            @if ($order->payment_status !== \App\Enums\PaymentStatus::Paid)
                                <button wire:click="selectOrder({{ $order->id }})"
                                    class="flex-1 rounded-lg bg-brand px-3 py-2.5 text-sm font-bold text-white hover:bg-brand-dark">
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
            <div class="rounded-xl bg-paper p-10 text-center shadow-sm">
                <p class="text-lg font-semibold text-espresso">Pilih order dulu dari tab Monitor Order</p>
                <p class="text-sm text-cocoa">Atau tunggu order masuk — semua order aktif tampil di monitor.</p>
                <button wire:click="$set('tab', 'monitor')" class="mt-4 rounded-lg bg-brand px-5 py-3 font-bold text-white">Buka Monitor</button>
            </div>
        @else
            @php $order = $this->selectedOrder; @endphp
            <div class="grid gap-5 lg:grid-cols-2">
                <div class="rounded-xl bg-paper p-5 shadow-sm">
                    <h2 class="font-display text-lg font-bold">{{ $order->diningTable->name }} — {{ $order->order_number }}</h2>
                    <p class="text-sm text-cocoa">{{ $order->created_at->format('d M Y, H:i') }} • Status: {{ $order->status->label() }}</p>
                    <ul class="mt-4 space-y-2 border-t border-line pt-3">
                        @foreach ($order->items as $item)
                            <li class="flex justify-between gap-2">
                                <span>{{ $item->qty }}× {{ $item->name }}
                                    @if ($item->note)<span class="block text-xs text-cocoa">{{ $item->note }}</span>@endif
                                </span>
                                <span>{{ rupiah($item->subtotal) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-3 space-y-1 border-t border-dashed border-line pt-3 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><span>{{ rupiah($order->subtotal) }}</span></div>
                        @if ($order->tax_amount > 0)<div class="flex justify-between"><span>Pajak</span><span>{{ rupiah($order->tax_amount) }}</span></div>@endif
                        @if ($order->service_amount > 0)<div class="flex justify-between"><span>Service</span><span>{{ rupiah($order->service_amount) }}</span></div>@endif
                        <div class="flex justify-between text-xl font-extrabold"><span>Total</span><span>{{ rupiah($order->total) }}</span></div>
                    </div>
                </div>

                <div class="rounded-xl bg-paper p-5 shadow-sm">
                    @if ($order->payment_status === \App\Enums\PaymentStatus::Paid)
                        <div class="rounded-lg bg-leaf-soft px-4 py-4 text-center">
                            <p class="text-lg font-bold text-leaf-dark">✓ Sudah Lunas</p>
                            <p class="text-sm text-leaf-dark">
                                {{ $order->payment_method?->isGateway() ? 'Via gateway (' . ($order->midtrans_payment_type ?? 'online') . ')' : 'Via ' . ($order->payment_method?->label() ?? '—') }}
                                • {{ $order->paid_at?->format('H:i') }}
                            </p>
                            @if ($order->change_amount > 0)
                                <p class="mt-1 text-sm text-leaf-dark">Kembalian: {{ rupiah($order->change_amount) }}</p>
                            @endif
                        </div>
                        <a href="{{ route('cashier.receipt', $order) }}" target="_blank"
                            class="mt-4 block rounded-lg bg-espresso py-3 text-center font-bold text-white hover:bg-espresso-light">
                            Cetak / Lihat Struk
                        </a>
                    @elseif ($order->status === \App\Enums\OrderStatus::Cancelled)
                        <div class="rounded-lg bg-danger-soft px-4 py-4 text-center font-bold text-danger">Order dibatalkan</div>
                    @else
                        <h3 class="font-display text-lg font-bold">Pembayaran</h3>
                        <div class="mt-3 flex gap-2">
                            @if ($settings->accept_cash)
                                <label class="flex-1 cursor-pointer rounded-xl border-2 px-4 py-3 text-center font-bold {{ $paymentMethod === 'cash' ? 'border-brand bg-brand-soft text-brand-dark' : 'border-line' }}">
                                    <input type="radio" wire:model.live="paymentMethod" value="cash" class="hidden"> Tunai
                                </label>
                            @endif
                            @if ($settings->accept_qris)
                                <label class="flex-1 cursor-pointer rounded-xl border-2 px-4 py-3 text-center font-bold {{ $paymentMethod === 'qris' ? 'border-brand bg-brand-soft text-brand-dark' : 'border-line' }}">
                                    <input type="radio" wire:model.live="paymentMethod" value="qris" class="hidden"> QRIS
                                </label>
                            @endif
                        </div>

                        @if ($paymentMethod === 'cash')
                            <label class="mt-4 block text-sm font-semibold">Uang diterima</label>
                            <input type="number" wire:model.live="cashReceived" min="0" step="500" placeholder="mis. 50000"
                                class="mt-1 w-full rounded-lg border-2 border-line px-4 py-3 text-2xl font-bold focus:border-brand focus:outline-none">
                            @if ($this->change !== null)
                                <p class="mt-3 rounded-lg px-4 py-3 text-center text-xl font-extrabold {{ $this->change >= 0 ? 'bg-leaf-soft text-leaf-dark' : 'bg-danger-soft text-danger' }}">
                                    Kembalian: {{ rupiah(max(0, $this->change)) }}
                                    @if ($this->change < 0)
                                        <span class="block text-sm font-normal">Uang kurang {{ rupiah(abs($this->change)) }}</span>
                                    @endif
                                </p>
                            @endif
                        @else
                            <p class="mt-4 rounded-lg bg-cream-dark px-4 py-3 text-sm text-cocoa">
                                Minta customer scan QRIS kasir sebesar <b>{{ rupiah($order->total) }}</b>, lalu tekan tombol di bawah setelah pembayaran masuk.
                            </p>
                        @endif

                        @error('payment')
                            <div class="mt-3 rounded-lg bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{{ $message }}</div>
                        @enderror
                        @error('cashReceived')
                            <div class="mt-3 rounded-lg bg-danger-soft px-4 py-3 text-sm font-medium text-danger">{{ $message }}</div>
                        @enderror

                        <button wire:click="confirmPayment" wire:loading.attr="disabled"
                            class="mt-4 w-full rounded-lg bg-brand py-3.5 text-lg font-bold text-white hover:bg-brand-dark disabled:opacity-50">
                            Tandai Lunas • {{ rupiah($order->total) }}
                        </button>

                        @if ($this->lastPaidOrderId)
                            <a href="{{ route('cashier.receipt', $this->lastPaidOrderId) }}" target="_blank"
                                class="mt-3 block rounded-lg bg-espresso py-3 text-center font-bold text-white hover:bg-espresso-light">
                                Cetak / Lihat Struk
                            </a>
                        @endif

                        <div class="mt-6 border-t border-line pt-4">
                            @if (! $showCancelForm)
                                <button wire:click="$set('showCancelForm', true)" class="text-sm font-semibold text-danger hover:underline">
                                    Batalkan order ini
                                </button>
                            @else
                                <label class="block text-sm font-semibold">Alasan pembatalan (wajib)</label>
                                <input type="text" wire:model="cancelReason" maxlength="200" placeholder="mis. stok habis / salah input"
                                    class="mt-1 w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-danger focus:outline-none">
                                @error('cancelReason')
                                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                                @enderror
                                <div class="mt-2 flex gap-2">
                                    <button wire:click="cancelOrder" class="rounded-lg bg-danger px-4 py-2 text-sm font-bold text-white">Ya, batalkan</button>
                                    <button wire:click="$set('showCancelForm', false)" class="rounded-lg bg-cream-dark px-4 py-2 text-sm font-bold">Urungkan</button>
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
        <div class="overflow-x-auto rounded-xl bg-paper shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-cream-dark text-xs uppercase text-cocoa">
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
                        <tr class="border-t border-line">
                            <td class="px-4 py-3">{{ $order->updated_at->format('H:i') }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $order->order_number }}</td>
                            <td class="px-4 py-3">{{ $order->diningTable->name }}</td>
                            <td class="px-4 py-3 font-bold">{{ rupiah($order->total) }}</td>
                            <td class="px-4 py-3">{{ $order->payment_status === \App\Enums\PaymentStatus::Paid ? ($order->payment_method?->isGateway() ? 'Gateway' : ($order->payment_method?->label() ?? '—')) : '—' }}</td>
                            <td class="px-4 py-3">{{ $order->status === \App\Enums\OrderStatus::Cancelled ? 'Dibatalkan' : $order->payment_status->label() }}</td>
                            <td class="px-4 py-3">
                                @if ($order->payment_status === \App\Enums\PaymentStatus::Paid)
                                    <a href="{{ route('cashier.receipt', $order) }}" target="_blank" class="font-semibold text-brand hover:underline">Struk</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-cocoa">Belum ada transaksi hari ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
