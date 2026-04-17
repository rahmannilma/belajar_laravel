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
        $branches = Branch::withCount(['users', 'sales'])->orderBy('name')->get();

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

        Branch::create($request->all());

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
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
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
        $products = Product::with('category', 'branchStocks')
            ->whereHas('branchStocks', function ($query) use ($branch) {
                $query->where('branch_id', $branch->id);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($product) use ($branch) {
                $branchStock = $product->branchStocks()->where('branch_id', $branch->id)->first();
                $product->cabin_stock = $branchStock?->stock ?? 0;

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
        } elseif ($isMaterial) {
            $material = Material::find($itemId);
        } else {
            // Fallback - check both
            $product = Product::find($item);
            $material = Material::find($item);
        }

        if ($product) {
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

            $action = $request->type === 'add' ? 'ditambah' : 'dikurangi';

            return back()->with('success', "Stok bahan '{$material->name}' berhasil {$action} dari {$oldStock} menjadi {$newStock}. ".($request->reason ? "Keterangan: {$request->reason}" : ''));
        }

        return back()->with('error', 'Item tidak ditemukan!');
    }

    public function removeStock(Branch $branch, $item)
    {
        // Strip prefix if present
        $itemId = ltrim($item, 'pm');

        if (str_starts_with($item, 'p')) {
            $product = Product::find($itemId);
            if ($product) {
                $product->branchStocks()->where('branch_id', $branch->id)->delete();

                return response()->json(['success' => true]);
            }
        } elseif (str_starts_with($item, 'm')) {
            $material = Material::find($itemId);
            if ($material) {
                $material->branchStocks()->where('branch_id', $branch->id)->delete();

                return response()->json(['success' => true]);
            }
        }

        return response()->json(['error' => 'Item tidak ditemukan'], 404);
    }
}
