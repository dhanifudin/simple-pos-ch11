<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — {{ $shopSetting->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-rail min-h-screen flex items-center justify-center px-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <p class="mb-2 text-center font-mono text-xs uppercase tracking-widest text-surface/50">Kasir &middot; Admin</p>
        <h1 class="mb-6 text-center font-display text-2xl font-semibold text-surface">{{ $shopSetting->name }}</h1>
        <div x-data class="bg-surface-raised rounded-lg border border-ink/10 p-6 shadow-lg">
            <div class="perforation mb-6"></div>
            <x-flash />
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium mb-1 text-ink">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm bg-surface-raised text-ink focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 text-ink">Kata Sandi</label>
                    <input type="password" name="password" required
                           class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm bg-surface-raised text-ink focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                </div>
                <x-button type="submit" class="w-full">Masuk</x-button>
            </form>
            <div class="perforation my-6"></div>
            <p class="text-xs text-ink-soft text-center font-mono">
                Demo: admin@pos.test / kasir@pos.test — password: <code>password</code>
            </p>
        </div>
    </div>
</body>
</html>
