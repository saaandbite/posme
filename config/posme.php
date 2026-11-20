<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Masa berlaku QR meja (hari)
    |--------------------------------------------------------------------------
    |
    | URL QR dibuat dengan signature sementara yang kedaluwarsa setelah
    | jumlah hari ini. QR yang sudah dicetak harus dicetak ulang bila
    | sudah lewat tanggal kedaluwarsa, atau putar token meja dari admin
    | (token lama langsung tidak berlaku).
    |
    | Catatan: signature hanya dicek saat pertama membuka link. Customer
    | yang sudah membuka halaman dan sedang memesan tidak terganggu
    | bila masa berlakunya habis di tengah jalan.
    |
    */
    'qr_url_expiry_days' => (int) env('QR_URL_EXPIRY_DAYS', 30),

];
