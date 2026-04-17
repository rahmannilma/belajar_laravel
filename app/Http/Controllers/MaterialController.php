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
        $materials = Material::with('branchStocks.branch')
            ->when($request->search, function ($query, $search) {
                $query->search($search);
            })
            ->when($request->branch, function ($query, $branch) {
                $query->whereHas('branchStocks', function ($q) use ($branch) {
                    $q->where('branch_id', $branch);
                });
            })
            ->when($request->stock_status, function ($query, $status) {
                if ($status === 'low') {
                    $query->lowStock();
                } elseif ($status === 'out') {
                    $query->where('stock', 0);
                } elseif ($status === 'available') {
                    $query->where('stock', '>', 0);
                }
            })
            ->orderBy('name')
            ->paginate(15);

        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('materials.index', compact('materials', 'branches'));
    }

    public function create()
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return view('materials.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'stock' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
        ]);

        $material = Material::create($request->all());

        // Save branch stock if selected
        if ($request->filled('branch_id')) {
            $material->branchStocks()->create([
                'branch_id' => $request->branch_id,
                'stock' => $request->stock,
            ]);
        }

        return redirect()->route('materials.index')->with('success', 'Bahan berhasil ditambahkan!');
    }

    public function edit(Material $material)
    {
        return view('materials.edit', compact('material'));
    }

    public function update(Request $request, Material $material)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'stock' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
        ]);

        $material->update($request->all());

        return redirect()->route('materials.index')->with('success', 'Bahan berhasil diperbarui!');
    }

    public function destroy(Material $material)
    {
        if ($material->products()->exists()) {
            return back()->with('error', 'Bahan tidak dapat dihapus karena digunakan oleh beberapa produk!');
        }

        $material->delete();

        return redirect()->route('materials.index')->with('success', 'Bahan berhasil dihapus!');
    }

    public function adjustStock(Request $request, Material $material)
    {
        $request->validate([
            'type' => 'required|in:add,reduce',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($request->type === 'add') {
            $material->increment('stock', $request->quantity);
            $message = "Stok {$material->name} ditambah {$request->quantity} {$material->unit}.";
        } else {
            if ($material->stock < $request->quantity) {
                return back()->with('error', 'Stok tidak mencukupi!');
            }
            $material->decrement('stock', $request->quantity);
            $message = "Stok {$material->name} dikurangi {$request->quantity} {$material->unit}.";
        }

        return redirect()->route('materials.index')->with('success', $message);
    }

    public function branchStock(Request $request, Material $material)
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        if ($request->ajax()) {
            return response()->json($material->branchStocks()->with('branch')->get());
        }

        return view('materials.branch-stock', compact('material', 'branches'));
    }

    public function updateBranchStock(Request $request, Material $material)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'stock' => 'required|numeric|min:0',
        ]);

        $material->branchStocks()->updateOrCreate(
            ['branch_id' => $request->branch_id],
            ['stock' => $request->stock]
        );

        return back()->with('success', 'Stok cabang berhasil diperbarui!');
    }

    public function bulkBranchStock(Request $request, Material $material)
    {
        $request->validate([
            'stocks' => 'required|array',
            'stocks.*.branch_id' => 'required|exists:branches,id',
            'stocks.*.stock' => 'required|numeric|min:0',
        ]);

        foreach ($request->stocks as $stockData) {
            $material->branchStocks()->updateOrCreate(
                ['branch_id' => $stockData['branch_id']],
                ['stock' => $stockData['stock']]
            );
        }

        return back()->with('success', 'Stok semua cabang berhasil diperbarui!');
    }
}
