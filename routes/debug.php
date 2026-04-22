<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/debug-check', function () {
    $user = User::first();
    if (! $user) {
        return response('No user', 404);
    }

    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role,
        'branch_id' => $user->branch_id,
        'isOwner' => $user->isOwner(),
        'canManageProducts' => $user->canManageProducts(),
        'accessibleBranchIds' => app(\App\Http\Controllers\Controller::class)->getAccessibleBranchIds(),
    ]);
})->middleware('auth');
