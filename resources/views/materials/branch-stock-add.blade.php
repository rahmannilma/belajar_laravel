@extends('layouts.app')

@section('title', 'Tambah Stok Cabin - ' . $material->name)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <div class="flex items-center gap-2 mb-2">
            <a href="{{ route('materials.branch-stock', $material) }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tambah Stok Cabin - {{ $material->name }}</h1>
        </div>
        <p class="text-gray-500 dark:text-gray-400">Tambahkan stok hanya untuk cabin yang dipilih. Stok cabin lain tidak terdampak.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('materials.branch-stock.store', $material) }}" method="POST">
            @csrf

            <!-- Pilih Cabang -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Pilih Cabin (Cabang) *</label>
                <select name="branch_id" required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white"
                    onchange="updateCurrentStock(this.value)">
                    <option value="">-- Pilih Cabin --</option>
                    @foreach($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Stok hanya akan ditambahkan pada cabin yang dipilih. Cabin lain tidak berubah.</p>
            </div>

            <!-- Stok Saat Ini (Info) -->
            <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Stok Saat Ini di Cabin Terpilih:</h3>
                <p class="text-2xl font-bold text-teal-600 dark:text-teal-400" id="current-stock-display">-</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Nama material: <strong>{{ $material->name }}</strong> ({{ $material->unit }})</p>
            </div>

            <!-- Jumlah Tambah -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Jumlah Stok Tambahan *</label>
                <input type="number" name="quantity" min="0.01" step="0.01" required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white"
                    placeholder="Masukkan jumlah stok yang ditambahkan">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Contoh: jika input 10, stok di cabin tersebut bertambah 10 {{ $material->unit }}.</p>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('materials.branch-stock', $material) }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                    Batal
                </a>
                <button type="submit" class="px-4 py-2 bg-teal-500 text-white rounded-lg hover:bg-teal-600">
                    Tambah Stok ke Cabin Ini
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const stockData = <?= json_encode($material->branchStocks()->pluck('stock', 'branch_id')->toArray()) ?>;

function updateCurrentStock(branchId) {
    const display = document.getElementById('current-stock-display');
    if (branchId && stockData[branchId] !== undefined) {
        display.textContent = stockData[branchId] + ' {{ $material->unit }}';
    } else {
        display.textContent = '0 {{ $material->unit }}';
    }
}

// Init on page load
document.addEventListener('DOMContentLoaded', function() {
    const select = document.querySelector('select[name="branch_id"]');
    if (select && select.value) {
        updateCurrentStock(select.value);
    }
});
</script>
@endsection
