<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Render the dashboard view and capture output
use App\Models\Branch;
use App\Models\Sale;
use Carbon\Carbon;

$accessibleBranchIds = [1, 2];
$today = Carbon::today();

$todaySales = Sale::today()->whereIn('branch_id', $accessibleBranchIds)->completed();
$todayTotal = $todaySales->sum('total_amount');
$yesterdaySales = Sale::whereDate('sale_date', Carbon::yesterday())
    ->whereIn('branch_id', $accessibleBranchIds)
    ->completed()
    ->sum('total_amount');
$todayComparison = $yesterdaySales > 0
    ? (($todayTotal - $yesterdaySales) / $yesterdaySales) * 100
    : ($todayTotal > 0 ? 100 : 0);

$startOfWeek = Carbon::now()->startOfWeek();
$endOfWeek = Carbon::now()->endOfWeek();
$weeklySales = Sale::whereBetween('sale_date', [$startOfWeek, $endOfWeek])
    ->whereIn('branch_id', $accessibleBranchIds)
    ->completed();
$weeklyTotal = $weeklySales->sum('total_amount');
$weeklyTransactions = $weeklySales->count();

$last30Days = [];
for ($i = 29; $i >= 0; $i--) {
    $date = Carbon::today()->subDays($i);
    $sales = Sale::whereDate('sale_date', $date)
        ->whereIn('branch_id', $accessibleBranchIds)
        ->completed()
        ->sum('total_amount');
    $count = Sale::whereDate('sale_date', $date)
        ->whereIn('branch_id', $accessibleBranchIds)
        ->completed()
        ->count();
    $last30Days[] = [
        'date' => Carbon::now()->subDays($i)->isoFormat('ddd, DD MMM'),
        'sales' => $sales,
        'count' => $count,
    ];
}

// Check last7Days
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = Carbon::today()->subDays($i);
    $sales = Sale::whereDate('sale_date', $date)
        ->whereIn('branch_id', $accessibleBranchIds)
        ->completed()
        ->sum('total_amount');
    $count = Sale::whereDate('sale_date', $date)
        ->whereIn('branch_id', $accessibleBranchIds)
        ->completed()
        ->count();
    $last7Days[] = [
        'date' => $date->format('Y-m-d'),
        'label' => Carbon::now()->subDays($i)->isoFormat('ddd, DD MMM'),
        'sales' => $sales,
        'count' => $count,
    ];
}

echo "todayTotal: $todayTotal\n";
echo "yesterdaySales: $yesterdaySales\n";
echo "todayComparison: $todayComparison\n";
echo "weeklyTotal: $weeklyTotal\n";
echo "weeklyTransactions: $weeklyTransactions\n";

echo "\n--- Labels that will be injected into JS ---\n";
$labels = array_column($last30Days, 'date');
$data = array_column($last30Days, 'sales');
$counts = array_column($last30Days, 'count');
echo "Labels: " . json_encode($labels) . "\n";
echo "Data: " . json_encode($data) . "\n";
echo "Counts: " . json_encode($counts) . "\n";

echo "\n--- Check for any issue ---\n";
echo "json_last_error after labels: " . json_last_error_msg() . "\n";

// Check if any value is non-numeric
foreach ($data as $i => $val) {
    if (!is_numeric($val)) {
        echo "Non-numeric sales value at index $i: " . var_export($val, true) . "\n";
    }
}

// Render view
echo "\n--- Rendering View ---\n";
$branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();
$todaySalesQuery = Sale::whereIn('branch_id', $accessibleBranchIds)->whereDate('sale_date', $today)->completed();
$todayTransactions = $todaySalesQuery->count();
$todayItems = $todaySalesQuery->withCount('items')->get()->sum('items_count');
$monthlySales = Sale::whereMonth('sale_date', now()->month)->whereYear('sale_date', now()->year)
    ->whereIn('branch_id', $accessibleBranchIds)->completed();

echo "View data compiled successfully.\n";

try {
    $html = view('dashboard', compact(
        'todayTotal', 'yesterdaySales', 'todayTransactions', 'todayItems',
        'weeklyTotal', 'weeklyTransactions', 'last30Days', 'last7Days',
        'branches', 'todayComparison'
    ))->render();
    echo "View rendered successfully.\n";

    // Check if Chart.js CDN is in the HTML
    if (strpos($html, 'chart.js') !== false) {
        echo "Chart.js CDN found in HTML.\n";
    } else {
        echo "WARNING: Chart.js CDN NOT found!\n";
    }

    // Check if canvas elements are present
    if (strpos($html, 'revenueChart') !== false) {
        echo "revenueChart canvas found.\n";
    } else {
        echo "WARNING: revenueChart canvas NOT found!\n";
    }

    // Extract and check the chart script
    if (preg_match('/new Chart\(.*?\)\s*;/s', $html, $matches)) {
        echo "Chart initialization found.\n";
    }

    // Check for any error-like content
    if (preg_match('/error|exception|Error/i', $html, $errMatches)) {
        echo "Potential errors in HTML output.\n";
    }

    // Write full HTML for inspection
    file_put_contents('/tmp/dashboard_output.html', $html);
    echo "\nFull HTML written to /tmp/dashboard_output.html\n";
} catch (Exception $e) {
    echo "ERROR rendering view: " . $e->getMessage() . "\n";
}