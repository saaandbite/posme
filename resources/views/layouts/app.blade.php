<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Posme' }} — {{ \App\Models\Setting::current()->restaurant_name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        /* Tampilan struk saat print */
        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position: absolute; inset: 0; }
        }
    </style>
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased">
    @auth
        <header class="sticky top-0 z-40 bg-amber-600 text-white shadow">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
                <a href="/" class="text-lg font-bold tracking-tight">{{ \App\Models\Setting::current()->restaurant_name }}</a>
                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden sm:inline">{{ auth()->user()->name }} ({{ auth()->user()->role->label() }})</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-white/20 px-3 py-1.5 font-semibold hover:bg-white/30">Keluar</button>
                    </form>
                </div>
            </div>
            <nav class="bg-amber-700/60">
                <div class="mx-auto flex max-w-7xl gap-1 px-4 py-1.5 text-sm font-medium">
                    @if (auth()->user()->isAdmin())
                        <a href="/admin" class="rounded px-3 py-1 hover:bg-white/20">Panel Admin</a>
                    @endif
                    @if (auth()->user()->isCashier() || auth()->user()->isAdmin())
                        <a href="{{ route('cashier') }}" class="rounded px-3 py-1 hover:bg-white/20">Kasir</a>
                    @endif
                    @if (auth()->user()->isKitchen() || auth()->user()->isAdmin())
                        <a href="{{ route('kitchen') }}" class="rounded px-3 py-1 hover:bg-white/20">Dapur</a>
                    @endif
                </div>
            </nav>
        </header>
    @endauth

    <main class="mx-auto w-full max-w-7xl px-4 py-6">
        {{ $slot }}
    </main>

    @livewireScripts
    @stack('scripts')
</body>
</html>
