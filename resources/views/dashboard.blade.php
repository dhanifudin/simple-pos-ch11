@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-lg font-display font-semibold mb-4">Dashboard</h1>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-card title="Penjualan Hari Ini" :href="route('transactions.index', ['from' => today()->format('Y-m-d'), 'to' => today()->format('Y-m-d')])">
            <p class="text-2xl font-mono font-semibold tabular-nums">Rp {{ number_format($todayTotal, 0, ',', '.') }}</p>
        </x-card>
        <x-card title="Transaksi Hari Ini" :href="route('transactions.index', ['from' => today()->format('Y-m-d'), 'to' => today()->format('Y-m-d')])">
            <p class="text-2xl font-mono font-semibold tabular-nums">{{ $todayCount }}</p>
        </x-card>
        <x-card title="Produk Stok Menipis" :href="auth()->user()->isAdmin() ? route('products.index', ['low_stock' => 1]) : null">
            <p class="text-2xl font-mono font-semibold tabular-nums">{{ $lowStock->count() }}</p>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <x-card title="Penjualan 7 Hari Terakhir">
            @php $peak = max(1, $weekSeries->max('total')); @endphp
            {{-- Bars are direct children of the h-32 row (a definite height) so their
                 percentage heights actually resolve; labels live in a separate row below.
                 A percentage height on a bar nested inside a flex-column wrapper here would
                 resolve against that wrapper's auto/content height instead, i.e. effectively 0. --}}
            <div class="flex items-end gap-3 h-32">
                @foreach ($weekSeries as $day)
                    <div class="flex-1 rounded-t-sm bg-brass/80" style="height: {{ max(4, (int) ($day['total'] / $peak * 100)) }}%"
                         title="Rp {{ number_format($day['total'], 0, ',', '.') }}"></div>
                @endforeach
            </div>
            <div class="flex gap-3 mt-2">
                @foreach ($weekSeries as $day)
                    <span class="flex-1 text-center text-[10px] font-mono uppercase text-ink-soft">{{ $day['label'] }}</span>
                @endforeach
            </div>
        </x-card>

        <x-card title="Produk dengan Stok Menipis (< 10)">
            @if ($lowStock->isEmpty())
                <x-empty-state>Tidak ada produk dengan stok menipis.</x-empty-state>
            @else
                <ul class="divide-y divide-ink/5">
                    @foreach ($lowStock as $product)
                        <li>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('products.edit', $product) }}"
                                   class="py-2 flex justify-between items-center text-sm hover:text-brass">
                                    <span>{{ $product->name }}</span>
                                    <x-badge tone="warn">{{ $product->stock }} unit</x-badge>
                                </a>
                            @else
                                <div class="py-2 flex justify-between items-center text-sm">
                                    <span>{{ $product->name }}</span>
                                    <x-badge tone="warn">{{ $product->stock }} unit</x-badge>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
@endsection
