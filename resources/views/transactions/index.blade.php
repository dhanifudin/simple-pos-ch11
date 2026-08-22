@extends('layouts.app')

@section('title', 'Transaksi')

@section('content')
    <h1 class="text-lg font-display font-semibold mb-4">Riwayat Transaksi</h1>

    <x-card class="mb-6" x-data="{ advanced: {{ (request()->filled('from') || request()->filled('to')) ? 'true' : 'false' }} }">
        <form method="GET" action="{{ route('transactions.index') }}" class="space-y-3">
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
                <div>
                    <label class="block text-xs font-medium mb-1">Status</label>
                    <select name="status" class="border border-ink/15 rounded-md px-3 py-2 text-sm focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                        <option value="">Semua</option>
                        <option value="selesai" {{ $status === 'selesai' ? 'selected' : '' }}>Selesai</option>
                        <option value="dibatalkan" {{ $status === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Cari</label>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Invoice atau nama kasir..."
                           class="border border-ink/15 rounded-md px-3 py-2 text-sm w-56 focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                </div>
                <x-button type="submit">Tampilkan</x-button>
                <button type="button" @click="advanced = !advanced" class="text-sm text-ink-soft hover:text-brass hover:underline pb-2">
                    <span x-show="!advanced">Rentang khusus</span>
                    <span x-show="advanced" x-cloak>Sembunyikan rentang khusus</span>
                </button>
                @if ($from || $to || $status || $q !== '')
                    <a href="{{ route('transactions.index') }}" class="text-sm text-ink-soft hover:text-brass hover:underline pb-2">Lihat semua</a>
                @endif
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

    <x-card x-data>
        @if ($transactions->isEmpty())
            <x-empty-state>Belum ada transaksi.</x-empty-state>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-ink-soft border-b border-ink/10 text-xs uppercase tracking-wide">
                        <th class="py-2 font-medium"><x-sortable-th column="invoice_no" label="Invoice" :sort="$sort" :direction="$direction" /></th>
                        <th class="py-2 font-medium"><x-sortable-th column="created_at" label="Tanggal" :sort="$sort" :direction="$direction" /></th>
                        <th class="py-2 font-medium">Kasir</th>
                        <th class="py-2 font-medium"><x-sortable-th column="total" label="Total" :sort="$sort" :direction="$direction" /></th>
                        <th class="py-2 font-medium">Status</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        <tr class="border-b border-ink/5 {{ $transaction->isVoided() ? 'opacity-50' : '' }}">
                            <td class="py-3 font-mono text-xs">{{ $transaction->invoice_no }}</td>
                            <td class="py-3">{{ $transaction->created_at->format('d M Y H:i') }}</td>
                            <td class="py-3">{{ $transaction->user->name }}</td>
                            <td class="py-3 font-mono tabular-nums">Rp {{ number_format($transaction->total, 0, ',', '.') }}</td>
                            <td class="py-3">
                                @if ($transaction->isVoided())
                                    <x-badge tone="danger">Dibatalkan</x-badge>
                                @else
                                    <x-badge tone="success">Selesai</x-badge>
                                @endif
                            </td>
                            <td class="py-3 text-right">
                                <div class="hidden lg:flex items-center justify-end gap-3">
                                    <a href="{{ route('transactions.show', $transaction) }}" class="text-sm text-ink-soft hover:text-brass hover:underline">Detail</a>
                                    @if (auth()->user()->isAdmin() && ! $transaction->isVoided())
                                        <button type="button" @click="$store.modal.open('void-{{ $transaction->id }}')"
                                                class="text-sm text-signal-red hover:underline">Batalkan</button>
                                    @endif
                                </div>
                                <div class="lg:hidden flex justify-end">
                                    <x-row-menu>
                                        <a href="{{ route('transactions.show', $transaction) }}" class="block w-full text-left px-3 py-2 hover:bg-ink/5">Detail</a>
                                        @if (auth()->user()->isAdmin() && ! $transaction->isVoided())
                                            <button type="button" @click="$store.modal.open('void-{{ $transaction->id }}')"
                                                    class="block w-full text-left px-3 py-2 text-signal-red hover:bg-ink/5">Batalkan</button>
                                        @endif
                                    </x-row-menu>
                                </div>
                            </td>
                        </tr>

                        @if (auth()->user()->isAdmin() && ! $transaction->isVoided())
                            <x-modal id="void-{{ $transaction->id }}" title="Batalkan Transaksi">
                                <p class="text-sm text-ink-soft mb-3">
                                    Invoice <strong class="font-mono">{{ $transaction->invoice_no }}</strong> akan dibatalkan —
                                    stok dikembalikan dan transaksi dikecualikan dari laporan. Transaksi tetap tampil di
                                    riwayat dengan status "Dibatalkan".
                                </p>
                                <form method="POST" action="{{ route('transactions.void', $transaction) }}" class="space-y-3">
                                    @csrf @method('PATCH')
                                    <div>
                                        <label class="block text-xs font-medium text-ink-soft mb-1">Alasan pembatalan</label>
                                        <textarea name="reason" required rows="2"
                                                  class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass"></textarea>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-button type="button" variant="ghost" size="sm" @click="$store.modal.close()">Batal</x-button>
                                        <x-button type="submit" variant="danger" size="sm">Konfirmasi Batalkan</x-button>
                                    </div>
                                </form>
                            </x-modal>
                        @endif
                    @endforeach
                </tbody>
            </table>
            </div>
            <div class="mt-4"><x-pagination :paginator="$transactions" /></div>
        @endif
    </x-card>
@endsection
