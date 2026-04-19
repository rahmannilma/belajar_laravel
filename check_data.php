<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo App\Models\Branch::with('users')->get()->map(function ($b) {
    return [
        'id' => $b->id,
        'name' => $b->name,
        'users' => $b->users->map(function ($u) {
            return ['id' => $u->id, 'name' => $u->name, 'role' => $u->role, 'branch_id' => $u->branch_id];
        }),
    ];
})->toJson();
