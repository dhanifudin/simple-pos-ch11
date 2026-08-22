@extends('layouts.app')

@section('title', 'Kasir')

@section('content')
    <h1 class="text-lg font-display font-semibold mb-4">Transaksi Kasir</h1>

    <div x-data="posCart({{ $shopSetting->tax_percent }})" x-init="init()" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <x-card class="order-last lg:order-none lg:col-span-2">
            <div class="flex items-center justify-between mb-3 gap-3">
                <h2 class="font-display text-xs font-semibold uppercase tracking-wide text-ink-soft">Daftar Produk</h2>
                <form method="GET" action="{{ route('pos.create') }}">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari produk..."
                           class="border border-ink/15 rounded-md px-3 py-1.5 text-sm w-40 focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                </form>
            </div>

            <div class="mb-4">
                <input type="text" x-model="scanInput" @keydown.enter.prevent="scan()"
                       placeholder="Scan atau ketik SKU lalu Enter..."
                       class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm font-mono focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                <p x-show="scanError" x-cloak x-text="scanError" class="text-xs text-signal-red mt-1"></p>
            </div>
            @if ($products->isEmpty())
                <x-empty-state>Tidak ada produk yang cocok.</x-empty-state>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach ($products as $product)
                        <button type="button"
                                @click="add({{ \Illuminate\Support\Js::from(['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'price' => $product->price, 'stock' => $product->stock]) }})"
                                class="text-left border border-ink/10 rounded-md p-3 hover:border-brass hover:bg-brass-soft/40 transition-colors">
                            <x-product-thumb :product="$product" size="w-full aspect-square mb-2" text-size="text-2xl" />
                            <p class="text-sm font-medium">{{ $product->name }}</p>
                            <p class="text-xs font-mono text-ink-soft/70">{{ $product->sku }}</p>
                            <p class="text-xs font-mono text-ink-soft">Rp {{ number_format($product->price, 0, ',', '.') }} &middot; stok {{ $product->stock }}</p>
                        </button>
                    @endforeach
                </div>
                <div class="mt-4"><x-pagination :paginator="$products" /></div>
            @endif
        </x-card>

        <x-card class="order-first lg:order-none lg:sticky lg:top-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-display text-xs font-semibold uppercase tracking-wide text-ink-soft">Keranjang</h2>
                <button type="button" x-show="list.length > 0" x-cloak @click="$store.modal.open('clear-cart')"
                        class="text-xs text-signal-red hover:underline">Kosongkan Keranjang</button>
            </div>
            <form method="POST" action="{{ route('transactions.store') }}" @submit="checkout">
                @csrf
                <template x-if="list.length === 0">
                    <p class="text-xs text-ink-soft">Keranjang masih kosong.</p>
                </template>
                <div class="space-y-3 mb-2 text-sm">
                    <template x-for="item in list" :key="item.id">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-medium truncate" x-text="item.name"></p>
                                <p class="text-xs font-mono text-ink-soft/70" x-text="item.sku"></p>
                                <p class="text-xs font-mono text-ink-soft" x-text="formatRupiah(item.price) + ' x ' + item.qty"></p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" @click="changeQty(item.id, -1)"
                                        class="w-6 h-6 border border-ink/15 rounded text-xs hover:border-brass">-</button>
                                <span class="w-6 text-center font-mono" x-text="item.qty"></span>
                                <button type="button" @click="changeQty(item.id, 1)"
                                        class="w-6 h-6 border border-ink/15 rounded text-xs hover:border-brass">+</button>
                            </div>
                            <input type="hidden" :name="`items[${item.id}][product_id]`" :value="item.id">
                            <input type="hidden" :name="`items[${item.id}][qty]`" :value="item.qty">
                        </div>
                    </template>
                </div>

                <div x-show="list.length > 0" x-cloak>
                    <div class="perforation my-3"></div>
                    <label class="block text-xs font-medium text-ink-soft mb-1">Diskon</label>
                    <input type="text" inputmode="numeric" placeholder="0"
                           :value="discountInput !== null ? discountInput.toLocaleString('id-ID') : ''"
                           @input="onDiscountInput($event)"
                           class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm font-mono focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                    <input type="hidden" name="discount" :value="discount">
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <template x-for="d in quickDiscounts()" :key="d.pct">
                            <button type="button" @click="discountInput = d.amount"
                                    class="border border-ink/15 rounded px-2 py-1 text-xs font-mono hover:border-brass hover:text-brass"
                                    x-text="d.pct + '% (' + formatRupiah(d.amount) + ')'"></button>
                        </template>
                    </div>

                    <label class="block text-xs font-medium text-ink-soft mb-1 mt-3">Uang Diterima</label>
                    <input type="text" inputmode="numeric" placeholder="0"
                           :value="amountPaid !== null ? amountPaid.toLocaleString('id-ID') : ''"
                           @input="onAmountInput($event)"
                           class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm font-mono focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                    <input type="hidden" name="amount_paid" :value="amountPaid ?? ''">
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <template x-for="amount in quickAmounts()" :key="amount">
                            <button type="button" @click="amountPaid = amount"
                                    class="border border-ink/15 rounded px-2 py-1 text-xs font-mono hover:border-brass hover:text-brass"
                                    x-text="formatRupiah(amount)"></button>
                        </template>
                    </div>
                </div>

                <div class="perforation my-3"></div>
                <div class="text-sm space-y-1 mb-2">
                    <div class="flex justify-between">
                        <span class="text-ink-soft">Subtotal</span>
                        <span class="font-mono tabular-nums" x-text="formatRupiah(itemsSubtotal)"></span>
                    </div>
                    <div class="flex justify-between" x-show="discount > 0" x-cloak>
                        <span class="text-ink-soft">Diskon</span>
                        <span class="font-mono tabular-nums">&minus; <span x-text="formatRupiah(discount)"></span></span>
                    </div>
                    <div class="flex justify-between" x-show="taxPercent > 0" x-cloak>
                        <span class="text-ink-soft" x-text="'Pajak (' + taxPercent + '%)'"></span>
                        <span class="font-mono tabular-nums" x-text="formatRupiah(taxAmount)"></span>
                    </div>
                </div>
                <div class="flex justify-between font-semibold text-base">
                    <span>Total</span>
                    <span class="font-mono tabular-nums" x-text="formatRupiah(total)"></span>
                </div>
                <div class="flex items-center justify-between mt-1 mb-4">
                    <span class="text-sm text-ink-soft">Kembalian</span>
                    <span class="font-mono tabular-nums font-bold text-2xl text-signal-green" x-text="formatRupiah(change)"></span>
                </div>

                <x-button type="submit" variant="success" class="w-full" x-bind:disabled="!canCheckout">
                    Simpan Transaksi
                </x-button>
            </form>
        </x-card>

        <x-modal id="clear-cart" title="Kosongkan Keranjang">
            <p class="text-sm text-ink-soft mb-4">Semua item di keranjang akan dihapus. Lanjutkan?</p>
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="ghost" size="sm" @click="$store.modal.close()">Batal</x-button>
                <x-button type="button" variant="danger" size="sm" @click="clearCart(); $store.modal.close()">Kosongkan</x-button>
            </div>
        </x-modal>
    </div>
@endsection
