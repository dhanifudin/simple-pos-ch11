@extends('layouts.app')

@section('title', 'Import Produk')

@section('content')
    <h1 class="text-lg font-display font-semibold mb-4">Import Data Produk</h1>

    <x-card class="max-w-lg">
        <p class="text-sm text-ink-soft mb-4">
            Unggah file CSV dengan kolom: <code class="font-mono text-xs bg-ink/5 px-1 py-0.5 rounded">category,name,sku,price,stock</code> (tanpa header khusus, baris pertama dianggap header).
        </p>
        <form method="POST" action="{{ route('reports.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="file" name="file" accept=".csv,.txt" required class="text-sm">
            <x-button type="submit">Import</x-button>
        </form>
        <a href="{{ route('reports.index') }}" class="inline-block mt-4 text-sm text-ink-soft hover:text-brass hover:underline">
            &larr; Kembali ke laporan
        </a>
    </x-card>
@endsection
