@extends('layouts.app')

@section('title', 'Dashboard')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update clock
    function updateClock() {
        const now = new Date();
        const timeElement = document.getElementById('current-time');
        const dateElement = document.getElementById('current-date');

        if (timeElement && dateElement) {
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            timeElement.textContent = `${hours}:${minutes}:${seconds}`;

            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            const dayName = days[now.getDay()];
            const day = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();
            dateElement.textContent = `${dayName}, ${day} ${monthName} ${year}`;
        }
    }

    updateClock();
    setInterval(updateClock, 1000);

    // Revenue Chart (30 hari)
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode(array_column($last30Days, 'date')) !!},
                datasets: [
                    {
                        label: 'Penjualan',
                        data: {!! json_encode(array_column($last30Days, 'sales')) !!},
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
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 20
                        }
                    },
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

// Transaction Chart (30 hari)
    const txCtx = document.getElementById('transactionChart');
    if (txCtx) {
        new Chart(txCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode(array_column($last30Days, 'date')) !!},
                datasets: [{
                    label: 'Jumlah Transaksi',
                    data: {!! json_encode(array_column($last30Days, 'count')) !!},
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
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 20
                        }
                    },
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

    // Branch mini charts (30 hari per cabang)
    @foreach($branchCharts as $index => $bc)
    const branchCtx{{ $index }} = document.getElementById('branchChart{{ $index }}');
    if (branchCtx{{ $index }}) {
        new Chart(branchCtx{{ $index }}, {
            type: 'line',
            data: {
                labels: {!! json_encode(array_column($bc['daily'], 'date')) !!},
                datasets: [{
                    label: 'Penjualan',
                    data: {!! json_encode(array_column($bc['daily'], 'sales')) !!},
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
                    pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return 'Rp ' + ctx.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) {
                                return 'Rp ' + (v / 1000).toFixed(0) + 'k';
                            }
                        }
                    }
                },
                interaction: { intersect: false }
            }
        });
    }
    @endforeach
});
</script>
@endpush

@section('content')
<div>
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                <p class="text-gray-500 dark:text-gray-400">Selamat datang, {{ auth()->user()->name }}!</p>
            </div>
            <div class="text-right">
                <div id="current-time" class="text-lg font-semibold text-gray-900 dark:text-white"></div>
                <div id="current-date" class="text-sm text-gray-500 dark:text-gray-400"></div>
            </div>
        </div>
    </div>

    <!-- Today's Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        <!-- Today's Sales -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Penjualan Hari Ini</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">Rp {{ number_format($todayTotal, 0, ',', '.') }}</p>
                    @if($todayComparison != 0)
                    <p class="text-[10px] mt-0.5 {{ $todayComparison >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $todayComparison >= 0 ? '↑' : '↓' }} {{ abs(round($todayComparison, 1)) }}% dari kemarin
                    </p>
                    @endif
                </div>
                <div class="w-8 h-8 bg-teal-100 dark:bg-teal-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Yesterday's Sales -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Penjualan Kemarin</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">Rp {{ number_format($yesterdaySales, 0, ',', '.') }}</p>
                    @if($todayComparison != 0)
                    <p class="text-[10px] mt-0.5 {{ $todayComparison >= 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ $todayComparison >= 0 ? '↑' : '↓' }} {{ abs(round($todayComparison, 1)) }}% dari hari ini
                    </p>
                    @endif
                </div>
                <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-orange-500 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Transactions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Transaksi Hari Ini</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ $todayTransactions }}</p>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $todayItems }} item terjual</p>
                </div>
                <div class="w-8 h-8 bg-cyan-100 dark:bg-cyan-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Weekly Sales -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Penjualan Minggu Ini</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">Rp {{ number_format($weeklyTotal, 0, ',', '.') }}</p>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $weeklyTransactions }} transaksi</p>
                </div>
                <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Monthly Profit -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Untung Bulan Ini</p>
                    <p class="text-lg font-bold {{ $monthlyProfit >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">Rp {{ number_format($monthlyProfit, 0, ',', '.') }}</p>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $monthlyTransactions }} transaksi</p>
                </div>
                <div class="w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row (30 Hari) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <!-- Revenue Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-3">Grafik Penjualan 30 Hari Terakhir</h3>
            <div class="h-56">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Transaction Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-3">Jumlah Transaksi 30 Hari Terakhir</h3>
            <div class="h-56">
                <canvas id="transactionChart"></canvas>
            </div>
        </div>
    </div>

<!-- Ringkasan Cabang (Tabel + Mini Chart) -->
    @if($branchSummaries->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6 overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">📊 Ringkasan Cabang (Hari Ini & Kemarin)</h3>
                <a href="{{ route('sales.by-branch') }}" class="text-xs text-teal-600 hover:text-teal-800 dark:text-teal-400">Lihat Detail →</a>
            </div>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($branchSummaries as $index => $data)
                @php
                    $brComp = $data['yesterday_sales'] > 0
                        ? (($data['total_sales'] - $data['yesterday_sales']) / $data['yesterday_sales']) * 100
                        : ($data['total_sales'] > 0 ? 100 : 0);
                @endphp
                <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $data['branch']->name }}</span>
                        <span class="text-[10px] text-gray-500 dark:text-gray-400">{{ $data['transaction_count'] }} transaksi</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="text-xs">
                            <p class="text-gray-500 dark:text-gray-400">Hari Ini</p>
                            <p class="font-semibold text-gray-900 dark:text-white">Rp {{ number_format($data['total_sales'], 0, ',', '.') }}</p>
                            <p class="text-[10px] text-gray-400">{{ $data['transaction_count'] }} transaksi</p>
                        </div>
                        <div class="text-xs">
                            <p class="text-gray-500 dark:text-gray-400">Kemarin</p>
                            <p class="font-semibold text-gray-900 dark:text-white">Rp {{ number_format($data['yesterday_sales'], 0, ',', '.') }}</p>
                            <p class="text-[10px] text-gray-400">{{ $data['yesterday_transactions'] }} transaksi</p>
                        </div>
                    </div>

                    @if($brComp != 0)
                    <p class="text-[10px] {{ $brComp >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $brComp >= 0 ? '↑' : '↓' }} {{ abs(round($brComp, 1)) }}% vs kemarin
                    </p>
                    @endif

                    <div class="h-20">
                        <canvas id="branchChart{{ $index }}"></canvas>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

     <!-- Penjualan 7 Hari Terakhir (Tabel) -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-4 overflow-hidden">
        <div class="p-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">📈 Penjualan 7 Hari Terakhir</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-3 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal</th>
                        <th class="px-3 py-1.5 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trans</th>
                        <th class="px-3 py-1.5 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jumlah</th>
                        <th class="px-3 py-1.5 text-right font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">vs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($last7Days as $day)
                    @php
                        $prevDay = $loop->index > 0 ? $last7Days[$loop->index - 1]['sales'] : null;
                        $change = $prevDay !== null && $prevDay > 0 ? (($day['sales'] - $prevDay) / $prevDay) * 100 : null;
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="px-3 py-1.5 whitespace-nowrap">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $day['label'] }}</div>
                            <div class="text-[9px] text-gray-500 dark:text-gray-400">{{ $day['date'] }}</div>
                        </td>
                        <td class="px-3 py-1.5 whitespace-nowrap text-right text-gray-900 dark:text-white">{{ $day['count'] }}</td>
                        <td class="px-3 py-1.5 whitespace-nowrap text-right font-semibold text-gray-900 dark:text-white">Rp {{ number_format($day['sales'], 0, ',', '.') }}</td>
                        <td class="px-3 py-1.5 whitespace-nowrap text-right">
                            @if($change !== null)
                                <span class="text-[9px] font-medium {{ $change >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $change >= 0 ? '↑' : '↓' }} {{ abs(round($change, 1)) }}%
                                </span>
                            @else
                                <span class="text-[9px] text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-700/50">
                    <tr class="font-semibold">
                        <td class="px-3 py-1.5 text-left text-gray-900 dark:text-white">Total</td>
                        <td class="px-3 py-1.5 text-right text-gray-900 dark:text-white">{{ $weeklyTransactions }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-900 dark:text-white">Rp {{ number_format($weeklyTotal, 0, ',', '.') }}</td>
                        <td class="px-3 py-1.5 text-right"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Bottom Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Low Stock Alert -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Stok Rendah</h3>
                    <span class="px-2 py-0.5 text-[10px] font-medium bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-full">
                        {{ $lowStockProducts->count() }} item
                    </span>
                </div>
            </div>
            <div class="p-4">
                @if($lowStockProducts->isEmpty())
                <p class="text-gray-500 dark:text-gray-400 text-sm text-center py-4">
                    Semua produk stoknya aman ✓
                </p>
                @else
                <div class="space-y-2">
                    @foreach($lowStockProducts as $product)
                    <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <div class="flex items-center space-x-2">
                            <div class="w-2 h-2 rounded-full bg-red-500"></div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->name }}</p>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400">{{ $product->category->name }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-red-600 dark:text-red-400">{{ intval($product->display_stock) }}</p>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400">min: {{ intval($product->min_stock) }}</p>
                            @if($product->hasMaterials())
                            <span class="text-[10px] text-green-500">(dari bahan)</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        <!-- Top Products -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Produk Terlaris Hari Ini</h3>
            </div>
            <div class="p-4">
                @if($topProducts->isEmpty())
                <p class="text-gray-500 dark:text-gray-400 text-sm text-center py-4">
                    Belum ada penjualan hari ini
                </p>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">#</th>
                                <th class="px-3 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">Produk</th>
                                <th class="px-3 py-1.5 text-right font-medium text-gray-500 dark:text-gray-400">Terjual</th>
                                <th class="px-3 py-1.5 text-right font-medium text-gray-500 dark:text-gray-400">Harga</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($topProducts as $index => $product)
                            <tr>
                                <td class="px-3 py-1.5">
                                    <span class="w-4 h-4 flex items-center justify-center bg-teal-100 dark:bg-teal-900/30 rounded-full text-[9px] font-bold text-teal-600 dark:text-teal-400">
                                        {{ $index + 1 }}
                                    </span>
                                </td>
                                <td class="px-3 py-1.5 text-gray-900 dark:text-white">{{ $product->name }}</td>
                                <td class="px-3 py-1.5 text-right text-gray-900 dark:text-white">{{ $product->sale_items_count ?? 0 }}</td>
                                <td class="px-3 py-1.5 text-right text-gray-900 dark:text-white">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Penjualan Terbaru</h3>
            </div>
            <div class="p-4">
                @if($recentSales->isEmpty())
                <p class="text-gray-500 dark:text-gray-400 text-sm text-center py-4">
                    Belum ada penjualan
                </p>
                @else
                <div class="space-y-2">
                    @foreach($recentSales as $sale)
                    <a href="{{ route('sales.show', $sale) }}" class="block p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $sale->invoice_number }}</p>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400">{{ $sale->created_at->diffForHumans() }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</p>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400">{{ $sale->items->count() }} item</p>
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