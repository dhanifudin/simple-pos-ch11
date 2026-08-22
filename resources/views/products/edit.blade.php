@extends('layouts.app')

@section('title', 'Ubah Produk')

@section('content')
    <x-breadcrumb :items="[['Produk', route('products.index')], ['Ubah Produk']]" />
    <h1 class="text-lg font-display font-semibold mb-4">Ubah Produk</h1>

    <x-card class="max-w-lg">
        <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf @method('PUT')
            @include('products._form')
            <div class="flex gap-2">
                <x-button type="submit">Perbarui</x-button>
                <x-button variant="ghost" :href="route('products.index')">Batal</x-button>
            </div>
        </form>
    </x-card>
@endsection
