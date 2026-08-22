@php $p = $product ?? null; @endphp
<div>
    <label class="block text-sm font-medium mb-1">Kategori</label>
    <select name="category_id" class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm bg-surface-raised focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $p?->category_id) == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
</div>
<div>
    <label class="block text-sm font-medium mb-1">Nama Produk</label>
    <input type="text" name="name" value="{{ old('name', $p?->name) }}" required
           class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
</div>
<div>
    <label class="block text-sm font-medium mb-1">SKU</label>
    <input type="text" name="sku" value="{{ old('sku', $p?->sku) }}" required
           class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm font-mono focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
</div>
<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">Harga (Rp)</label>
        <input type="number" name="price" value="{{ old('price', $p?->price) }}" required min="0"
               class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm font-mono focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Stok</label>
        @if ($p)
            <p class="w-full border border-ink/10 rounded-md px-3 py-2 text-sm font-mono bg-ink/5 text-ink-soft">{{ $p->stock }}</p>
            <p class="text-xs text-ink-soft mt-1">Ubah lewat "Sesuaikan Stok" di daftar produk (tercatat siapa &amp; kenapa).</p>
        @else
            <input type="number" name="stock" value="{{ old('stock', 0) }}" required min="0"
                   class="w-full border border-ink/15 rounded-md px-3 py-2 text-sm font-mono focus:border-brass focus:outline-none focus:ring-1 focus:ring-brass">
        @endif
    </div>
</div>
<div>
    <label class="block text-sm font-medium mb-1">Foto Produk</label>
    @if ($p?->image_url)
        <img src="{{ $p->image_url }}" alt="{{ $p->name }}" class="w-16 h-16 object-cover rounded-md border border-ink/10 mb-2">
    @endif
    <input type="file" name="image" accept="image/*"
           class="block w-full text-sm text-ink-soft file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-ink/15 file:bg-surface-raised file:text-sm file:font-medium hover:file:border-brass">
    <p class="text-xs text-ink-soft mt-1">Opsional, maks 2MB. {{ $p ? 'Kosongkan untuk mempertahankan foto saat ini.' : '' }}</p>
</div>
@if ($p)
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $p->is_active))
               class="rounded border-ink/30 text-brass focus:ring-brass">
        Produk aktif (tampil di kasir)
    </label>
@endif
