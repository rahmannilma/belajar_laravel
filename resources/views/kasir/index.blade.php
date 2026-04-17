@extends('layouts.app')

@section('title', 'Kasir')

@push('styles')
<style>
@media print {
    body * { visibility: hidden; }
    #receipt-content, #receipt-content * { visibility: visible; }
    #receipt-content { position: absolute; left: 0; top: 0; width: 80mm; }
}
</style>
@endpush

@section('content')
<div x-data="posSystem()" x-init="init()" class="flex flex-col lg:flex-row gap-6">
    <!-- Left Side - Product List -->
    <div class="flex-1">
        <!-- Search Bar -->
        <div class="mb-4">
            <div class="relative">
                <input type="text" x-model="searchQuery" @input="searchProducts()" @keydown.escape="clearSearch()" 
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                    placeholder="Cari produk (nama, SKU, barcode)...">
                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <button x-show="searchQuery" @click="clearSearch()" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Category Filter -->
        <div class="mb-4 flex gap-2 overflow-x-auto pb-2">
            <button @click="selectedCategory = null" 
                :class="selectedCategory === null ? 'bg-teal-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors">
                Semua
            </button>
            @foreach($categories as $category)
            <button @click="selectedCategory = {{ $category->id }}" 
                :class="selectedCategory === {{ $category->id }} ? 'bg-teal-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors">
                {{ $category->name }} ({{ $category->products_count }})
            </button>
            @endforeach
        </div>

        <!-- Product Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            <template x-for="product in filteredProducts" :key="product.id">
                <button @click="addToCart(product)" 
                    :disabled="product.display_stock === 0"
                    class="relative p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-teal-500 dark:hover:border-teal-500 hover:shadow-md transition-all text-left group disabled:opacity-50 disabled:cursor-not-allowed">
                    <!-- Low Stock Warning -->
                    <div x-show="product.display_stock <= product.min_stock && product.display_stock > 0" 
                        class="absolute top-2 right-2 w-2 h-2 bg-orange-500 rounded-full"></div>
                    <div x-show="product.display_stock === 0" 
                        class="absolute top-2 right-2 px-1.5 py-0.5 bg-red-500 text-white text-[10px] rounded">Habis</div>
                    
                    <div class="aspect-square mb-3 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center overflow-hidden">
                        <svg x-show="!product.image" class="w-12 h-12 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <img x-show="product.image" :src="product.image" :alt="product.name" class="w-full h-full object-cover">
                    </div>
                    
                    <h4 class="font-semibold text-gray-900 dark:text-white text-sm truncate" x-text="product.name"></h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="product.category?.name || ''"></p>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-teal-600 dark:text-teal-400 font-bold" x-text="'Rp ' + formatNumber(product.selling_price)"></span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Stok: <span x-text="product.display_stock"></span></p>
                </button>
            </template>
        </div>

        <!-- No Products -->
        <div x-show="filteredProducts.length === 0" class="text-center py-12">
            <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
            <p class="text-gray-500 dark:text-gray-400">Produk tidak ditemukan</p>
        </div>
    </div>

    <!-- Right Side - Cart -->
    <div class="w-full lg:w-96">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 sticky top-24">
            <!-- Cart Header -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Keranjang</h2>
                    <span class="px-2 py-1 bg-teal-100 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 text-sm font-medium rounded-full">
                        <span x-text="cartItems.length"></span> item
                    </span>
                </div>
            </div>

            <!-- Cart Items -->
            <div class="p-4 max-h-80 overflow-y-auto">
                <template x-if="cartItems.length === 0">
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <p class="text-gray-500 dark:text-gray-400 text-sm">Keranjang kosong</p>
                    </div>
                </template>

                <div class="space-y-3">
                    <template x-for="(item, index) in cartItems" :key="index">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.name"></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Rp <span x-text="formatNumber(item.price)"></span></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="decrementQty(index)" class="w-8 h-8 flex items-center justify-center bg-gray-200 dark:bg-gray-600 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                    </svg>
                                </button>
                                <input type="number" x-model.number="item.quantity" @change="updateQty(index)" min="1" class="w-12 text-center border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white text-sm">
                                <button @click="incrementQty(index)" class="w-8 h-8 flex items-center justify-center bg-teal-500 text-white rounded-lg hover:bg-teal-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                </button>
                                <button @click="removeItem(index)" class="w-8 h-8 flex items-center justify-center text-red-500 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Discount -->
            <div class="px-4 pb-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Diskon</label>
                <input type="number" x-model.number="discountPercent" @change="calculateTotals()" min="0" max="100" 
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="0">
            </div>

            <!-- Totals -->
            <div class="px-4 pb-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                    <span class="font-medium text-gray-900 dark:text-white">Rp <span x-text="formatNumber(subtotal)"></span></span>
                </div>
                <div class="flex justify-between text-sm" x-show="discountPercent > 0">
                    <span class="text-gray-500 dark:text-gray-400">Diskon</span>
                    <span class="text-red-500">- Rp <span x-text="formatNumber(discountAmount)"></span></span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t border-gray-200 dark:border-gray-700 pt-2">
                    <span class="text-gray-900 dark:text-white">Total</span>
                    <span class="text-teal-600 dark:text-teal-400">Rp <span x-text="formatNumber(totalAmount)"></span></span>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="px-4 pb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Metode Bayar</label>
                <div class="grid grid-cols-3 gap-2">
                    <button @click="paymentMethod = 'cash'" 
                        :class="paymentMethod === 'cash' ? 'bg-teal-500 text-white border-teal-500' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600'"
                        class="py-2 px-3 border rounded-lg text-sm font-medium transition-colors">
                        💵 Tunai
                    </button>
                    <button @click="paymentMethod = 'qris'" 
                        :class="paymentMethod === 'qris' ? 'bg-teal-500 text-white border-teal-500' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600'"
                        class="py-2 px-3 border rounded-lg text-sm font-medium transition-colors">
                        📱 QRIS
                    </button>
                    <button @click="paymentMethod = 'transfer'" 
                        :class="paymentMethod === 'transfer' ? 'bg-teal-500 text-white border-teal-500' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600'"
                        class="py-2 px-3 border rounded-lg text-sm font-medium transition-colors">
                        🏦 Transfer
                    </button>
                </div>
            </div>

            <!-- Customer Name (Optional) -->
            <div class="px-4 pb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nama Pelanggan (opsional)</label>
                <input type="text" x-model="customerName" 
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white" placeholder="Masukkan nama...">
            </div>

            <!-- Actions -->
            <div class="px-4 pb-4 space-y-2">
                <button @click="processTransaction()" :disabled="cartItems.length === 0 || isProcessing"
                    class="w-full py-3 bg-gradient-to-r from-teal-500 to-cyan-600 text-white rounded-xl font-semibold hover:from-teal-600 hover:to-cyan-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!isProcessing">Bayar Sekarang</span>
                    <span x-show="isProcessing">Memproses...</span>
                </button>
                <button @click="clearCart()" x-show="cartItems.length > 0"
                    class="w-full py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-xl font-medium hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    Batalkan
                </button>
            </div>
        </div>

        <!-- Recent Transactions (Owner only) -->
        @if(auth()->user()->isOwner() && $recentSales->count() > 0)
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white">Transaksi Hari Ini</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Invoice</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Waktu</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bayar</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($recentSales as $sale)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">{{ $sale->invoice_number }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $sale->sale_date->format('H:i') }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $sale->payment_method_label }}</td>
                            <td class="px-4 py-3">
                                @if($sale->status === 'cancelled')
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    Dibatalkan
                                </span>
                                @else
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    Selesai
                                </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($sale->status !== 'cancelled')
                                <form action="{{ route('sales.cancel', $sale) }}" method="POST" class="inline" onsubmit="return confirm('Yakin ingin membatalkan transaksi ini?')">
                                    @csrf
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700">Batalkan</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    <!-- Receipt Modal -->
    <div x-show="showReceipt" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @keydown.escape.window="closeReceipt()">
        <div class="bg-white dark:bg-gray-800 rounded-xl max-w-md w-full max-h-[90vh] overflow-auto" @click.stop>
            <div id="receipt-content" class="p-6">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-bold">Smart POS</h2>
                    <p class="text-sm text-gray-500">Struk Pembayaran</p>
                </div>
                
                <div class="border-t border-b border-dashed border-gray-300 dark:border-gray-600 py-4 mb-4">
                    <p class="text-sm"><strong>No. Invoice:</strong> <span x-text="lastTransaction?.invoice_number"></span></p>
                    <p class="text-sm"><strong>Tanggal:</strong> <span x-text="new Date().toLocaleString('id-ID')"></span></p>
                    <p class="text-sm"><strong>Kasir:</strong> {{ auth()->user()->name }}</p>
                </div>

                <div class="space-y-2 mb-4">
                    <template x-for="item in lastTransaction?.items || []" :key="item.id">
                        <div class="flex justify-between text-sm">
                            <span><span x-text="item.product_name"></span> x<span x-text="item.quantity"></span></span>
                            <span x-text="'Rp ' + formatNumber(item.subtotal)"></span>
                        </div>
                    </template>
                </div>

                <div class="border-t border-dashed border-gray-300 dark:border-gray-600 pt-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span>Subtotal</span>
                        <span x-text="'Rp ' + formatNumber(lastTransaction?.subtotal || 0)"></span>
                    </div>
                    <div class="flex justify-between text-sm" x-show="lastTransaction?.discount_amount > 0">
                        <span>Diskon</span>
                        <span class="text-red-500">- Rp <span x-text="formatNumber(lastTransaction?.discount_amount || 0)"></span></span>
                    </div>
                    <div class="flex justify-between text-lg font-bold">
                        <span>Total</span>
                        <span class="text-teal-600" x-text="'Rp ' + formatNumber(lastTransaction?.total_amount || 0)"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>Metode Bayar</span>
                        <span x-text="lastTransaction?.payment_method === 'cash' ? 'Tunai' : lastTransaction?.payment_method === 'qris' ? 'QRIS' : 'Transfer'"></span>
                    </div>
                </div>

                <div class="text-center mt-6">
                    <p class="text-xs text-gray-500">Terima kasih atas kunjungan Anda!</p>
                    <p class="text-xs text-gray-500">Barang yang sudah dibeli tidak dapat dikembalikan</p>
                </div>
            </div>

            <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex gap-2">
                <button @click="printReceipt()" class="flex-1 py-2 bg-teal-500 text-white rounded-lg font-medium hover:bg-teal-600 transition-colors">
                    🖨️ Cetak
                </button>
                <button @click="closeReceipt()" class="flex-1 py-2 border border-gray-300 dark:border-gray-600 rounded-lg font-medium hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function posSystem() {
    return {
        products: @json($products),
        cartItems: [],
        searchQuery: '',
        selectedCategory: null,
        discountPercent: 0,
        subtotal: 0,
        discountAmount: 0,
        taxAmount: 0,
        totalAmount: 0,
        paymentMethod: 'cash',
        customerName: '',
        showReceipt: false,
        lastTransaction: null,
        isProcessing: false,

        init() {
            this.filteredProducts = this.products;
        },

        get filteredProducts() {
            let result = this.products;
            
            if (this.selectedCategory) {
                result = result.filter(p => p.category_id === this.selectedCategory);
            }
            
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                result = result.filter(p => 
                    p.name.toLowerCase().includes(query) ||
                    (p.sku && p.sku.toLowerCase().includes(query)) ||
                    (p.barcode && p.barcode.toLowerCase().includes(query))
                );
            }
            
            return result;
        },

        searchProducts() {
            // Trigger Alpine reactivity
            this.$forceUpdate;
        },

        clearSearch() {
            this.searchQuery = '';
        },

        addToCart(product) {
            const existingItem = this.cartItems.find(item => item.product_id === product.id);
            
            if (existingItem) {
                if (existingItem.quantity < product.display_stock) {
                    existingItem.quantity++;
                } else {
                    alert('Stok tidak mencukupi!');
                }
            } else {
                this.cartItems.push({
                    product_id: product.id,
                    name: product.name,
                    price: parseFloat(product.selling_price),
                    quantity: 1,
                    max_stock: product.display_stock
                });
            }
            
            this.calculateTotals();
        },

        incrementQty(index) {
            const item = this.cartItems[index];
            if (item.quantity < item.max_stock) {
                item.quantity++;
                this.calculateTotals();
            } else {
                alert('Stok tidak mencukupi!');
            }
        },

        decrementQty(index) {
            if (this.cartItems[index].quantity > 1) {
                this.cartItems[index].quantity--;
                this.calculateTotals();
            }
        },

        updateQty(index) {
            const item = this.cartItems[index];
            if (item.quantity < 1) item.quantity = 1;
            if (item.quantity > item.max_stock) {
                item.quantity = item.max_stock;
                alert('Stok tidak mencukupi!');
            }
            this.calculateTotals();
        },

        removeItem(index) {
            this.cartItems.splice(index, 1);
            this.calculateTotals();
        },

        clearCart() {
            if (confirm('Yakin ingin membatalkan semua item?')) {
                this.cartItems = [];
                this.discountPercent = 0;
                this.customerName = '';
                this.calculateTotals();
            }
        },

        calculateTotals() {
            this.subtotal = this.cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            this.discountAmount = this.subtotal * (this.discountPercent / 100);
            this.totalAmount = this.subtotal - this.discountAmount;
        },

        formatNumber(num) {
            return Math.round(num).toLocaleString('id-ID');
        },

        async processTransaction() {
            if (this.cartItems.length === 0) {
                alert('Keranjang masih kosong!');
                return;
            }

            this.isProcessing = true;

            try {
                const response = await fetch('{{ route('kasir') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        items: this.cartItems.map(item => ({
                            product_id: item.product_id,
                            quantity: item.quantity
                        })),
                        discount_percent: this.discountPercent,
                        payment_method: this.paymentMethod,
                        customer_name: this.customerName
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.lastTransaction = data.sale;
                    this.showReceipt = true;
                    this.cartItems = [];
                    this.discountPercent = 0;
                    this.customerName = '';
                    this.calculateTotals();
                    
                    // Refresh page after 2 seconds to update stock
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert(data.message || 'Terjadi kesalahan!');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan koneksi!');
            } finally {
                this.isProcessing = false;
            }
        },

        closeReceipt() {
            this.showReceipt = false;
        },

        printReceipt() {
            window.print();
        }
    };
}
</script>
@endsection
