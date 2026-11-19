<?php

use Illuminate\Support\Number;

if (! function_exists('rupiah')) {
    /**
     * Format angka menjadi Rupiah, mis. 15000 -> "Rp 15.000".
     * Satu-satunya tempat format Rupiah didefinisikan — semua tampilan
     * memakai helper ini (atau Number::currency langsung).
     * Precision 0 karena semua harga di POS ini bilangan bulat.
     */
    function rupiah(int|float|null $amount): string
    {
        return Number::currency((int) $amount, in: 'IDR', locale: 'id', precision: 0);
    }
}
