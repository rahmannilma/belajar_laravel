<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Http\Request;

class BranchController extends Controller
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

    public function index()
    {
        $user = auth()->user();

        if ($user->isOwner()) {
            // Owner only sees their own branches
            $branches = Branch::where('owner_id', $user->id)
                ->withCount(['users', 'sales'])
                ->orderBy('name')
                ->get();
        } else {
            // Cashier/staff sees branches they are assigned to
            $branches = Branch::where('id', $user->branch_id)
                ->withCount(['users', 'sales'])
                ->orderBy('name')
                ->get();
        }

        return view('branches.index', compact('branches'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
        ]);

        $data = $request->all();
        if (auth()->user()->isOwner()) {
            $data['owner_id'] = auth()->id();
        }

        Branch::create($data);

        return redirect()->route('branches.index')->with('success', 'Cabang berhasil ditambahkan!');
    }

    public function update(Request $request, Branch $branch)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
        ]);

        $branch->update($request->all());

        return redirect()->route('branches.index')->with('success', 'Cabang berhasil diperbarui!');
    }

    public function destroy(Branch $branch)
    {
        if ($branch->users()->exists()) {
            return back()->with('error', 'Cabang tidak dapat dihapus karena masih memiliki pengguna!');
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'Cabang berhasil dihapus!');
    }

    public function toggleActive(Request $request, Branch $branch)
    {
        $branch->update(['is_active' => ! $branch->is_active]);

        $status = $branch->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Cabang berhasil {$status}!");
    }

    public function stockOverview(Request $request, ?Branch $branch = null)
    {
        $user = auth()->user();

        if ($user->isOwner()) {
            // Owner only sees their own branches in the selector
            $branchesQuery = Branch::where('owner_id', $user->id)->where('is_active', true);
            $branches = $branchesQuery->orderBy('name')->get();

            // If specific branch requested, verify ownership
            if ($branch && $branch->owner_id !== $user->id) {
                abort(403, 'Anda tidak memiliki akses ke cabang ini.');
            }
        } else {
            // Cashier only sees their assigned branch
            $branchesQuery = Branch::where('id', $user->branch_id)->where('is_active', true);
            $branches = $branchesQuery->orderBy('name')->get();

            // If specific branch requested, verify it's the assigned branch
            if ($branch && $branch->id !== $user->branch_id) {
                abort(403, 'Anda tidak memiliki akses ke cabang ini.');
            }
        }

        $selectedBranch = $branch ?? $branches->first();

        if (! $selectedBranch) {
            return redirect()->route('branches.index')->with('error', 'Tidak ada cabang yang aktif!');
        }

        $products = Product::with('category', 'branchStocks')
            ->whereHas('branchStocks', function ($query) use ($selectedBranch) {
                $query->where('branch_id', $selectedBranch->id);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($product) use ($selectedBranch) {
                $branchStock = $product->branchStocks()->where('branch_id', $selectedBranch->id)->first();
                $product->cabin_stock = $branchStock?->stock ?? 0;

                return $product;
            });

        $materials = Material::with('branchStocks')
            ->whereHas('branchStocks', function ($query) use ($selectedBranch) {
                $query->where('branch_id', $selectedBranch->id);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($material) use ($selectedBranch) {
                $branchStock = $material->branchStocks()->where('branch_id', $selectedBranch->id)->first();
                $material->cabin_stock = $branchStock?->stock ?? 0;

                return $material;
            });

        $lowStockProducts = $products->filter(fn ($p) => $p->cabin_stock <= $p->min_stock);
        $lowStockMaterials = $materials->filter(fn ($m) => $m->cabin_stock <= $m->min_stock);

        return view('branches.stock-overview', compact('branches', 'selectedBranch', 'products', 'materials', 'lowStockProducts', 'lowStockMaterials'));
    }

    public function stockApi(Branch $branch)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify branch belongs to user
        if (! in_array($branch->id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        $products = Product::with('category', 'branchStocks', 'materials')
            ->whereHas('branchStocks', function ($query) use ($branch) {
                $query->where('branch_id', $branch->id);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($product) use ($branch) {
                $branchStock = $product->branchStocks()->where('branch_id', $branch->id)->first();
                $product->cabin_stock = $branchStock?->stock ?? 0;
                $product->has_materials = $product->materials->isNotEmpty();

                return $product;
            });

        $materials = Material::with('branchStocks')
            ->whereHas('branchStocks', function ($query) use ($branch) {
                $query->where('branch_id', $branch->id);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($material) use ($branch) {
                $branchStock = $material->branchStocks()->where('branch_id', $branch->id)->first();
                $material->cabin_stock = $branchStock?->stock ?? 0;

                return $material;
            });

        return response()->json([
            'products' => $products,
            'materials' => $materials,
        ]);
    }

    public function adjustStock(Request $request, Branch $branch, $item)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify branch belongs to user
        if (! in_array($branch->id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        $request->validate([
            'type' => 'required|in:add,reduce',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ]);

        // Strip prefix if present
        $itemId = ltrim($item, 'pm');

        // Determine type and find item
        $isProduct = str_starts_with($item, 'p');
        $isMaterial = str_starts_with($item, 'm');

        $product = null;
        $material = null;

        if ($isProduct) {
            $product = Product::find($itemId);
            // Verify product is accessible
            if ($product && ! $product->branchStocks()->whereIn('branch_id', $accessibleBranchIds)->exists()) {
                abort(403, 'Anda tidak memiliki akses ke produk ini.');
            }
        } elseif ($isMaterial) {
            $material = Material::find($itemId);
            // Verify material is accessible
            if ($material && ! $material->branchStocks()->whereIn('branch_id', $accessibleBranchIds)->exists()) {
                abort(403, 'Anda tidak memiliki akses ke bahan ini.');
            }
        } else {
            // Fallback - check both
            $product = Product::find($item);
            $material = Material::find($item);
        }

        if ($product) {
            // Check if product has materials - prevent manual adjustment
            if ($product->hasMaterials()) {
                return back()->with('error', 'Produk ini menggunakan bahan baku. Stok tidak dapat diatur manual, silakan atur stok bahan baku.');
            }

            $branchStock = $product->branchStocks()->firstOrCreate(
                ['branch_id' => $branch->id],
                ['stock' => 0]
            );

            $oldStock = $branchStock->stock;
            $adjustment = $request->type === 'add' ? $request->quantity : -$request->quantity;
            $newStock = $oldStock + $adjustment;

            if ($newStock < 0) {
                return back()->with('error', 'Stok tidak dapat kurang dari 0!');
            }

            $branchStock->update(['stock' => $newStock]);

            $action = $request->type === 'add' ? 'ditambah' : 'dikurangi';

            return back()->with('success', "Stok produk '{$product->name}' berhasil {$action} dari {$oldStock} menjadi {$newStock}. ".($request->reason ? "Keterangan: {$request->reason}" : ''));
        }

        if ($material) {
            $branchStock = $material->branchStocks()->firstOrCreate(
                ['branch_id' => $branch->id],
                ['stock' => 0]
            );

            $oldStock = $branchStock->stock;
            $adjustment = $request->type === 'add' ? $request->quantity : -$request->quantity;
            $newStock = $oldStock + $adjustment;

            if ($newStock < 0) {
                return back()->with('error', 'Stok tidak dapat kurang dari 0!');
            }

            $branchStock->update(['stock' => $newStock]);

            // Update product stocks that depend on this material
            \App\Models\Product::updateStocksFromMaterial($material, $branch->id);

            $action = $request->type === 'add' ? 'ditambah' : 'dikurangi';

            return back()->with('success', "Stok bahan '{$material->name}' berhasil {$action} dari {$oldStock} menjadi {$newStock}. ".($request->reason ? "Keterangan: {$request->reason}" : ''));
        }

        return back()->with('error', 'Item tidak ditemukan!');
    }

    public function removeStock(Branch $branch, $item)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify branch belongs to user
        if (! in_array($branch->id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        // Strip prefix if present
        $itemId = ltrim($item, 'pm');

        if (str_starts_with($item, 'p')) {
            $product = Product::find($itemId);
            if ($product) {
                // Verify product belongs to accessible branches
                if ($product->branchStocks()->whereIn('branch_id', $accessibleBranchIds)->exists()) {
                    // Prevent removal if product has materials
                    if ($product->hasMaterials()) {
                        return response()->json(['error' => 'Produk ini menggunakan bahan baku. Stok tidak dapat dihapus manual.'], 403);
                    }
                    $product->branchStocks()->where('branch_id', $branch->id)->delete();

                    return response()->json(['success' => true]);
                }
            }
        } elseif (str_starts_with($item, 'm')) {
            $material = Material::find($itemId);
            if ($material) {
                // Verify material belongs to accessible branches
                if ($material->branchStocks()->whereIn('branch_id', $accessibleBranchIds)->exists()) {
                    $material->branchStocks()->where('branch_id', $branch->id)->delete();

                    return response()->json(['success' => true]);
                }
            }
        }

        return response()->json(['error' => 'Item tidak ditemukan'], 404);
    }
}
