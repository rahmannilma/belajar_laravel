<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Material;
use Illuminate\Http\Request;

class MaterialController extends Controller
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

        $materials = Material::with(['branchStocks' => function ($q) use ($accessibleBranchIds) {
                $q->whereIn('branch_id', $accessibleBranchIds)->with('branch');
            }])
            ->when($request->search, function ($query, $search) {
                $query->search($search);
            })
            ->when($request->branch, function ($query, $branch) {
                $query->whereHas('branchStocks', function ($q) use ($branch) {
                    $q->where('branch_id', $branch);
                });
            })
            ->when($request->stock_status, function ($query, $status) use ($request) {
                $branchId = $request->branch ?: $request->input('branch');

                if ($branchId) {
                    if ($status === 'low') {
                        $query->whereHas('branchStocks', fn ($q) => $q->where('branch_id', $branchId)->whereColumn('stock', '<=', 'materials.min_stock'));
                    } elseif ($status === 'out') {
                        $query->whereHas('branchStocks', fn ($q) => $q->where('branch_id', $branchId)->where('stock', 0));
                    } elseif ($status === 'available') {
                        $query->whereHas('branchStocks', fn ($q) => $q->where('branch_id', $branchId)->where('stock', '>', 0));
                    }
                } else {
                    if ($status === 'low') {
                        $query->lowStock();
                    } elseif ($status === 'out') {
                        $query->where('stock', 0);
                    } elseif ($status === 'available') {
                        $query->where('stock', '>', 0);
                    }
                }
            })
            ->orderBy('name')
            ->paginate(15);

        $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        return view('materials.index', compact('materials', 'branches'));
    }

    public function create()
    {
        return view('materials.create');
    }

    public function store(Request $request)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'min_stock' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
        ]);

        $material = Material::create($request->all());

        return redirect()->route('materials.branch-stock', $material)->with('success', 'Bahan berhasil ditambahkan! Silakan isi stok cabin.');
    }

    public function show(Material $material)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();
        $hasAccess = $material->branchStocks()->whereIn('branch_id', $accessibleBranchIds)->exists();

        if (! $hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke bahan ini.');
        }

        $material->load('branchStocks.branch');

        return view('materials.show', compact('material'));
    }

    public function edit(Material $material)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();
        $hasAccess = $material->branchStocks()->whereIn('branch_id', $accessibleBranchIds)->exists();

        if (! $hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke bahan ini.');
        }

        $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        return view('materials.edit', compact('material', 'branches'));
    }

    public function update(Request $request, Material $material)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $hasAccess = $material->branchStocks()->whereIn('branch_id', $accessibleBranchIds)->exists();
        if (! $hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke bahan ini.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'stock' => 'nullable|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
        ]);

        $material->update($request->all());

        // Update or create branch stock if branch_id provided
        if ($request->filled('branch_id')) {
            if (! in_array($request->branch_id, $accessibleBranchIds)) {
                abort(403, 'Anda tidak memiliki akses ke cabang ini.');
            }
            $material->branchStocks()->updateOrCreate(
                ['branch_id' => $request->branch_id],
                ['stock' => $request->stock]
            );
        }

        return redirect()->route('materials.index')->with('success', 'Bahan berhasil diperbarui!');
    }

    public function destroy(Material $material)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();
        $hasAccess = $material->branchStocks()->whereIn('branch_id', $accessibleBranchIds)->exists();

        if (! $hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke bahan ini.');
        }

        // Check if material is used in any products
        if ($material->products()->exists()) {
            return back()->with('error', 'Bahan tidak dapat dihapus karena masih digunakan di produk!');
        }

        $material->delete();

        return redirect()->route('materials.index')->with('success', 'Bahan berhasil dihapus!');
    }

    public function branchStock(Request $request, Material $material)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $hasExistingStock = $material->branchStocks()->whereIn('branch_id', $accessibleBranchIds)->exists();

        // Allow access if user has existing stock OR if this is a new material (no branch stocks yet)
        if (! $hasExistingStock && $material->branchStocks()->count() > 0) {
            abort(403, 'Anda tidak memiliki akses ke bahan ini.');
        }

        $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        if ($request->ajax()) {
            return response()->json($material->branchStocks()->with('branch')->whereIn('branch_id', $accessibleBranchIds)->get());
        }

        return view('materials.branch-stock', compact('material', 'branches'));
    }

    public function updateBranchStock(Request $request, Material $material)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'stock' => 'required|integer|min:0',
        ]);

        if (! in_array($request->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        $material->branchStocks()->updateOrCreate(
            ['branch_id' => $request->branch_id],
            ['stock' => $request->stock]
        );

        // Update product stocks that use this material
        \App\Models\Product::updateStocksFromMaterial($material, $request->branch_id);

        return back()->with('success', 'Stok cabang berhasil diperbarui!');
    }

    public function bulkBranchStock(Request $request, Material $material)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $request->validate([
            'stocks' => 'required|array',
            'stocks.*.branch_id' => 'required|exists:branches,id',
            'stocks.*.stock' => 'required|numeric|min:0',
        ]);

        foreach ($request->stocks as $stockData) {
            if (! in_array($stockData['branch_id'], $accessibleBranchIds)) {
                abort(403, 'Anda tidak memiliki akses ke salah satu cabang.');
            }
            $material->branchStocks()->updateOrCreate(
                ['branch_id' => $stockData['branch_id']],
                ['stock' => $stockData['stock']]
            );
        }

        // Update product stocks that use this material
        \App\Models\Product::updateStocksFromMaterial($material);

        return redirect()->route('materials.index')->with('success', 'Stok semua cabang berhasil diperbarui!');
    }
}
