<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Sales count: " . DB::table('sales')->count() . PHP_EOL;
echo "Today sales: " . DB::table('sales')->whereDate('sale_date', today())->count() . PHP_EOL;
echo "Yesterday sales: " . DB::table('sales')->whereDate('sale_date', today()->subDay())->count() . PHP_EOL;

$recent = DB::table('sales')->orderBy('sale_date', 'desc')->limit(5)->get();
foreach ($recent as $r) {
    echo $r->invoice_number . ' - ' . $r->sale_date . ' - ' . $r->total_amount . ' (' . $r->status . ')' . PHP_EOL;
}

echo PHP_EOL . "Branches: " . DB::table('branches')->count() . PHP_EOL;
echo "Users: " . DB::table('users')->count() . PHP_EOL;
echo "Products: " . DB::table('products')->count() . PHP_EOL;
echo "Sale Items: " . DB::table('sale_items')->count() . PHP_EOL;