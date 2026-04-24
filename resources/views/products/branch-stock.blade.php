@extends('layouts.app')

@section('title', 'Stok Cabin - ' . $product->name)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-2">
            <a href="{{ route('products.index') }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Stok Cabin - {{ $product->name }}</h1>
        </div>
        <p class="text-gray-500 dark:text-gray-400">Kelola stok produk per cabang</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                @if($product->image)
                <img src="{{ Storage::url($product->image) }}" alt="" class="w-full h-full object-cover rounded-lg">
                @else
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                @endif
            </div>
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $product->name }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">SKU: {{ $product->sku }}</p>
                @if($product->hasMaterials())
                <span class="inline-flex items-center px-2 py-1 mt-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                    Komposisi: {{ $product->materials()->count() }} bahan
                </span>
                <!-- Komposisi Detail -->
                <div class="mt-4 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                    <h3 class="text-sm font-semibold text-purple-800 dark:text-purple-200 mb-3">Komposisi Bahan (Takaran)</h3>
                    <div class="space-y-2">
                        @foreach($product->materials as $material)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-purple-700 dark:text-purple-300">{{ $material->name }}</span>
                            <span class="text-sm font-medium text-purple-800 dark:text-purple-200">{{ $material->pivot->quantity }} {{ $material->unit }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                <span class="inline-flex items-center px-2 py-1 mt-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                    Tanpa Komposisi
                </span>
                @endif
            </div>
        </div>

        <form action="{{ route('products.branch-stock.bulk', $product) }}" method="POST">
            @csrf
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cabang</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stok Saat Ini</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stok Baru</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @php
                            $branchStocks = $product->branchStocks()->pluck('stock', 'branch_id')->toArray();
                            $hasMaterials = $product->hasMaterials();
                        @endphp
                        @foreach($branches as $branch)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $branch->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if($hasMaterials)
                                @php
                                    $calculatedStock = $product->calculateStockFromMaterials($branch->id);
                                @endphp
                                <span class="text-sm font-medium text-purple-600 dark:text-purple-400">
                                    {{ intval($calculatedStock) }} <span class="text-gray-500 text-xs">(dihitung dari bahan)</span>
                                </span>
                                @else
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ intval($branchStocks[$branch->id] ?? 0) }}
                                </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" name="stocks[{{ $loop->index }}][branch_id]" value="{{ $branch->id }}" hidden>
                                @if($hasMaterials)
                                <input type="number" name="stocks[{{ $loop->index }}][stock]" 
                                    value="0"
                                    readonly
                                    class="w-32 px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-gray-400 text-sm bg-gray-50 dark:bg-gray-800"
                                    placeholder="Otomatis">
                                <p class="mt-1 text-xs text-gray-500">Stok dihitung otomatis dari bahan</p>
                                @else
                                <input type="number" name="stocks[{{ $loop->index }}][stock]" 
                                    value="{{ $branchStocks[$branch->id] ?? 0 }}"
                                    min="0" 
                                    class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white text-sm">
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('products.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                    Batal
                </a>
                <button type="submit" class="px-4 py-2 bg-teal-500 text-white rounded-lg hover:bg-teal-600">
                    Simpan Semua
                </button>
            </div>
        </form>
    </div>
</div>
@endsection