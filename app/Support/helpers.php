<?php

if (! function_exists('rupiah')) {
    /**
     * Format angka menjadi Rupiah, mis. 15000 -> "Rp 15.000".
     */
    function rupiah(int|float|null $amount): string
    {
        return 'Rp '.number_format((int) $amount, 0, ',', '.');
    }
}

if (! function_exists('order_status_label')) {
    /**
     * Label Bahasa Indonesia untuk status order.
     */
    function order_status_label(string $status): string
    {
        return match ($status) {
            'pending' => 'Dipesan',
            'preparing' => 'Disiapkan',
            'ready' => 'Siap',
            'served' => 'Disajikan',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($status),
        };
    }
}

if (! function_exists('payment_status_label')) {
    /**
     * Label Bahasa Indonesia untuk status pembayaran.
     */
    function payment_status_label(string $status): string
    {
        return match ($status) {
            'unpaid' => 'Belum bayar',
            'waiting' => 'Menunggu pembayaran',
            'paid' => 'Lunas',
            default => ucfirst($status),
        };
    }
}
