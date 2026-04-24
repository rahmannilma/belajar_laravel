@extends('layouts.app')

@section('title', 'Detail Produk')

@section('content')
<div>
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $product->name }}</h1>
            <p class="text-gray-500 dark:text-gray-400">Detail produk</p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            <a href="{{ route('products.edit', $product) }}" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg font-medium hover:from-blue-600 hover:to-indigo-700 transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 002.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Produk
            </a>
            <a href="{{ route('products.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                Kembali ke Daftar Produk
            </a>
        </div>
    </div>

    <!-- Product Details -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Basic Info -->
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Informasi Dasar</h2>
                    <div class="space-y-3">
                        <div class="flex">
                            <span class="w-20 text-sm font-medium text-gray-500 dark:text-gray-400">SKU:</span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $product->sku }}</span>
                        </div>
                        <div class="flex">
                            <span class="w-20 text-sm font-medium text-gray-500 dark:text-gray-400">Kode Bar:</span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $product->barcode ?? '-' }}</span>
                        </div>
                        <div class="flex">
                            <span class="w-20 text-sm font-medium text-gray-500 dark:text-gray-400">Kategori:</span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $product->category->name ?? '-' }}</span>
                        </div>
                        <div class="flex">
                            <span class="w-20 text-sm font-medium text-gray-500 dark:text-gray-400">Harga Beli:</span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Rp {{ number_format($product->buy_price, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex">
                            <span class="w-20 text-sm font-medium text-gray-500 dark:text-gray-400">Harga Jual:</span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</span>
                        </div>
                
                        <div class="flex">
                            <span class="w-20 text-sm font-medium text-gray-500 dark:text-gray-400">Stok Minimal:</span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ intval($product->min_stock) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Deskripsi</h2>
                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $product->description ?? 'Tidak ada deskripsi.' }}</p>
                </div>

                <!-- Materials -->
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Bahan Baku</h2>
                    @if($product->materials->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400">Produk ini tidak menggunakan bahan baku.</p>
                    @else
                    <div class="space-y-2">
                        @foreach($product->materials as $material)
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m2 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $material->name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Jumlah: {{ $material->pivot->quantity }} {{ $material->unit }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                <!-- Branch Stocks -->
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Stok per Cabang</h2>
                    @if($product->branchStocks->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400">Belum ada stok yang tercatat per cabang.</p>
                    @else
                    <div class="space-y-2">
                        @foreach($product->branchStocks as $bs)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ $bs->branch->name }}:</span>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full 
                                {{ $bs->stock > $product->min_stock ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 
                                  ($bs->stock > 0 ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400') }}">
                                {{ intval($bs->stock) }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection