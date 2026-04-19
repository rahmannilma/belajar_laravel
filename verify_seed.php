<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$branches = App\Models\Branch::with('owner')->get();
echo 'Branches with owners:'.PHP_EOL.PHP_EOL;
foreach ($branches as $b) {
    echo "ID: {$b->id}".PHP_EOL;
    echo "Name: {$b->name}".PHP_EOL;
    echo "Owner ID: {$b->owner_id}".PHP_EOL;
    echo 'Owner: '.($b->owner?->name ?? 'none').PHP_EOL;
    echo str_repeat('-', 40).PHP_EOL;
}
echo PHP_EOL.'Total branches: '.$branches->count().PHP_EOL;

echo PHP_EOL.'Users:'.PHP_EOL.PHP_EOL;
$users = App\Models\User::all();
foreach ($users as $u) {
    echo "ID: {$u->id}, Name: {$u->name}, Role: {$u->role}, Branch ID: {$u->branch_id}".PHP_EOL;
}
