@php
    $user = auth()->user();
    $linkGroups = [
        'Utama' => [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'admin' => false],
            ['route' => 'pos.create', 'label' => 'Kasir', 'admin' => false],
            ['route' => 'transactions.index', 'label' => 'Transaksi', 'admin' => false],
        ],
        'Master Data' => [
            ['route' => 'products.index', 'label' => 'Produk', 'admin' => true],
            ['route' => 'categories.index', 'label' => 'Kategori', 'admin' => true],
        ],
        'Admin' => [
            ['route' => 'reports.index', 'label' => 'Laporan', 'admin' => true],
            ['route' => 'users.index', 'label' => 'Pengguna', 'admin' => true],
            ['route' => 'settings.edit', 'label' => 'Pengaturan', 'admin' => true],
        ],
    ];
@endphp
<div x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    {{-- Mobile top bar --}}
    <header class="lg:hidden sticky top-0 z-30 flex items-center justify-between h-14 px-4 bg-rail text-surface">
        <button @click="drawerOpen = true" aria-label="Buka menu" class="p-2 -ml-2 hover:text-brass">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <span class="font-display font-semibold tracking-tight">{{ $shopSetting->name }}</span>
        <span class="w-10"></span>
    </header>

    {{-- Overlay --}}
    <div x-show="drawerOpen" x-cloak x-transition.opacity @click="drawerOpen = false"
         class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

    {{-- Drawer / sidebar --}}
    <aside
        :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 w-64 bg-rail text-surface flex flex-col
               transition-transform duration-200 ease-out
               lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:translate-x-0"
    >
        <div class="flex items-center justify-between h-14 px-4 border-b border-white/10">
            <span class="font-display font-semibold tracking-tight">{{ $shopSetting->name }}</span>
            <button @click="drawerOpen = false" aria-label="Tutup menu" class="p-2 -mr-2 hover:text-brass lg:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-1">
            @foreach ($linkGroups as $groupLabel => $groupLinks)
                @php $visibleLinks = collect($groupLinks)->filter(fn ($l) => !$l['admin'] || $user?->isAdmin()); @endphp
                @if ($visibleLinks->isNotEmpty())
                    <div class="px-3 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-wide text-surface/40">
                        {{ $groupLabel }}
                    </div>
                    @foreach ($visibleLinks as $link)
                        @php $active = request()->routeIs($link['route'] . '*'); @endphp
                        <a href="{{ route($link['route']) }}"
                           @click="drawerOpen = false"
                           class="block rounded px-3 py-2 text-sm border-l-2 transition-colors
                                  {{ $active
                                        ? 'border-brass bg-white/5 text-brass font-medium'
                                        : 'border-transparent text-surface/80 hover:border-white/20 hover:bg-white/5 hover:text-surface' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-4 text-sm space-y-3">
            <div>
                {{ $user?->name }}
                <span class="ml-1 inline-flex items-center rounded-full bg-white/10 px-2 py-0.5 text-xs font-mono text-surface/70">{{ $user?->role }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-signal-red/90 hover:text-signal-red">Keluar</button>
            </form>
        </div>
    </aside>
</div>
