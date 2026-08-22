@extends('layouts.app')

@section('title', 'Laporan Penjualan')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-display font-semibold">Laporan Penjualan</h1>
        <a href="{{ route('reports.import.form') }}" class="text-sm text-ink-soft hover:text-brass hover:underline">Import Produk (CSV)</a>
    </div>

    <p class="text-sm text-ink-soft mb-4">
        Menampilkan data: <strong class="text-ink">{{ $from->translatedFormat('d M Y') }} &ndash; {{ $to->translatedFormat('d M Y') }}</strong>
    </p>

    <x-card class="mb-6" x-data="{ advanced: {{ (request()->filled('from') || request()->filled('to')) ? 'true' : 'false' }} }">
        <form method="GET" action="{{ route('reports.index') }}" class="space-y-3">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium mb-1">Bulan</label>
                    <select name="month" class="border border-ink/15 rounded-md px-3 py-2 text-sm focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                        <option value="">Semua bulan</option>
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $selectedMonth == $m ? 'selected' : '' }}>
                                {{ \Illuminate\Support\Carbon::create(null, $m, 1)->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Tahun</label>
                    <select name="year" class="border border-ink/15 rounded-md px-3 py-2 text-sm focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                        @foreach ($availableYears as $y)
                            <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit">Tampilkan</x-button>
                <div class="flex-1"></div>
                <button type="button" @click="advanced = !advanced" class="text-sm text-ink-soft hover:text-brass hover:underline">
                    <span x-show="!advanced">Rentang khusus</span>
                    <span x-show="advanced" x-cloak>Sembunyikan rentang khusus</span>
                </button>
                <x-button :href="route('reports.export', request()->query())" variant="ghost">Export CSV</x-button>
                <x-button :href="route('reports.export.pdf', request()->query())" variant="dark">Export PDF</x-button>
            </div>
            <div x-show="advanced" x-cloak class="flex flex-wrap items-end gap-3 pt-3 border-t border-ink/10">
                <div>
                    <label class="block text-xs font-medium mb-1">Dari</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                           class="border border-ink/15 rounded-md px-3 py-2 text-sm focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Sampai</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                           class="border border-ink/15 rounded-md px-3 py-2 text-sm focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                </div>
                <p class="text-xs text-ink-soft">Mengisi rentang khusus akan mengabaikan pilihan bulan/tahun di atas.</p>
            </div>
        </form>
    </x-card>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-card title="Total Penjualan">
            <p class="text-2xl font-mono font-semibold tabular-nums">Rp {{ number_format($totalPenjualan, 0, ',', '.') }}</p>
        </x-card>
        <x-card title="Jumlah Transaksi">
            <p class="text-2xl font-mono font-semibold tabular-nums">{{ $jumlahTransaksi }}</p>
        </x-card>
        <x-card title="Rata-rata per Transaksi">
            <p class="text-2xl font-mono font-semibold tabular-nums">Rp {{ number_format($avgTransaksi, 0, ',', '.') }}</p>
        </x-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <x-card :title="$granularity === 'day' ? 'Tren Penjualan Harian' : 'Tren Penjualan Bulanan'">
            @if ($periodBreakdown->isEmpty())
                <x-empty-state>Tidak ada data pada rentang ini.</x-empty-state>
            @else
                @php $peak = max(1, $periodBreakdown->max('total')); @endphp
                <div class="flex items-end gap-1 h-32 overflow-x-auto">
                    @foreach ($periodBreakdown as $point)
                        <div class="flex-1 min-w-[6px] rounded-t-sm bg-brass/80" style="height: {{ max(4, (int) ($point['total'] / $peak * 100)) }}%"
                             title="{{ $point['label'] }}: Rp {{ number_format($point['total'], 0, ',', '.') }} ({{ $point['count'] }} transaksi)"></div>
                    @endforeach
                </div>
                <div class="flex gap-1 mt-2 overflow-x-auto">
                    @foreach ($periodBreakdown as $point)
                        <span class="flex-1 min-w-[6px] text-center text-[9px] font-mono uppercase text-ink-soft truncate">{{ $point['label'] }}</span>
                    @endforeach
                </div>
            @endif
        </x-card>

        <x-card title="Produk Terlaris">
            @if ($topProducts->isEmpty())
                <x-empty-state>Tidak ada data pada rentang ini.</x-empty-state>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-ink-soft border-b border-ink/10 text-xs uppercase tracking-wide">
                            <th class="py-2 font-medium">Produk</th>
                            <th class="py-2 font-medium">Terjual</th>
                            <th class="py-2 font-medium text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topProducts as $product)
                            <tr class="border-b border-ink/5">
                                <td class="py-2">{{ $product->name }}</td>
                                <td class="py-2 font-mono">{{ $product->qty_terjual }}</td>
                                <td class="py-2 font-mono tabular-nums text-right">Rp {{ number_format($product->total_penjualan, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card title="Rekap per Kategori">
            @if ($categoryBreakdown->isEmpty())
                <x-empty-state>Tidak ada data pada rentang ini.</x-empty-state>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-ink-soft border-b border-ink/10 text-xs uppercase tracking-wide">
                            <th class="py-2 font-medium">Kategori</th>
                            <th class="py-2 font-medium">Terjual</th>
                            <th class="py-2 font-medium text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categoryBreakdown as $category)
                            <tr class="border-b border-ink/5">
                                <td class="py-2">{{ $category->name }}</td>
                                <td class="py-2 font-mono">{{ $category->qty_terjual }}</td>
                                <td class="py-2 font-mono tabular-nums text-right">Rp {{ number_format($category->total_penjualan, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card title="Rekap per Kasir">
            @if ($cashierBreakdown->isEmpty())
                <x-empty-state>Tidak ada data pada rentang ini.</x-empty-state>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-ink-soft border-b border-ink/10 text-xs uppercase tracking-wide">
                            <th class="py-2 font-medium">Kasir</th>
                            <th class="py-2 font-medium">Transaksi</th>
                            <th class="py-2 font-medium text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cashierBreakdown as $cashier)
                            <tr class="border-b border-ink/5">
                                <td class="py-2">{{ $cashier['name'] }}</td>
                                <td class="py-2 font-mono">{{ $cashier['count'] }}</td>
                                <td class="py-2 font-mono tabular-nums text-right">Rp {{ number_format($cashier['total'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>

    <x-card title="Daftar Transaksi">
        @if ($transactionsPage->isEmpty())
            <x-empty-state>Tidak ada transaksi pada rentang ini.</x-empty-state>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-ink-soft border-b border-ink/10 text-xs uppercase tracking-wide">
                        <th class="py-2 font-medium">Invoice</th>
                        <th class="py-2 font-medium">Tanggal</th>
                        <th class="py-2 font-medium">Kasir</th>
                        <th class="py-2 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactionsPage as $transaction)
                        <tr class="border-b border-ink/5">
                            <td class="py-2 font-mono text-xs">{{ $transaction->invoice_no }}</td>
                            <td class="py-2">{{ $transaction->created_at->format('d M Y H:i') }}</td>
                            <td class="py-2">{{ $transaction->user->name }}</td>
                            <td class="py-2 font-mono tabular-nums text-right">Rp {{ number_format($transaction->total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <div class="mt-4"><x-pagination :paginator="$transactionsPage" /></div>
        @endif
    </x-card>
@endsection
