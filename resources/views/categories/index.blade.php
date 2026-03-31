@extends('layouts.app')

@section('title', 'Kategori')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Kategori Produk</h1>
            <p class="text-gray-500 dark:text-gray-400">Kelola kategori untuk produk</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button @click="$dispatch('open-modal')" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-teal-500 to-cyan-600 text-white rounded-lg font-medium hover:from-teal-600 hover:to-cyan-700 transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Tambah Kategori
            </button>
        </div>
    </div>

    <!-- Categories Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($categories as $category)
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $category->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $category->products_count }} produk</p>
                    @if($category->description)
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">{{ Str::limit($category->description, 60) }}</p>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button @click="$dispatch('edit-category', { id: {{ $category->id }}, name: '{{ $category->name }}', description: '{{ $category->description ?? '' }}' })" class="p-2 text-gray-400 hover:text-blue-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                    </button>
                    <form action="{{ route('categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('Yakin ingin menghapus kategori ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-2 text-gray-400 hover:text-red-500 transition-colors" {{ $category->products()->exists() ? 'disabled' : '' }}>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-12">
            <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
            </svg>
            <p class="text-gray-500 dark:text-gray-400">Belum ada kategori</p>
        </div>
        @endforelse
    </div>

    <!-- Add/Edit Modal -->
    <div x-data="{ open: false, editMode: false, category: {} }" @open-modal.window="open = true; editMode = false; category = {}" @edit-category.window="open = true; editMode = true; category = $event.detail" @keydown.escape.window="open = false">
        <div x-show="open" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click="open = false">
            <div class="bg-white dark:bg-gray-800 rounded-xl max-w-md w-full" @click.stop>
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="editMode ? 'Edit Kategori' : 'Tambah Kategori'"></h3>
                </div>
                <form :action="editMode ? '/categories/' + category.id : '{{ route('categories.store') }}'" method="POST" class="p-6 space-y-4">
                    @csrf
                    <template x-if="editMode">
                        @method('PUT')
                    </template>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nama Kategori *</label>
                        <input type="text" name="name" x-model="category.name" required
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Deskripsi</label>
                        <textarea name="description" x-model="category.description" rows="3"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" @click="open = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-teal-500 text-white rounded-lg hover:bg-teal-600">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
