<x-filament-panels::page>
    <style>
        @media print {
            .fi-topbar, .fi-sidebar, .no-print { display: none !important; }
            .fi-main { margin: 0 !important; padding: 0 !important; }
        }
    </style>

    <div class="no-print mb-4 flex items-center justify-between">
        <p class="text-sm text-gray-500">Cetak halaman ini, gunting per meja, lalu tempel QR di masing-masing meja.</p>
        <x-filament::button onclick="window.print()">Print Semua QR</x-filament::button>
    </div>

    <div class="grid grid-cols-2 gap-6 md:grid-cols-3">
        @foreach ($this->getTables() as $table)
            <div class="rounded-2xl bg-white p-5 text-center shadow ring-1 ring-gray-200">
                <div class="flex justify-center">
                    {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($table->orderUrl()) !!}
                </div>
                <p class="mt-3 text-xl font-bold">{{ $table->name }}</p>
                <p class="text-sm text-gray-500">Scan untuk pesan</p>
                <p class="mt-1 text-xs text-gray-400">{{ \App\Models\Setting::current()->restaurant_name }}</p>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
