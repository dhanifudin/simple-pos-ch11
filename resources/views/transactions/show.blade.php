@extends('layouts.app')

@section('title', 'Detail Transaksi')

@section('content')
    <x-breadcrumb :items="[['Transaksi', route('transactions.index')], ['Detail Transaksi']]" />
    <div class="flex items-center gap-3 mb-4">
        <h1 class="text-lg font-display font-semibold">Struk Transaksi</h1>
        @if ($transaction->isVoided())
            <x-badge tone="danger">Dibatalkan</x-badge>
        @endif
    </div>

    <x-card class="max-w-lg font-mono" x-data>
        <div class="mb-4 text-sm text-ink-soft">
            <p>Invoice: <span class="font-medium text-ink">{{ $transaction->invoice_no }}</span></p>
            <p>Tanggal: {{ $transaction->created_at->format('d M Y H:i') }}</p>
            <p>Kasir: {{ $transaction->user->name }}</p>
        </div>

        @if ($transaction->isVoided())
            <div class="mb-4 rounded-md border border-signal-red/30 bg-signal-red-soft px-3 py-2 text-sm text-signal-red font-sans">
                <p class="font-medium">Dibatalkan oleh {{ $transaction->voidedBy?->name ?? '—' }}
                    pada {{ $transaction->voided_at?->format('d M Y H:i') }}</p>
                <p class="mt-1">Alasan: {{ $transaction->void_reason }}</p>
            </div>
        @endif

        <div class="perforation mb-4"></div>

        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="text-left text-ink-soft border-b border-ink/10 text-xs uppercase tracking-wide">
                    <th class="py-2 font-medium">Produk</th>
                    <th class="py-2 font-medium">Qty</th>
                    <th class="py-2 font-medium">Harga</th>
                    <th class="py-2 font-medium">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transaction->details as $detail)
                    <tr class="border-b border-ink/5">
                        <td class="py-2 font-sans">{{ $detail->product->name }}</td>
                        <td class="py-2">{{ $detail->qty }}</td>
                        <td class="py-2 tabular-nums">Rp {{ number_format($detail->price, 0, ',', '.') }}</td>
                        <td class="py-2 tabular-nums">Rp {{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="perforation mb-4"></div>

        <div class="text-sm space-y-1 mb-2">
            <div class="flex justify-between">
                <span class="font-sans text-ink-soft">Subtotal</span>
                <span class="tabular-nums">Rp {{ number_format($transaction->details->sum('subtotal'), 0, ',', '.') }}</span>
            </div>
            @if ($transaction->discount > 0)
                <div class="flex justify-between">
                    <span class="font-sans text-ink-soft">Diskon</span>
                    <span class="tabular-nums">&minus; Rp {{ number_format($transaction->discount, 0, ',', '.') }}</span>
                </div>
            @endif
            @if ($transaction->tax > 0)
                <div class="flex justify-between">
                    <span class="font-sans text-ink-soft">Pajak</span>
                    <span class="tabular-nums">Rp {{ number_format($transaction->tax, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>

        <div class="flex justify-between font-semibold text-base">
            <span class="font-sans">Total</span>
            <span class="tabular-nums">Rp {{ number_format($transaction->total, 0, ',', '.') }}</span>
        </div>

        <div class="mt-3 text-sm space-y-1">
            <div class="flex justify-between">
                <span class="font-sans text-ink-soft">Metode Bayar</span>
                <span>{{ $transaction->paymentMethodLabel() }}</span>
            </div>
            @if ($transaction->payment_method === 'tunai')
                <div class="flex justify-between">
                    <span class="font-sans text-ink-soft">Uang Diterima</span>
                    <span class="tabular-nums">Rp {{ number_format($transaction->amount_paid, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-sans text-ink-soft">Kembalian</span>
                    <span class="tabular-nums">Rp {{ number_format($transaction->change_due, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between mt-6 font-sans">
            <a href="{{ route('transactions.index') }}" class="text-sm text-ink-soft hover:text-brass hover:underline">
                &larr; Kembali ke riwayat
            </a>
            <x-button :href="route('transactions.pdf', $transaction)" variant="ghost" size="sm">Unduh PDF</x-button>
        </div>

        @if (auth()->user()->isAdmin() && ! $transaction->isVoided())
            <div class="mt-4 pt-4 border-t border-ink/10 font-sans">
                <button type="button" @click="$store.modal.open('void-transaction')"
                        class="text-sm text-signal-red hover:underline">Batalkan Transaksi</button>
            </div>

            <x-modal id="void-transaction" title="Batalkan Transaksi">
                <p class="text-sm text-ink-soft mb-3 font-sans">
                    Stok akan dikembalikan dan transaksi dikecualikan dari laporan. Transaksi tetap
                    tampil di riwayat dengan status "Dibatalkan".
                </p>
                <form method="POST" action="{{ route('transactions.void', $transaction) }}" class="space-y-3 font-sans">
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
    </x-card>
@endsection
