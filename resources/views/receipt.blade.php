<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $order->order_number }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body class="bg-cream font-sans text-espresso">
    <div class="no-print mx-auto mt-6 max-w-sm text-center">
        <button onclick="window.print()" class="rounded-lg bg-brand px-6 py-3 font-bold text-white">Cetak Struk</button>
        <a href="{{ route('cashier') }}" class="ml-2 rounded-lg bg-paper px-6 py-3 font-bold text-espresso shadow">Kembali ke Kasir</a>
    </div>

    <div id="print-area" class="mx-auto mt-6 w-full max-w-sm rounded-xl bg-paper p-6 font-mono text-sm shadow">
        <div class="text-center">
            <p class="font-display text-xl font-bold">{{ $settings->restaurant_name }}</p>
            <p class="text-xs text-cocoa">Struk Pembayaran</p>
        </div>
        <hr class="my-3 border-dashed border-line">
        <p>No. Order: {{ $order->order_number }}</p>
        <p>Meja: {{ $order->diningTable->name }}</p>
        <p>Tanggal: {{ $order->created_at->format('d/m/Y H:i') }}</p>
        @if ($order->paid_at)
            <p>Lunas: {{ $order->paid_at->format('d/m/Y H:i') }}</p>
        @endif
        <hr class="my-3 border-dashed border-line">
        @foreach ($order->items as $item)
            <div class="flex justify-between">
                <span>{{ $item->qty }}× {{ $item->name }}</span>
                <span>{{ rupiah($item->subtotal) }}</span>
            </div>
        @endforeach
        <hr class="my-3 border-dashed border-line">
        <div class="flex justify-between"><span>Subtotal</span><span>{{ rupiah($order->subtotal) }}</span></div>
        @if ($order->tax_amount > 0)
            <div class="flex justify-between"><span>Pajak</span><span>{{ rupiah($order->tax_amount) }}</span></div>
        @endif
        @if ($order->service_amount > 0)
            <div class="flex justify-between"><span>Service</span><span>{{ rupiah($order->service_amount) }}</span></div>
        @endif
        <div class="flex justify-between text-base font-bold"><span>TOTAL</span><span>{{ rupiah($order->total) }}</span></div>
        <div class="flex justify-between"><span>Bayar ({{ $order->payment_method?->isGateway() ? 'gateway ' . ($order->midtrans_payment_type ?? '') : ($order->payment_method?->label() ?? '—') }})</span><span>{{ rupiah($order->paid_amount ?? 0) }}</span></div>
        @if (($order->change_amount ?? 0) > 0)
            <div class="flex justify-between"><span>Kembalian</span><span>{{ rupiah($order->change_amount) }}</span></div>
        @endif
        <hr class="my-3 border-dashed border-line">
        <p class="text-center text-xs">Terima kasih sudah memesan di {{ $settings->restaurant_name }}.</p>
    </div>
</body>
</html>
