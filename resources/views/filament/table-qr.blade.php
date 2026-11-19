<div class="text-center">
    <div class="mx-auto flex justify-center rounded-xl bg-white p-4 ring-1 ring-gray-200">
        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(240)->generate($table->orderUrl()) !!}
    </div>
    <p class="mt-4 text-sm font-semibold">{{ $table->name }}</p>
    <p class="mt-1 break-all text-xs text-gray-500">{{ $table->orderUrl() }}</p>
    <p class="mt-3 text-xs text-gray-400">
        Tempel QR ini di meja. Customer scan dengan kamera HP, lalu memesan tanpa perlu login atau install aplikasi.
    </p>
</div>
