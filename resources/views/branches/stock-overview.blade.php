@extends('layouts.app')

@section('title', 'Stok Cabin')

@section('content')
<div class="max-w-7xl mx-auto min-h-screen bg-gray-50 dark:bg-gray-900" x-data="stockManager()" x-init="init()">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Stok Cabin</h1>
        <p class="text-gray-500 dark:text-gray-400">Cek stok produk dan bahan per cabang</p>
    </div>

    <!-- Stock Adjustment Modal -->
    <template x-if="showModal">
    <div class="modal-wrapper">
        <div class="modal-backdrop" @click="showModal = false"></div>
        <div class="modal-box">
            <h3>Penyesuaian Stok: <span x-text="modalName"></span></h3>
            
            <div class="form-group">
                <label>Tipe Penyesuaian</label>
                <div class="flex gap-2">
                    <button type="button" @click="modalType = 'add'" :class="modalType === 'add' ? 'btn-active' : 'btn-inactive'">Tambah Stok</button>
                    <button type="button" @click="modalType = 'reduce'" :class="modalType === 'reduce' ? 'btn-active' : 'btn-inactive'">Kurangi Stok</button>
                </div>
            </div>
            
            <div class="form-group">
                <label>Stok Saat Ini</label>
                <p><span x-text="Math.floor(modalCabinStock)"></span> <span x-text="modalUnit"></span></p>
            </div>
            
            <form :action="modalAction" method="POST" id="stock-form" @submit.prevent="submitStockAdjustment()">
                @csrf
                <input type="hidden" name="type" :value="modalType">
                <input type="hidden" name="quantity" :value="modalQuantity">
                <input type="hidden" name="reason" value="">
            
                <div class="form-group">
                    <label>Jumlah (<span x-text="modalUnit"></span>)</label>
                    <input type="number" step="0.01" x-model="modalQuantity" required min="0.01">
                </div>
                
                <div class="form-group">
                    <label>Keterangan (Opsional)</label>
                    <input type="text" name="reason" placeholder="Contoh: Barang datang, Rusak">
                </div>
                
                <div class="flex gap-2 mt-4">
                    <button type="submit" class="btn-primary">Simpan</button>
                    <button type="button" @click="deleteStock()" class="btn-danger">Hapus</button>
                    <button type="button" @click="showModal = false" class="btn-secondary">Batal</button>
                </div>
            </form>
        </div>
    </div>
    </template>
    
    <style>
    .modal-wrapper { position: fixed; inset: 0; z-index: 9999; }
    .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.5); }
    .modal-box { 
        position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
        background: white; padding: 24px; border-radius: 8px; width: 400px; max-width: 90vw;
    }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 4px; color: #374151; }
    .form-group input { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; }
    .form-group p { font-weight: 600; }
    .btn-active { background: #14b8a6; color: white; padding: 8px 16px; border-radius: 4px; }
    .btn-inactive { background: #f3f4f6; color: #374151; padding: 8px 16px; border-radius: 4px; }
    .btn-primary { background: #0d9488; color: white; padding: 8px 16px; border-radius: 4px; }
    .btn-secondary { background: #e5e7eb; color: #374151; padding: 8px 16px; border-radius: 4px; }
    .btn-danger { background: #ef4444; color: white; padding: 8px 16px; border-radius: 4px; }
    .flex { display: flex; }
    .gap-2 { gap: 8px; }
    .mt-4 { margin-top: 16px; }
    
    .dark .modal-box { background: #1f2937; }
    .dark .form-group label { color: #e5e7eb; }
    .dark .form-group input { background: #374151; border-color: #4b5563; color: #f9fafb; }
    .dark .btn-inactive { background: #374151; color: #e5e7eb; }
    .dark .btn-secondary { background: #4b5563; color: #e5e7eb; }
    .dark .btn-danger { background: #dc2626; color: white; }
    </style>

    <div class="mb-6 flex gap-2 overflow-x-auto pb-2">
        <template x-for="branch in branches" :key="branch.id">
            <button @click="selectBranch(branch.id)" 
                class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors"
                :class="selectedBranchId === branch.id 
                    ? 'bg-teal-500 text-white' 
                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'">
                <span x-text="branch.name"></span>
            </button>
        </template>
    </div>

    <div class="mb-4 flex gap-3">
        <div class="flex-1">
            <input type="text" x-model="searchQuery" @input="filterData()" 
                placeholder="Cari produk atau bahan..." 
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
        </div>
        <select x-model="filterStatus" @change="filterData()" 
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
            <option value="">Semua</option>
            <option value="low">Stok Rendah</option>
            <option value="out">Habis</option>
            <option value="available">Tersedia</option>
        </select>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Products Stock -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Produk</h2>
                <span class="px-2 py-1 bg-teal-100 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 text-sm font-medium rounded-full">
                    <span x-text="filteredProducts.length"></span> item
                </span>
            </div>
            <div class="max-h-[500px] overflow-y-auto">
                <div x-show="loading" class="p-6 text-center">
                    <svg class="animate-spin h-8 w-8 text-teal-500 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div x-show="!loading && filteredProducts.length === 0" class="p-6 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Tidak ada produk di cabin ini</p>
                </div>
                <table x-show="!loading && filteredProducts.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Produk</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stok</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <template x-for="product in filteredProducts" :key="product.id">
                            <tr :class="product.cabin_stock <= product.min_stock ? 'bg-orange-50 dark:bg-orange-950/30' : ''">
                                <td class="px-4 py-2">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="product.name"></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="product.category?.name"></p>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-sm font-semibold" 
                                        :class="product.cabin_stock <= product.min_stock ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'"
                                        x-text="Math.floor(product.cabin_stock)"></span>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="showModal = true; modalId = 'p' + product.id; modalName = product.name; modalUnit = 'pcs'; modalCabinStock = product.cabin_stock; modalAction = '/branches/' + selectedBranchId + '/adjust-stock/p' + product.id" class="p-1.5 text-gray-400 hover:text-orange-500 transition-colors" title="Penyesuaian Stok">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                            </svg>
                                        </button>
                                        <span x-show="product.cabin_stock <= 0" class="px-2 py-0.5 text-xs font-medium bg-red-200 dark:bg-red-800 text-red-700 dark:text-red-200 rounded-full">Habis</span>
                                        <span x-show="product.cabin_stock > 0 && product.cabin_stock <= product.min_stock" class="px-2 py-0.5 text-xs font-medium bg-orange-200 dark:bg-orange-700 text-orange-700 dark:text-orange-200 rounded-full">Rendah</span>
                                        <span x-show="product.cabin_stock > product.min_stock" class="px-2 py-0.5 text-xs font-medium bg-green-200 dark:bg-green-800 text-green-700 dark:text-green-200 rounded-full">Tersedia</span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Materials Stock -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Bahan</h2>
                <span class="px-2 py-1 bg-teal-100 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 text-sm font-medium rounded-full">
                    <span x-text="filteredMaterials.length"></span> item
                </span>
            </div>
            <div class="max-h-[500px] overflow-y-auto">
                <div x-show="loading" class="p-6 text-center">
                    <svg class="animate-spin h-8 w-8 text-teal-500 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div x-show="!loading && filteredMaterials.length === 0" class="p-6 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Tidak ada bahan di cabin ini</p>
                </div>
                <table x-show="!loading && filteredMaterials.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bahan</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stok</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <template x-for="material in filteredMaterials" :key="material.id">
                            <tr :class="material.cabin_stock <= material.min_stock ? 'bg-orange-50 dark:bg-orange-950/30' : ''">
                                <td class="px-4 py-2">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="material.name"></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="material.unit"></p>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-sm font-semibold" 
                                        :class="material.cabin_stock <= material.min_stock ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'"
                                        x-text="Math.floor(material.cabin_stock)"></span>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="showModal = true; modalId = 'm' + material.id; modalName = material.name; modalUnit = material.unit; modalCabinStock = material.cabin_stock; modalAction = '/branches/' + selectedBranchId + '/adjust-stock/m' + material.id" class="p-1.5 text-gray-400 hover:text-orange-500 transition-colors" title="Penyesuaian Stok">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                            </svg>
                                        </button>
                                        <span x-show="material.cabin_stock <= 0" class="px-2 py-0.5 text-xs font-medium bg-red-200 dark:bg-red-800 text-red-700 dark:text-red-200 rounded-full">Habis</span>
                                        <span x-show="material.cabin_stock > 0 && material.cabin_stock <= material.min_stock" class="px-2 py-0.5 text-xs font-medium bg-orange-200 dark:bg-orange-700 text-orange-700 dark:text-orange-200 rounded-full">Rendah</span>
                                        <span x-show="material.cabin_stock > material.min_stock" class="px-2 py-0.5 text-xs font-medium bg-green-200 dark:bg-green-800 text-green-700 dark:text-green-200 rounded-full">Tersedia</span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
</div>

    <script>
function stockManager() {
    return {
        branches: @json($branches),
        selectedBranchId: {{ $selectedBranch->id }},
        products: [],
        materials: [],
        searchQuery: '',
        filterStatus: '',
        loading: false,

        showModal: false,
        modalId: null,
        modalName: '',
        modalUnit: '',
        modalCabinStock: 0,
        modalType: 'add',
        modalQuantity: 1,
        modalAction: '',

        init() {
            this.loadStock();
        },

        openStockModal(data) {
            this.modalId = data.id;
            this.modalName = data.name;
            this.modalUnit = data.unit || 'pcs';
            this.modalCabinStock = data.cabinStock || 0;
            this.modalType = 'add';
            this.modalQuantity = 1;
            this.modalAction = '/branches/' + data.branchId + '/adjust-stock/' + data.id;
            this.showModal = true;
        },

        async submitStockAdjustment() {
            const form = document.getElementById('stock-form');
            const formData = new FormData(form);
            console.log('Submitting to:', this.modalAction);
            console.log('Type:', formData.get('type'));
            console.log('Quantity:', formData.get('quantity'));
            fetch(this.modalAction, {
                method: 'POST',
                body: formData
            }).then(r => {
                console.log('Response status:', r.status);
                return r.text();
            }).then(text => {
                console.log('Response:', text);
                this.showModal = false;
                this.loadStock();
            }).catch(e => {
                console.error(e);
                this.showModal = false;
                this.loadStock();
            });
        },

        async deleteStock() {
            if (!confirm('Yakin hapus produk ini dari cabang?')) return;
            const deleteUrl = this.modalAction.replace('adjust-stock', 'remove-stock');
            try {
                const response = await fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                console.log('Response:', response.status);
                if (response.ok) {
                    this.showModal = false;
                    this.loadStock();
                } else {
                    alert('Berhasil dihapus');
                    this.showModal = false;
                    this.loadStock();
                }
            } catch (error) {
                console.error('Error:', error);
                this.showModal = false;
                this.loadStock();
            }
        },

        async loadStock() {
            this.loading = true;
            try {
                const response = await fetch(`/api/cabang/stok/${this.selectedBranchId}`);
                const data = await response.json();
                this.products = data.products;
                this.materials = data.materials;
                this.filterData();
            } catch (error) {
                console.error('Error loading stock:', error);
            } finally {
                this.loading = false;
            }
        },

        async selectBranch(branchId) {
            this.selectedBranchId = branchId;
            await this.loadStock();
        },

        filterData() {
        },

        get filteredProducts() {
            return this.products.filter(p => {
                const matchesSearch = !this.searchQuery || 
                    p.name.toLowerCase().includes(this.searchQuery.toLowerCase());
                const matchesStatus = !this.filterStatus || 
                    (this.filterStatus === 'low' && p.cabin_stock <= p.min_stock && p.cabin_stock > 0) ||
                    (this.filterStatus === 'out' && p.cabin_stock <= 0) ||
                    (this.filterStatus === 'available' && p.cabin_stock > p.min_stock);
                return matchesSearch && matchesStatus;
            });
        },

        get filteredMaterials() {
            return this.materials.filter(m => {
                const matchesSearch = !this.searchQuery || 
                    m.name.toLowerCase().includes(this.searchQuery.toLowerCase());
                const matchesStatus = !this.filterStatus || 
                    (this.filterStatus === 'low' && m.cabin_stock <= m.min_stock && m.cabin_stock > 0) ||
                    (this.filterStatus === 'out' && m.cabin_stock <= 0) ||
                    (this.filterStatus === 'available' && m.cabin_stock > m.min_stock);
                return matchesSearch && matchesStatus;
            });
        }
    };
}
</script>

@endsection