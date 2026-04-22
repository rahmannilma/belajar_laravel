<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::first();
echo "Email: {$user->email}\n";
echo "Role: {$user->role}\n";
echo 'isOwner: '.($user->isOwner() ? 'true' : 'false')."\n";
echo 'canManageProducts: '.($user->canManageProducts() ? 'true' : 'false')."\n";
