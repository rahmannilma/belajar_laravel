<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\Sale;
use App\Models\Product;
use Carbon\Carbon;

$accessibleBranchIds = [1, 2];
$today = Carbon::today();

$todaySales = Sale::today()->whereIn('branch_id', $accessibleBranchIds)->completed();
$todayTotal = $todaySales->sum('total_amount');
$todayProfit = $todaySales->sum('profit');
$todayTransactions = $todaySales->count();
$todayItems = $todaySales->withCount('items')->get()->sum('items_count');

$startOfWeek = Carbon::now()->startOfWeek();
$endOfWeek = Carbon::now()->endOfWeek();
$weeklySales = Sale::whereBetween('sale_date', [$startOfWeek, $endOfWeek])
    ->whereIn('branch_id', $accessibleBranchIds)->completed();
$weeklyTotal = $weeklySales->sum('total_amount');
$weeklyProfit = $weeklySales->sum('profit');
$weeklyTransactions = $weeklySales->count();

$startOfMonth = Carbon::now()->startOfMonth();
$endOfMonth = Carbon::now()->endOfMonth();
$monthlySales = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth])
    ->whereIn('branch_id', $accessibleBranchIds)->completed();
$monthlyTotal = $monthlySales->sum('total_amount');
$monthlyProfit = $monthlySales->sum('profit');
$monthlyTransactions = $monthlySales->count();

$branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();
$yesterday = Carbon::yesterday();

$branchSummaries = $branches->map(function ($branch) use ($today, $yesterday) {
    $todaySales = Sale::where('branch_id', $branch->id)->whereDate('sale_date', $today)->completed();
    $yesterdaySales = Sale::where('branch_id', $branch->id)->whereDate('sale_date', $yesterday)->completed();
    return [
        'branch' => $branch,
        'total_sales' => (clone $todaySales)->sum('total_amount'),
        'total_profit' => (clone $todaySales)->sum('profit'),
        'transaction_count' => (clone $todaySales)->count(),
        'yesterday_sales' => (clone $yesterdaySales)->sum('total_amount'),
        'yesterday_profit' => (clone $yesterdaySales)->sum('profit'),
        'yesterday_transactions' => (clone $yesterdaySales)->count(),
    ];
});

$branchData = $branches->map(function ($branch) use ($today) {
    $todaySales = Sale::where('branch_id', $branch->id)->whereDate('sale_date', $today)->completed();
    $todayTransactionCount = $todaySales->count();
    $todaySalesTotal = $todaySales->sum('total_amount');
    $allSales = Sale::where('branch_id', $branch->id)->completed();
    $transactionCount = $allSales->count();
    $totalSales = $allSales->sum('total_amount');
    return [
        'branch' => $branch,
        'today_transaction_count' => $todayTransactionCount,
        'today_sales' => $todaySalesTotal,
        'transaction_count' => $transactionCount,
        'total_sales' => $totalSales,
    ];
});

$branchCharts = $branches->map(function ($branch) {
    $dailyData = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = Carbon::today()->subDays($i);
        $sales = Sale::where('branch_id', $branch->id)
            ->whereDate('sale_date', $date)->completed()->sum('total_amount');
        $dailyData[] = [
            'date' => Carbon::now()->subDays($i)->isoFormat('ddd, DD MMM'),
            'sales' => $sales,
        ];
    }
    return ['branch' => $branch, 'daily' => $dailyData];
});

$last30Days = [];
for ($i = 29; $i >= 0; $i--) {
    $date = Carbon::today()->subDays($i);
    $sales = Sale::whereDate('sale_date', $date)
        ->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('total_amount');
    $profit = Sale::whereDate('sale_date', $date)
        ->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('profit');
    $count = Sale::whereDate('sale_date', $date)
        ->whereIn('branch_id', $accessibleBranchIds)->completed()->count();
    $last30Days[] = [
        'date' => Carbon::now()->subDays($i)->isoFormat('ddd, DD MMM'),
        'label' => Carbon::now()->subDays($i)->isoFormat('ddd'),
        'sales' => $sales,
        'profit' => $profit,
        'count' => $count,
    ];
}

$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = Carbon::today()->subDays($i);
    $sales = Sale::whereDate('sale_date', $date)
        ->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('total_amount');
    $count = Sale::whereDate('sale_date', $date)
        ->whereIn('branch_id', $accessibleBranchIds)->completed()->count();
    $last7Days[] = [
        'date' => $date->format('Y-m-d'),
        'label' => Carbon::now()->subDays($i)->isoFormat('ddd, DD MMM'),
        'sales' => $sales,
        'count' => $count,
    ];
}

$topProducts = Product::select('products.*')
    ->whereHas('saleItems.sale', function ($q) use ($accessibleBranchIds) {
        $q->whereDate('sale_date', Carbon::today())
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('status', 'completed');
    })
    ->withCount('saleItems')
    ->orderBy('sale_items_count', 'desc')
    ->limit(5)
    ->get();

$popularProducts = Product::select('products.*')
    ->whereHas('saleItems.sale', function ($q) use ($accessibleBranchIds) {
        $q->whereMonth('sale_date', Carbon::now()->month)
            ->whereYear('sale_date', Carbon::now()->year)
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('status', 'completed');
    })
    ->withCount('saleItems')
    ->orderBy('sale_items_count', 'desc')
    ->limit(10)
    ->get();

$categorySales = \App\Models\Category::with([
    'products.saleItems' => function ($query) use ($accessibleBranchIds) {
        $query->whereHas('sale', function ($q) use ($accessibleBranchIds) {
            $q->whereDate('sale_date', Carbon::today())
                ->whereIn('branch_id', $accessibleBranchIds)
                ->completed();
        });
    },
])->get()->map(function ($category) {
    $totalSales = $category->products->sum(function ($product) {
        return $product->saleItems->sum('subtotal');
    });
    $category->products_sum_subtotal = $totalSales;
    return $category;
})->filter(function ($cat) {
    return $cat->products_sum_subtotal > 0;
});

$recentSales = Sale::with('user', 'items')
    ->whereIn('branch_id', $accessibleBranchIds)
    ->completed()
    ->orderBy('sale_date', 'desc')
    ->limit(5)
    ->get();

$yesterdaySales = Sale::whereDate('sale_date', Carbon::yesterday())
    ->whereIn('branch_id', $accessibleBranchIds)
    ->completed()
    ->sum('total_amount');
$yesterdayTransactions = Sale::whereDate('sale_date', Carbon::yesterday())
    ->whereIn('branch_id', $accessibleBranchIds)
    ->completed()
    ->count();
$todayComparison = $yesterdaySales > 0
    ? (($todayTotal - $yesterdaySales) / $yesterdaySales) * 100
    : ($todayTotal > 0 ? 100 : 0);

$lowStockProducts = Product::with('category', 'branchStocks', 'materials')
    ->whereHas('branchStocks', function ($q) use ($accessibleBranchIds) {
        $q->whereIn('branch_id', $accessibleBranchIds);
    })
    ->orderBy('stock', 'asc')
    ->limit(10)
    ->get()
    ->map(function ($product) use ($accessibleBranchIds) {
        $branchId = $accessibleBranchIds[0] ?? null;
        $stock = $branchId ? $product->getStockForBranch($branchId) : $product->stock;
        $product->display_stock = $stock;
        return $product;
    })
    ->filter(function ($product) {
        return $product->display_stock <= $product->min_stock;
    })
    ->values();

echo "All data compiled OK\n";

try {
    $html = view('dashboard', compact(
        'todayTotal', 'todayProfit', 'yesterdaySales', 'yesterdayTransactions',
        'todayTransactions', 'todayItems',
        'weeklyTotal', 'weeklyProfit', 'weeklyTransactions',
        'monthlyTotal', 'monthlyProfit', 'monthlyTransactions',
        'lowStockProducts',
        'last30Days', 'last7Days',
        'topProducts', 'popularProducts',
        'categorySales', 'recentSales',
        'todayComparison',
        'branchSummaries', 'branchCharts', 'branchData', 'branches'
    ))->render();

    file_put_contents('/tmp/dashboard_output.html', $html);

    if (strpos($html, 'cdn.jsdelivr.net/npm/chart.js') !== false) {
        echo "Chart.js CDN: PRESENT\n";
    } else {
        echo "Chart.js CDN: MISSING\n";
    }

    if (strpos($html, 'revenueChart') !== false) {
        echo "revenueChart canvas: PRESENT\n";
    } else {
        echo "revenueChart canvas: MISSING\n";
    }

    if (strpos($html, 'new Chart(') !== false) {
        echo "Chart initialization: PRESENT\n";
    } else {
        echo "Chart initialization: MISSING\n";
    }

    // Look for the last30Days JSON in rendered output
    if (preg_match('/"Penjualan"/', $html)) {
        echo "Chart label 'Penjualan': PRESENT\n";
    }

    // Check if last7Days table data is present
    if (preg_match('/Jumlah Transaksi/', $html)) {
        echo "Transaction table header: PRESENT\n";
    }

    echo "Rendered HTML: " . strlen($html) . " bytes saved to /tmp/dashboard_output.html\n";
} catch (Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}