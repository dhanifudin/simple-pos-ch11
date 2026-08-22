@extends('layouts.app')

@section('title', 'Produk')

@section('content')
    <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
        <div>
            <h1 class="text-lg font-display font-semibold">Produk</h1>
            @if ($lowStock || $q !== '' || $categoryName)
                <p class="text-sm text-ink-soft mt-1">
                    @if ($lowStock) Stok menipis (&lt; 10) @endif
                    @if ($lowStock && ($q !== '' || $categoryName)) &middot; @endif
                    @if ($q !== '') Pencarian "{{ $q }}" @endif
                    @if ($q !== '' && $categoryName) &middot; @endif
                    @if ($categoryName) Kategori: {{ $categoryName }} @endif
                    &middot; <a href="{{ route('products.index') }}" class="text-brass hover:underline">Lihat semua</a>
                </p>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2">
                @if ($lowStock)
                    <input type="hidden" name="low_stock" value="1">
                @endif
                @if ($categoryId)
                    <input type="hidden" name="category_id" value="{{ $categoryId }}">
                @endif
                <input type="search" name="q" value="{{ $q }}" placeholder="Cari nama/SKU..."
                       class="border border-ink/15 rounded-md px-3 py-2 text-sm w-48 focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
            </form>
            <x-button :href="route('products.create')">+ Produk Baru</x-button>
        </div>
    </div>

    <x-card x-data="{ selected: [] }">
        @if ($products->isEmpty())
            <x-empty-state>Belum ada produk. Tambahkan produk pertama untuk mulai berjualan.</x-empty-state>
        @else
            <div x-show="selected.length > 0" x-cloak
                 class="mb-3 flex items-center justify-between gap-3 rounded-md border border-brass/30 bg-brass-soft/40 px-3 py-2 text-sm">
                <span><span x-text="selected.length"></span> produk dipilih</span>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('products.bulk-status') }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="is_active" value="1">
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="ids[]" :value="id">
                        </template>
                        <x-button type="submit" variant="success" size="sm">Aktifkan</x-button>
                    </form>
                    <form method="POST" action="{{ route('products.bulk-status') }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="is_active" value="0">
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="ids[]" :value="id">
                        </template>
                        <x-button type="submit" variant="danger" size="sm">Nonaktifkan</x-button>
                    </form>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-ink-soft border-b border-ink/10 text-xs uppercase tracking-wide">
                        <th class="py-2 w-8">
                            <input type="checkbox"
                                   :checked="selected.length === {{ $products->count() }} && {{ $products->count() }} > 0"
                                   @change="selected = $event.target.checked ? {{ \Illuminate\Support\Js::from($products->pluck('id')) }} : []">
                        </th>
                        <th class="py-2"></th>
                        <th class="py-2 font-medium"><x-sortable-th column="sku" label="SKU" :sort="$sort" :direction="$direction" /></th>
                        <th class="py-2 font-medium"><x-sortable-th column="name" label="Nama" :sort="$sort" :direction="$direction" /></th>
                        <th class="py-2 font-medium">Kategori</th>
                        <th class="py-2 font-medium"><x-sortable-th column="price" label="Harga" :sort="$sort" :direction="$direction" /></th>
                        <th class="py-2 font-medium"><x-sortable-th column="stock" label="Stok" :sort="$sort" :direction="$direction" /></th>
                        <th class="py-2 font-medium">Status</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        @php $toggleLabel = $product->is_active ? 'Nonaktifkan' : 'Aktifkan'; @endphp
                        <tr class="border-b border-ink/5 {{ $product->is_active ? '' : 'opacity-50' }}">
                            <td class="py-3">
                                <input type="checkbox" :checked="selected.includes({{ $product->id }})"
                                       @change="$event.target.checked ? selected.push({{ $product->id }}) : selected = selected.filter(id => id !== {{ $product->id }})">
                            </td>
                            <td class="py-3"><x-product-thumb :product="$product" /></td>
                            <td class="py-3 font-mono text-xs">{{ $product->sku }}</td>
                            <td class="py-3">{{ $product->name }}</td>
                            <td class="py-3">{{ $product->category->name }}</td>
                            <td class="py-3 font-mono tabular-nums">Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                            <td class="py-3">
                                @if ($product->stock < 10)
                                    <x-badge tone="warn">{{ $product->stock }}</x-badge>
                                @else
                                    <span class="font-mono">{{ $product->stock }}</span>
                                @endif
                            </td>
                            <td class="py-3">
                                <x-status-toggle :checked="$product->is_active"
                                                  :label="$product->is_active ? 'Aktif' : 'Nonaktif'"
                                                  @click="$store.modal.open('toggle-{{ $product->id }}')" />
                            </td>
                            <td class="py-3 text-right">
                                <div class="hidden lg:flex items-center justify-end gap-3 whitespace-nowrap">
                                    <button type="button" @click="$store.modal.open('stock-{{ $product->id }}')"
                                            class="text-sm text-ink-soft hover:text-brass hover:underline">Sesuaikan Stok</button>
                                    <a href="{{ route('products.edit', $product) }}" class="text-sm text-ink-soft hover:text-brass hover:underline">Ubah</a>
                                </div>
                                <div class="lg:hidden flex justify-end">
                                    <x-row-menu>
                                        <button type="button" @click="$store.modal.open('stock-{{ $product->id }}')"
                                                class="block w-full text-left px-3 py-2 hover:bg-ink/5">Sesuaikan Stok</button>
                                        <a href="{{ route('products.edit', $product) }}" class="block w-full text-left px-3 py-2 hover:bg-ink/5">Ubah</a>
                                    </x-row-menu>
                                </div>
                            </td>
                        </tr>

                        <x-modal id="toggle-{{ $product->id }}" title="{{ $toggleLabel }} Produk">
                            <p class="text-sm text-ink-soft mb-4">
                                Yakin ingin {{ \Illuminate\Support\Str::lower($toggleLabel) }} <strong>{{ $product->name }}</strong>?
                            </p>
                            <form method="POST" action="{{ route('products.toggle', $product) }}" class="flex justify-end gap-2">
                                @csrf @method('PATCH')
                                <x-button type="button" variant="ghost" size="sm" @click="$store.modal.close()">Batal</x-button>
                                <x-button type="submit" variant="{{ $product->is_active ? 'danger' : 'success' }}" size="sm">{{ $toggleLabel }}</x-button>
                            </form>
                        </x-modal>

                        <x-modal id="stock-{{ $product->id }}" title="Sesuaikan Stok — {{ $product->name }}">
                            <p class="text-sm text-ink-soft mb-3">Stok saat ini: <strong class="font-mono">{{ $product->stock }}</strong></p>
                            <form method="POST" action="{{ route('products.stock.adjust', $product) }}" class="space-y-3">
                                @csrf @method('PATCH')
                                <div>
                                    <label class="block text-xs font-medium text-ink-soft mb-1">Jumlah Penyesuaian</label>
                                    <input type="number" name="delta" required placeholder="+10 atau -5"
                                           class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm font-mono focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                                    <p class="text-xs text-ink-soft mt-1">Positif untuk tambah stok (restock), negatif untuk koreksi turun.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-ink-soft mb-1">Alasan</label>
                                    <textarea name="reason" required rows="2" placeholder="Restock dari supplier, koreksi stock opname, dll."
                                              class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass"></textarea>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <x-button type="button" variant="ghost" size="sm" @click="$store.modal.close()">Batal</x-button>
                                    <x-button type="submit" size="sm">Simpan</x-button>
                                </div>
                            </form>
                        </x-modal>
                    @endforeach
                </tbody>
            </table>
            </div>
            <div class="mt-4"><x-pagination :paginator="$products" /></div>
        @endif
    </x-card>
@endsection
