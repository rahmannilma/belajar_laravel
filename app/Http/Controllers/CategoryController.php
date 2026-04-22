<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->canManageProducts()) {
                abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();
        $branchId = $this->getBranchId();

        $categories = Category::whereIn('branch_id', $accessibleBranchIds)
            ->withCount('products')
            ->when($request->branch, function ($query, $branch) use ($accessibleBranchIds) {
                // Ensure branch is accessible
                if (in_array($branch, $accessibleBranchIds)) {
                    $query->where('branch_id', $branch);
                }
            })
            ->orderBy('name')
            ->get();

        $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        return view('categories.index', compact('categories', 'branches'));
    }

    public function store(Request $request)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Auto-assign branch_id for owner if not provided
        $branchId = $request->branch_id ?? $accessibleBranchIds[0] ?? null;

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        // Verify branch belongs to user
        if ($branchId && ! in_array($branchId, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        $slug = Str::slug($request->name);

        // Check if slug exists for this branch
        if (Category::where('slug', $slug)->where('branch_id', $branchId)->exists()) {
            $slug .= '-'.time();
        }

        Category::create([
            'name' => $request->name,
            'slug' => $slug,
            'branch_id' => $branchId,
            'description' => $request->description,
        ]);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil ditambahkan!');
    }

    public function update(Request $request, Category $category)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify category belongs to accessible branch
        if (! in_array($category->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke kategori ini.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $slug = Str::slug($request->name);

        // Check if slug exists for this branch (excluding current)
        if (Category::where('slug', $slug)
            ->where('branch_id', $category->branch_id)
            ->where('id', '!=', $category->id)
            ->exists()) {
            $slug .= '-'.time();
        }

        $category->update([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
        ]);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil diperbarui!');
    }

    public function destroy(Category $category)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify category belongs to accessible branch
        if (! in_array($category->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke kategori ini.');
        }

        if ($category->products()->exists()) {
            return back()->with('error', 'Kategori tidak dapat dihapus karena memiliki produk!');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil dihapus!');
    }
}
