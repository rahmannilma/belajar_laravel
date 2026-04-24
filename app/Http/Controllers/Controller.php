<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    protected function getBranchId()
    {
        return auth()->user()->branch_id ?? null;
    }

    protected function getBranchIdOrAll()
    {
        $branchId = auth()->user()->branch_id ?? null;

        if (! $branchId) {
            return null;
        }

        return $branchId;
    }

    /**
     * Get branches accessible to current user
     * - Owner: all their owned branches
     * - Cashier: their assigned branch only
     */
    protected function getAccessibleBranches()
    {
        $user = auth()->user();

        if ($user->isOwner()) {
            // Owner only sees their own branches - explicit filter
            return Branch::where('owner_id', $user->id)->orderBy('name')->get();
        }

        // Cashier/staff: only their assigned branch
        if ($user->branch_id) {
            return Branch::where('id', $user->branch_id)->where('is_active', true)->orderBy('name')->get();
        }

        // Fallback: return empty collection if no branch assigned
        return collect();
    }

    /**
     * Get accessible branch IDs for queries
     */
    protected function getAccessibleBranchIds()
    {
        return $this->getAccessibleBranches()->pluck('id')->toArray();
    }
}
