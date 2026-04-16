@extends('layouts.app')

@section('title', 'Dashboard')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode(array_column($last7Days, 'label')) !!},
                datasets: [
                    {
                        label: 'Penjualan',
                        data: {!! json_encode(array_column($last7Days, 'sales')) !!},
                        borderColor: 'rgb(20, 184, 166)',
                        backgroundColor: 'rgba(20, 184, 166, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    }

    // Transaction Chart
    const txCtx = document.getElementById('transactionChart');
    if (txCtx) {
        new Chart(txCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode(array_column($last7Days, 'date')) !!},
                datasets: [{
                    label: 'Jumlah Transaksi',
                    data: {!! json_encode(array_column($last7Days, 'count')) !!},
                    backgroundColor: 'rgba(20, 184, 166, 0.8)',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush

@section('content')
<div>
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
        <p class="text-gray-500 dark:text-gray-400">Selamat datang, {{ auth()->user()->name }}!</p>
    </div>

    <!-- Today's Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        <!-- Today's Sales -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Penjualan Hari Ini</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">Rp {{ number_format($todayTotal, 0, ',', '.') }}</p>
                    @if($todayComparison != 0)
                    <p class="text-xs mt-1 {{ $todayComparison >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $todayComparison >= 0 ? '↑' : '↓' }} {{ abs(round($todayComparison, 1)) }}% dari kemarin
                    </p>
                    @endif
                </div>
                <div class="w-12 h-12 bg-teal-100 dark:bg-teal-900/30 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Transactions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Transaksi Hari Ini</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $todayTransactions }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $todayItems }} item terjual</p>
                </div>
                <div class="w-12 h-12 bg-cyan-100 dark:bg-cyan-900/30 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Weekly Sales -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Penjualan Minggu Ini</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">Rp {{ number_format($weeklyTotal, 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $weeklyTransactions }} transaksi</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Revenue Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Grafik Penjualan 7 Hari Terakhir</h3>
            <div class="h-72">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Transaction Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Jumlah Transaksi</h3>
            <div class="h-72">
                <canvas id="transactionChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Bottom Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Low Stock Alert -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Stok Rendah</h3>
                    <span class="px-2 py-1 text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-full">
                        {{ $lowStockProducts->count() }} item
                    </span>
                </div>
            </div>
            <div class="p-6">
                @if($lowStockProducts->isEmpty())
                <p class="text-gray-500 dark:text-gray-400 text-sm text-center py-4">
                    Semua produk stoknya aman ✓
                </p>
                @else
                <div class="space-y-3">
                    @foreach($lowStockProducts as $product)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="w-2 h-2 rounded-full bg-red-500"></div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $product->category->name }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-red-600 dark:text-red-400">{{ $product->stock }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">min: {{ $product->min_stock }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        <!-- Top Products -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Produk Terlaris Hari Ini</h3>
            </div>
            <div class="p-6">
                @if($topProducts->isEmpty())
                <p class="text-gray-500 dark:text-gray-400 text-sm text-center py-4">
                    Belum ada penjualan hari ini
                </p>
                @else
                <div class="space-y-3">
                    @foreach($topProducts as $index => $product)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="w-6 h-6 flex items-center justify-center bg-teal-100 dark:bg-teal-900/30 rounded-full text-xs font-bold text-teal-600 dark:text-teal-400">
                                {{ $index + 1 }}
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $product->sale_items_count ?? 0 }} sold
                        </span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Penjualan Terbaru</h3>
            </div>
            <div class="p-6">
                @if($recentSales->isEmpty())
                <p class="text-gray-500 dark:text-gray-400 text-sm text-center py-4">
                    Belum ada penjualan
                </p>
                @else
                <div class="space-y-3">
                    @foreach($recentSales as $sale)
                    <a href="{{ route('sales.show', $sale) }}" class="block p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $sale->invoice_number }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $sale->created_at->diffForHumans() }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $sale->items->count() }} item</p>
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
