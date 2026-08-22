@extends('layouts.app')

@section('title', 'Tambah Produk')

@section('content')
    <x-breadcrumb :items="[['Produk', route('products.index')], ['Tambah Produk']]" />
    <h1 class="text-lg font-display font-semibold mb-4">Tambah Produk</h1>

    <x-card class="max-w-lg">
        <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @include('products._form')
            <div class="flex gap-2">
                <x-button type="submit">Simpan</x-button>
                <x-button variant="ghost" :href="route('products.index')">Batal</x-button>
            </div>
        </form>
    </x-card>
@endsection
