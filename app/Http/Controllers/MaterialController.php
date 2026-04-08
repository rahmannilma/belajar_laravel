<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->canManageProducts()) {
                abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $materials = Material::when($request->search, function ($query, $search) {
                $query->search($search);
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

        return view('materials.index', compact('materials'));
    }

    public function create()
    {
        return view('materials.create');
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

        Material::create($request->all());

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
}
