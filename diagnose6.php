<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\User;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\Product;
use Carbon\Carbon;

$user = User::where('role', 'owner')->first();
$accessibleBranchIds = Branch::where('owner_id', $user->id)->pluck('id')->toArray();

$today = Carbon::today();
$yesterday = Carbon::yesterday();
$startOfWeek = Carbon::now()->startOfWeek();
$endOfWeek = Carbon::now()->endOfWeek();
$startOfMonth = Carbon::now()->startOfMonth();
$endOfMonth = Carbon::now()->endOfMonth();

$todayTotal = Sale::today()->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('total_amount');
$todayProfit = Sale::today()->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('profit');
$todayTransactions = Sale::today()->whereIn('branch_id', $accessibleBranchIds)->completed()->count();
$todayItems = Sale::today()->whereIn('branch_id', $accessibleBranchIds)->completed()->withCount('items')->get()->sum('items_count');

$weeklyTotal = Sale::whereBetween('sale_date', [$startOfWeek, $endOfWeek])->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('total_amount');
$weeklyProfit = Sale::whereBetween('sale_date', [$startOfWeek, $endOfWeek])->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('profit');
$weeklyTransactions = Sale::whereBetween('sale_date', [$startOfWeek, $endOfWeek])->whereIn('branch_id', $accessibleBranchIds)->completed()->count();

$monthlyTotal = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth])->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('total_amount');
$monthlyProfit = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth])->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('profit');
$monthlyTransactions = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth])->whereIn('branch_id', $accessibleBranchIds)->completed()->count();

$branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

$branchSummaries = $branches->map(function ($branch) use ($today, $yesterday) {
    return [
        'branch' => $branch,
        'total_sales' => Sale::where('branch_id', $branch->id)->whereDate('sale_date', $today)->completed()->sum('total_amount'),
        'total_profit' => Sale::where('branch_id', $branch->id)->whereDate('sale_date', $today)->completed()->sum('profit'),
        'transaction_count' => Sale::where('branch_id', $branch->id)->whereDate('sale_date', $today)->completed()->count(),
        'yesterday_sales' => Sale::where('branch_id', $branch->id)->whereDate('sale_date', $yesterday)->completed()->sum('total_amount'),
        'yesterday_profit' => Sale::where('branch_id', $branch->id)->whereDate('sale_date', $yesterday)->completed()->sum('profit'),
        'yesterday_transactions' => Sale::where('branch_id', $branch->id)->whereDate('sale_date', $yesterday)->completed()->count(),
    ];
});

$branchData = $branches->map(function ($branch) use ($today) {
    return [
        'branch' => $branch,
        'today_transaction_count' => Sale::where('branch_id', $branch->id)->whereDate('sale_date', $today)->completed()->count(),
        'today_sales' => Sale::where('branch_id', $branch->id)->whereDate('sale_date', $today)->completed()->sum('total_amount'),
        'transaction_count' => Sale::where('branch_id', $branch->id)->completed()->count(),
        'total_sales' => Sale::where('branch_id', $branch->id)->completed()->sum('total_amount'),
    ];
});

$branchCharts = $branches->map(function ($branch) {
    $dailyData = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = Carbon::today()->subDays($i);
        $dailyData[] = [
            'date' => $date->isoFormat('ddd, DD MMM'),
            'sales' => Sale::where('branch_id', $branch->id)->whereDate('sale_date', $date)->completed()->sum('total_amount'),
        ];
    }
    return ['branch' => $branch, 'daily' => $dailyData];
});

$last30Days = [];
for ($i = 29; $i >= 0; $i--) {
    $date = Carbon::today()->subDays($i);
    $last30Days[] = [
        'date' => $date->isoFormat('ddd, DD MMM'),
        'label' => $date->isoFormat('ddd'),
        'sales' => Sale::whereDate('sale_date', $date)->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('total_amount'),
        'profit' => Sale::whereDate('sale_date', $date)->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('profit'),
        'count' => Sale::whereDate('sale_date', $date)->whereIn('branch_id', $accessibleBranchIds)->completed()->count(),
    ];
}

$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = Carbon::today()->subDays($i);
    $last7Days[] = [
        'date' => $date->format('Y-m-d'),
        'label' => $date->isoFormat('ddd, DD MMM'),
        'sales' => Sale::whereDate('sale_date', $date)->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('total_amount'),
        'count' => Sale::whereDate('sale_date', $date)->whereIn('branch_id', $accessibleBranchIds)->completed()->count(),
    ];
}

$topProducts = Product::select('products.*')
    ->whereHas('saleItems.sale', function ($q) use ($accessibleBranchIds) {
        $q->whereDate('sale_date', Carbon::today())->whereIn('branch_id', $accessibleBranchIds)->completed();
    })->withCount('saleItems')->orderBy('sale_items_count', 'desc')->limit(5)->get();

$popularProducts = Product::select('products.*')
    ->whereHas('saleItems.sale', function ($q) use ($accessibleBranchIds) {
        $q->whereMonth('sale_date', Carbon::now()->month)->whereYear('sale_date', Carbon::now()->year)
            ->whereIn('branch_id', $accessibleBranchIds)->completed();
    })->withCount('saleItems')->orderBy('sale_items_count', 'desc')->limit(10)->get();

$categorySales = \App\Models\Category::with([
    'products.saleItems' => function ($query) use ($accessibleBranchIds) {
        $query->whereHas('sale', function ($q) use ($accessibleBranchIds) {
            $q->whereDate('sale_date', Carbon::today())->whereIn('branch_id', $accessibleBranchIds)->completed();
        });
    },
])->get()->map(function ($category) {
    $total = $category->products->sum(fn($p) => $p->saleItems->sum('subtotal'));
    $category->products_sum_subtotal = $total;
    return $category;
})->filter(fn($cat) => $cat->products_sum_subtotal > 0);

$recentSales = Sale::with('user', 'items')->whereIn('branch_id', $accessibleBranchIds)->completed()->orderBy('sale_date', 'desc')->limit(5)->get();

$yesterdaySalesVal = Sale::whereDate('sale_date', $yesterday)->whereIn('branch_id', $accessibleBranchIds)->completed()->sum('total_amount');
$yesterdayTransactions = Sale::whereDate('sale_date', $yesterday)->whereIn('branch_id', $accessibleBranchIds)->completed()->count();
$todayComparison = $yesterdaySalesVal > 0 ? (($todayTotal - $yesterdaySalesVal) / $yesterdaySalesVal) * 100 : ($todayTotal > 0 ? 100 : 0);

$lowStockProducts = Product::with('category', 'branchStocks', 'materials')
    ->whereHas('branchStocks', fn($q) => $q->whereIn('branch_id', $accessibleBranchIds))
    ->orderBy('stock', 'asc')->limit(10)->get()
    ->map(function ($p) use ($accessibleBranchIds) {
        $bid = $accessibleBranchIds[0] ?? null;
        $p->display_stock = $bid ? $p->getStockForBranch($bid) : $p->stock;
        return $p;
    })->filter(fn($p) => $p->display_stock <= $p->min_stock)->values();

// Use the correct variable names matching the controller
$yesterdaySales = $yesterdaySalesVal;

// Render
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
echo "Rendered: " . strlen($html) . " bytes\n";

$checks = [
    'Chart.js CDN' => 'cdn.jsdelivr.net/npm/chart.js',
    'revenueChart canvas' => 'id="revenueChart"',
    'transactionChart canvas' => 'id="transactionChart"',
    'new Chart(' => 'new Chart(',
    'Branch chart' => 'branchChart',
    'last30Days data' => 'array_column($last30Days',
];
foreach ($checks as $name => $needle) {
    echo "$name: " . (strpos($html, $needle) !== false ? "PRESENT" : "MISSING") . "\n";
}

// Extract script content between @push('scripts') and @endpush
if (preg_match('/@push\(\'scripts\'\)(.*?)@endpush/s', $html, $m)) {
    $scriptContent = trim($m[1]);
    echo "\nScript block length: " . strlen($scriptContent) . " chars\n";

    // Check if JSON encoded data looks valid
    if (preg_match('/labels:\s*(\{!! .*? !!})/s', $scriptContent, $lblMatch)) {
        echo "Labels expression: " . substr($lblMatch[1], 0, 100) . "\n";
    }
    if (preg_match('/new Chart\(ctx.*?\);/s', $scriptContent, $chartMatch)) {
        echo "Revenue chart code length: " . strlen($chartMatch[0]) . "\n";
    }
} else {
    echo "\nWARNING: @push('scripts') not found in rendered output!\n";
}