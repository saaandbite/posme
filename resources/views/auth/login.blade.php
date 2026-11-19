<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — Posme</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-stone-100 px-4">
    <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-lg">
        <h1 class="text-center text-2xl font-bold text-amber-700">{{ \App\Models\Setting::current()->restaurant_name }}</h1>
        <p class="mt-1 text-center text-sm text-stone-500">Masuk untuk staf (admin, kasir, dapur)</p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm font-semibold">Email</label>
                <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm font-semibold">Password</label>
                <input id="password" name="password" type="password" required
                    class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
            </div>
            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" name="remember" class="h-4 w-4 accent-amber-600"> Ingat saya
            </label>
            <button type="submit"
                class="w-full rounded-lg bg-amber-600 py-3 text-base font-bold text-white hover:bg-amber-700">
                Masuk
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-stone-400">
            Akun demo — admin@posme.test / kasir@posme.test / dapur@posme.test<br>
            Password ketiganya: <span class="font-mono">password</span>
        </p>
    </div>
</body>
</html>
