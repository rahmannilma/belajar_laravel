<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Simulate what the controller does
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$accessibleBranchIds = [1, 2];
$today = Carbon::today();

// Test last30Days query
$last30Days = [];
for ($i = 29; $i >= 0; $i--) {
    $date = Carbon::today()->subDays($i);
    $sales = DB::table('sales')
        ->whereDate('sale_date', $date)
        ->whereIn('branch_id', $accessibleBranchIds)
        ->where('status', 'completed')
        ->sum('total_amount');
    $count = DB::table('sales')
        ->whereDate('sale_date', $date)
        ->whereIn('branch_id', $accessibleBranchIds)
        ->where('status', 'completed')
        ->count();

    $last30Days[] = [
        'date' => $date->format('d M'),
        'sales' => $sales,
        'count' => $count,
    ];
}

echo "Last 30 days data:\n";
foreach ($last30Days as $d) {
    if ($d['sales'] > 0 || $d['count'] > 0) {
        echo "  {$d['date']}: sales={$d['sales']}, count={$d['count']}\n";
    }
}

// Test json_encode
$labels = array_column($last30Days, 'date');
$salesData = array_column($last30Days, 'sales');
$counts = array_column($last30Days, 'count');

echo "\nLabels JSON: " . substr(json_encode($labels), 0, 200) . "\n";
echo "Sales JSON: " . substr(json_encode($salesData), 0, 200) . "\n";
echo "Counts JSON: " . substr(json_encode($counts), 0, 200) . "\n";

// Check for any non-UTF8 values
foreach ($last30Days as $i => $d) {
    $json = json_encode($d);
    if ($json === false) {
        echo "JSON encode error at index $i: " . json_last_error_msg() . "\n";
    }
}