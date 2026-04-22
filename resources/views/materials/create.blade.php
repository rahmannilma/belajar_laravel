@extends('layouts.app')

@section('title', 'Tambah Bahan')

@section('content')
<div class="max-w-3xl mx-auto">
    <!-- Breadcrumbs -->
    <nav class="flex mb-6 text-sm text-gray-500 dark:text-gray-400" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="{{ route('dashboard') }}" class="hover:text-teal-500">Dashboard</a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-4 h-4 mx-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <a href="{{ route('materials.index') }}" class="hover:text-teal-500">Alat & Bahan</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-4 h-4 mx-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="font-medium text-gray-900 dark:text-white">Tambah Bahan</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Tambah Bahan Baru</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Masukkan informasi detail bahan baku atau peralatan</p>
        </div>

        <form action="{{ route('materials.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Bahan <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-teal-500 focus:border-teal-500"
                        placeholder="Contoh: Kopi Bubuk, Gula Pasir, Cup 16oz">
                    @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>

                <!-- Unit -->
                <div>
                    <label for="unit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Satuan <span class="text-red-500">*</span></label>
                    <select name="unit" id="unit" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-teal-500 focus:border-teal-500">
                        <option value="gr" {{ old('unit') == 'gr' ? 'selected' : '' }}>Gram (gr)</option>
                        <option value="ml" {{ old('unit') == 'ml' ? 'selected' : '' }}>Mililiter (ml)</option>
                        <option value="pcs" {{ old('unit') == 'pcs' ? 'selected' : '' }}>Pieces (pcs)</option>
                        <option value="kg" {{ old('unit') == 'kg' ? 'selected' : '' }}>Kilogram (kg)</option>
                        <option value="liter" {{ old('unit') == 'liter' ? 'selected' : '' }}>Liter (l)</option>
                        <option value="pack" {{ old('unit') == 'pack' ? 'selected' : '' }}>Pack</option>
                    </select>
                    @error('unit') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>

                <!-- Purchase Price -->
                <div>
                    <label for="purchase_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Harga Beli per Satuan <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">Rp</span>
                        <input type="number" name="purchase_price" id="purchase_price" value="{{ old('purchase_price', 0) }}" required min="0"
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-teal-500 focus:border-teal-500"
                            placeholder="0">
                    </div>
                    @error('purchase_price') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>

                <!-- Min Stock -->
                <div>
                    <label for="min_stock" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stok Minimum <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="min_stock" id="min_stock" value="{{ old('min_stock', 0) }}" required min="0"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-teal-500 focus:border-teal-500"
                        placeholder="0">
                    <p class="mt-1 text-xs text-gray-500">Peringatan akan muncul jika stok di bawah nilai ini.</p>
                    @error('min_stock') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ route('materials.index') }}" class="px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors font-medium">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-teal-500 to-cyan-600 text-white rounded-lg font-medium hover:from-teal-600 hover:to-cyan-700 transition-all shadow-md shadow-teal-500/20">
                    Simpan Bahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
