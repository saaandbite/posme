<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — Posme</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-cream px-4 font-sans text-espresso">
    <div class="w-full max-w-sm rounded-xl bg-paper p-8 shadow-lg">
        <h1 class="text-center font-display text-3xl font-bold">{{ \App\Models\Setting::current()->restaurant_name }}</h1>
        <p class="mt-1 text-center text-sm text-cocoa">Masuk untuk staf (admin, kasir, dapur)</p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg bg-danger-soft px-4 py-3 text-sm text-danger">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm font-semibold">Email</label>
                <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                    class="w-full rounded-lg border border-line px-3 py-2.5 text-base focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-soft">
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm font-semibold">Password</label>
                <input id="password" name="password" type="password" required
                    class="w-full rounded-lg border border-line px-3 py-2.5 text-base focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand-soft">
            </div>
            <label class="flex items-center gap-2 text-sm text-cocoa">
                <input type="checkbox" name="remember" class="h-4 w-4 accent-brand"> Ingat saya
            </label>
            <button type="submit"
                class="w-full rounded-lg bg-brand py-3 text-base font-bold text-white hover:bg-brand-dark">
                Masuk
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-cocoa">
            Akun demo — admin@posme.test / kasir@posme.test / dapur@posme.test<br>
            Password ketiganya: <span class="font-mono">password</span>
        </p>
    </div>
</body>
</html>
