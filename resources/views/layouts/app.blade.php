<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $shopSetting->name)</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-surface min-h-screen text-ink font-sans antialiased">
    <div class="lg:flex">
        <x-nav />
        <main class="flex-1 min-w-0 px-4 py-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <div class="flex justify-end mb-4">
                    <div x-data="{ q: '{{ addslashes(request('q', '')) }}' }" class="relative w-full max-w-xs">
                        <form method="GET" action="{{ route('search') }}">
                            <label for="global-search" class="sr-only">Cari produk, invoice, atau pengguna</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-ink-soft/50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                                </svg>
                                <input id="global-search" x-ref="input" type="search" name="q" x-model="q" autocomplete="off"
                                       @keydown.window="if ($event.key === '/' && !['INPUT', 'TEXTAREA'].includes($event.target.tagName)) { $event.preventDefault(); $refs.input.focus(); }"
                                       placeholder="Cari... (tekan /)"
                                       class="w-full border border-ink/15 rounded-md pl-8 pr-8 py-2 text-sm focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                                <button type="button" x-show="q.length > 0" x-cloak @click="q = ''; $refs.input.focus()" aria-label="Bersihkan pencarian"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-ink-soft/50 hover:text-ink text-lg leading-none">&times;</button>
                            </div>
                        </form>
                    </div>
                </div>
                <x-flash />
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
