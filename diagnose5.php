<?php
// Reproduce exactly what the DashboardController does
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Set up the auth context for the test
use App\Models\User;
use App\Models\Branch;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Get admin user
$user = User::where('role', 'owner')->first();
echo "User: " . ($user ? $user->name . " (role: " . $user->role . ", branch_id: " . ($user->branch_id ?? 'null') . ")\n" : "NOT FOUND\n");

// Simulate getAccessibleBranchIds for owner
$accessibleBranchIds = Branch::where('owner_id', $user->id)->pluck('id')->toArray();
echo "Accessible branch IDs: " . implode(', ', $accessibleBranchIds) . "\n";

// Now run the exact same queries as DashboardController
$today = Carbon::today();
$startOfWeek = Carbon::now()->startOfWeek();
$endOfWeek = Carbon::now()->endOfWeek();
$startOfMonth = Carbon::now()->startOfMonth();
$endOfMonth = Carbon::now()->endOfMonth();

echo "Today: $today\n";
echo "Start of week: $startOfWeek\n";
echo "End of week: $endOfWeek\n";

// Today's statistics
$todaySalesQ = Sale::today()->whereIn('branch_id', $accessibleBranchIds)->completed();
$todayTotal = $todaySalesQ->sum('total_amount');
$todayProfit = $todaySalesQ->sum('profit');
$todayTransactions = $todaySalesQ->count();
$todayItems = $todaySalesQ->withCount('items')->get()->sum('items_count');

echo "\n--- Today ---\n";
echo "todayTotal: $todayTotal\n";
echo "todayProfit: $todayProfit\n";
echo "todayTransactions: $todayTransactions\n";
echo "todayItems: $todayItems\n";

// Weekly
$weeklySalesQ = Sale::whereBetween('sale_date', [$startOfWeek, $endOfWeek])
    ->whereIn('branch_id', $accessibleBranchIds)->completed();
$weeklyTotal = $weeklySalesQ->sum('total_amount');
$weeklyProfit = $weeklySalesQ->sum('profit');
$weeklyTransactions = $weeklySalesQ->count();

echo "\n--- Weekly ---\n";
echo "weeklyTotal: $weeklyTotal\n";
echo "weeklyProfit: $weeklyProfit\n";
echo "weeklyTransactions: $weeklyTransactions\n";

// Monthly
$monthlySalesQ = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth])
    ->whereIn('branch_id', $accessibleBranchIds)->completed();
$monthlyTotal = $monthlySalesQ->sum('total_amount');
$monthlyProfit = $monthlySalesQ->sum('profit');
$monthlyTransactions = $monthlySalesQ->count();

echo "\n--- Monthly ---\n";
echo "monthlyTotal: $monthlyTotal\n";
echo "monthlyProfit: $monthlyProfit\n";
echo "monthlyTransactions: $monthlyTransactions\n";

// Branch summaries
$branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();
echo "\nBranches found: " . $branches->count() . "\n";
foreach ($branches as $b) {
    echo "  Branch: {$b->name} (id: {$b->id})\n";
}

$yesterday = Carbon::yesterday();
$branchSummaries = $branches->map(function ($branch) use ($today, $yesterday) {
    $todaySales = Sale::where('branch_id', $branch->id)->whereDate('sale_date', $today)->completed();
    $yesterdaySales = Sale::where('branch_id', $branch->id)->whereDate('sale_date', $yesterday)->completed();
    $r = [
        'branch' => $branch,
        'total_sales' => (clone $todaySales)->sum('total_amount'),
        'total_profit' => (clone $todaySales)->sum('profit'),
        'transaction_count' => (clone $todaySales)->count(),
        'yesterday_sales' => (clone $yesterdaySales)->sum('total_amount'),
        'yesterday_profit' => (clone $yesterdaySales)->sum('profit'),
        'yesterday_transactions' => (clone $yesterdaySales)->count(),
    ];
    echo "  Branch {$branch->name}: today={$r['total_sales']}, yesterday={$r['yesterday_sales']}\n";
    return $r;
});

// last30Days
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

// Branch charts
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

// last7Days
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

// Low stock products
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

// Top products
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

// Popular products
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

// Category sales
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

// Recent sales
$recentSales = Sale::with('user', 'items')
    ->whereIn('branch_id', $accessibleBranchIds)
    ->completed()
    ->orderBy('sale_date', 'desc')
    ->limit(5)
    ->get();

$yesterdaySales = (clone $yesterdaySalesQ ?? Sale::whereDate('sale_date', $yesterday)
    ->whereIn('branch_id', $accessibleBranchIds)
    ->completed())->sum('total_amount');
$yesterdayTransactions = Sale::whereDate('sale_date', $yesterday)
    ->whereIn('branch_id', $accessibleBranchIds)
    ->completed()
    ->count();
$todayComparison = $yesterdaySales > 0
    ? (($todayTotal - $yesterdaySales) / $yesterdaySales) * 100
    : ($todayTotal > 0 ? 100 : 0);

// Branch data
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

// Render view
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
echo "\n==== RENDER SUCCESSFUL ====\n";
echo "HTML size: " . strlen($html) . " bytes\n";

// Check chart elements
$checks = [
    'Chart.js CDN' => 'cdn.jsdelivr.net/npm/chart.js',
    'revenueChart canvas' => 'id="revenueChart"',
    'transactionChart canvas' => 'id="transactionChart"',
    'Chart init new Chart(' => 'new Chart(',
    'Branch chart div' => 'branchChart',
    'Chart labels with dates' => 'Rabu, 08 Mei',
    'Last 30 days label' => 'Grafik Penjualan 30 Hari',
];

foreach ($checks as $name => $needle) {
    echo "$name: " . (strpos($html, $needle) !== false ? "PRESENT" : "MISSING") . "\n";
}

// Extract and check script content
if (preg_match('/@push\(\'scripts\'\)(.*?)@endpush/s', $html, $matches)) {
    $scriptBlock = $matches[1];
    echo "\nScript block size: " . strlen($scriptBlock) . " chars\n";

    // Check if the JSON data is properly formed
    if (preg_match('/labels:\s*\{(!! json_encode.*?)\}/s', $scriptBlock, $l)) {
        echo "Chart labels expression found\n";
    }
    if (preg_match('/"Penjualan"/', $html)) {
        echo "'Penjualan' label found in dataset\n";
    }
} else {
    echo "WARNING: @push('scripts') block not found in rendered HTML!\n";
}