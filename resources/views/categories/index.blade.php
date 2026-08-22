@extends('layouts.app')

@section('title', 'Kategori')

@section('content')
    <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
        <div>
            <h1 class="text-lg font-display font-semibold">Kategori Produk</h1>
            @if ($q !== '')
                <p class="text-sm text-ink-soft mt-1">
                    Pencarian "{{ $q }}"
                    &middot; <a href="{{ route('categories.index') }}" class="text-brass hover:underline">Lihat semua</a>
                </p>
            @endif
        </div>
        <form method="GET" action="{{ route('categories.index') }}">
            <label for="category-search" class="sr-only">Cari nama kategori</label>
            <input id="category-search" type="search" name="q" value="{{ $q }}" placeholder="Cari nama kategori..."
                   class="border border-ink/15 rounded-md px-3 py-2 text-sm w-48 focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
        </form>
    </div>

    <x-card class="mb-6">
        <form method="POST" action="{{ route('categories.store') }}" class="flex gap-2">
            @csrf
            <label for="new-category-name" class="sr-only">Nama kategori baru</label>
            <input id="new-category-name" type="text" name="name" placeholder="Nama kategori baru" required
                   class="flex-1 border border-ink/15 rounded-md px-3 py-2 text-sm focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
            <x-button type="submit">Tambah</x-button>
        </form>
    </x-card>

    <x-card x-data="{
        selected: [],
        categoryMeta: {{ \Illuminate\Support\Js::from($categories->keyBy('id')->map(fn ($c) => ['name' => $c->name, 'products_count' => $c->products_count])) }},
        get blockedSelected() { return this.selected.filter(id => this.categoryMeta[id]?.products_count > 0); },
        get deletableCount() { return this.selected.length - this.blockedSelected.length; },
    }">
        @if ($categories->isEmpty())
            <x-empty-state>Belum ada kategori.</x-empty-state>
        @else
            <div x-show="selected.length > 0" x-cloak
                 class="mb-3 flex items-center justify-between gap-3 rounded-md border border-brass/30 bg-brass-soft/40 px-3 py-2 text-sm">
                <span><span x-text="selected.length"></span> kategori dipilih</span>
                <button type="button" @click="$store.modal.open('bulk-delete-categories')"
                        class="text-sm text-signal-red hover:underline">Hapus Terpilih</button>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-ink-soft border-b border-ink/10 text-xs uppercase tracking-wide">
                        <th class="py-2 w-8">
                            <input type="checkbox"
                                   :checked="selected.length === {{ $categories->count() }} && {{ $categories->count() }} > 0"
                                   @change="selected = $event.target.checked ? {{ \Illuminate\Support\Js::from($categories->pluck('id')) }} : []">
                        </th>
                        <th class="py-2 font-medium"><x-sortable-th column="name" label="Nama" :sort="$sort" :direction="$direction" /></th>
                        <th class="py-2 font-medium"><x-sortable-th column="products_count" label="Jumlah Produk" :sort="$sort" :direction="$direction" /></th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr class="border-b border-ink/5">
                            <td class="py-3">
                                <input type="checkbox" :checked="selected.includes({{ $category->id }})"
                                       @change="$event.target.checked ? selected.push({{ $category->id }}) : selected = selected.filter(id => id !== {{ $category->id }})">
                            </td>
                            <td class="py-3">{{ $category->name }}</td>
                            <td class="py-3 font-mono">
                                <a href="{{ route('products.index', ['category_id' => $category->id]) }}" class="hover:text-brass hover:underline">
                                    {{ $category->products_count }} produk
                                </a>
                            </td>
                            <td class="py-3 text-right">
                                <div class="hidden lg:flex items-center justify-end gap-3">
                                    <button type="button" @click="$store.modal.open('edit-category-{{ $category->id }}')"
                                            class="text-sm text-ink-soft hover:text-brass hover:underline">Ubah</button>
                                    <button type="button" @click="$store.modal.open('delete-category-{{ $category->id }}')"
                                            class="text-sm text-signal-red hover:underline">Hapus</button>
                                </div>
                                <div class="lg:hidden flex justify-end">
                                    <x-row-menu>
                                        <button type="button" @click="$store.modal.open('edit-category-{{ $category->id }}')"
                                                class="block w-full text-left px-3 py-2 hover:bg-ink/5">Ubah</button>
                                        <button type="button" @click="$store.modal.open('delete-category-{{ $category->id }}')"
                                                class="block w-full text-left px-3 py-2 text-signal-red hover:bg-ink/5">Hapus</button>
                                    </x-row-menu>
                                </div>
                            </td>
                        </tr>

                        <x-modal id="edit-category-{{ $category->id }}" title="Ubah Kategori">
                            <form method="POST" action="{{ route('categories.update', $category) }}" class="space-y-3">
                                @csrf @method('PUT')
                                <div>
                                    <label for="category-name-{{ $category->id }}" class="block text-xs font-medium text-ink-soft mb-1">Nama Kategori</label>
                                    <input id="category-name-{{ $category->id }}" type="text" name="name" value="{{ $category->name }}" required
                                           class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
                                </div>
                                <div class="flex justify-end gap-2">
                                    <x-button type="button" variant="ghost" size="sm" @click="$store.modal.close()">Batal</x-button>
                                    <x-button type="submit" size="sm">Simpan</x-button>
                                </div>
                            </form>
                        </x-modal>

                        <x-modal id="delete-category-{{ $category->id }}" title="Hapus Kategori">
                            <p class="text-sm text-ink-soft mb-4">Yakin ingin menghapus kategori <strong>{{ $category->name }}</strong>?</p>
                            <form method="POST" action="{{ route('categories.destroy', $category) }}" class="flex justify-end gap-2">
                                @csrf @method('DELETE')
                                <x-button type="button" variant="ghost" size="sm" @click="$store.modal.close()">Batal</x-button>
                                <x-button type="submit" variant="danger" size="sm">Hapus</x-button>
                            </form>
                        </x-modal>
                    @endforeach
                </tbody>
            </table>
            </div>
            <div class="mt-4"><x-pagination :paginator="$categories" /></div>

            <x-modal id="bulk-delete-categories" title="Hapus Kategori Terpilih">
                <p class="text-sm text-ink-soft mb-2">
                    <span x-text="deletableCount"></span> dari <span x-text="selected.length"></span> kategori terpilih akan dihapus.
                </p>
                <p class="text-sm text-signal-red mb-4" x-show="blockedSelected.length > 0" x-cloak>
                    Dilewati (masih memiliki produk):
                    <span x-text="blockedSelected.map(id => categoryMeta[id]?.name).join(', ')"></span>
                </p>
                <form method="POST" action="{{ route('categories.bulk-delete') }}" class="flex justify-end gap-2">
                    @csrf @method('DELETE')
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <x-button type="button" variant="ghost" size="sm" @click="$store.modal.close()">Batal</x-button>
                    <x-button type="submit" variant="danger" size="sm">Hapus</x-button>
                </form>
            </x-modal>
        @endif
    </x-card>
@endsection
