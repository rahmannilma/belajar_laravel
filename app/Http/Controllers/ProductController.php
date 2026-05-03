<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = Auth::user();

            if (! $user || ! $user->canManageProducts()) {
                abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $products = Product::with('category', 'branchStocks.branch')
            ->whereHas('category.branch', function ($q) use ($accessibleBranchIds) {
                $q->whereIn('branches.id', $accessibleBranchIds);
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($request->category, function ($query, $category) {
                $query->where('category_id', $category);
            })
            ->when($request->branch, function ($query, $branch) {
                $query->whereHas('branchStocks', function ($q) use ($branch) {
                    $q->where('branch_id', $branch);
                });
            })
            ->when($request->material, function ($query, $material) {
                $query->whereHas('materials', function ($q) use ($material) {
                    $q->where('material_id', $material);
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
            ->when($request->has('low_stock'), function ($query) {
                $query->lowStock();
            })
            ->orderBy('name')
            ->paginate(15);

        $categories = Category::whereIn('branch_id', $accessibleBranchIds)->orderBy('name')->get();
        $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        $materials = Material::with(['branchStocks' => function ($q) use ($accessibleBranchIds) {
            $q->whereIn('branch_id', $accessibleBranchIds);
        }])
            ->whereHas('branchStocks', function ($q) use ($accessibleBranchIds) {
                $q->whereIn('branch_id', $accessibleBranchIds);
            })
            ->orderBy('name')
            ->get();

        return view('products.index', compact('products', 'categories', 'branches', 'materials'));
    }

    public function create()
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();
        $categories = Category::whereIn('branch_id', $accessibleBranchIds)->orderBy('name')->get();

        // Explicitly filter by owner for multi-tenant isolation
        $user = auth()->user();
        if ($user->isOwner()) {
            $branches = Branch::where('owner_id', $user->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        } else {
            $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();
        }

        // Debug: only show this in development
        // dd($accessibleBranchIds, $branches->pluck('owner_id', 'id'));

        // Load materials with their branch stocks for accessible branches
        $materials = Material::with(['branchStocks' => function ($q) use ($accessibleBranchIds) {
            $q->whereIn('branch_id', $accessibleBranchIds);
        }])
            ->whereHas('branchStocks', function ($q) use ($accessibleBranchIds) {
                $q->whereIn('branch_id', $accessibleBranchIds);
            })
            ->orderBy('name')
            ->get();

        // Group materials by each branch they are available in
        $materialsByBranch = [];
        foreach ($materials as $material) {
            foreach ($material->branchStocks as $stock) {
                $branchId = $stock->branch_id;
                $materialsByBranch[$branchId][] = [
                    'id' => $material->id,
                    'name' => $material->name,
                    'unit' => $material->unit,
                ];
            }
        }

        return view('products.create', compact('categories', 'branches', 'materialsByBranch'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|unique:products,sku',
            'barcode' => 'nullable|string|unique:products,barcode',
            'category_id' => 'required|exists:categories,id',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'min_stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        // Generate SKU if not provided
        if (empty($data['sku'])) {
            $data['sku'] = 'SKU-'.strtoupper(Str::random(8));
        }

        $product = Product::create($data);

        // Sync materials
        if ($request->has('materials')) {
            $syncData = [];
            foreach ($request->materials as $key => $pivot) {
                $materialId = is_numeric($key) ? ($pivot['material_id'] ?? $key) : $key;
                $quantity = $pivot['quantity'] ?? 0;
                if (! empty($quantity) && $quantity > 0) {
                    $syncData[$materialId] = ['quantity' => $quantity];
                }
            }
            $product->materials()->sync($syncData);
        }

        return redirect()->route('products.branch-stock', $product)->with('success', 'Produk berhasil ditambahkan! Silakan isi stok cabin.');
    }

    public function show(Product $product)
    {
        $product->load('category', 'saleItems.sale');

        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify product's category belongs to one of user's accessible branches
        $hasAccess = $product->category()->whereIn('branch_id', $accessibleBranchIds)->exists();
        if (! $hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke produk ini.');
        }

        $categories = Category::whereIn('branch_id', $accessibleBranchIds)->orderBy('name')->get();

        // Get materials from accessible branches (those that have stock in user's branches)
        $materials = Material::with('branchStocks')
            ->whereHas('branchStocks', function ($q) use ($accessibleBranchIds) {
                $q->whereIn('branch_id', $accessibleBranchIds);
            })
            ->orderBy('name')
            ->get();

        $product->load('materials');

        // Prepare ingredients data for the form
        $ingredientsData = [];
        foreach ($product->materials as $material) {
            $ingredientsData[] = [
                'material_id' => $material->id,
                'quantity' => $material->pivot->quantity,
            ];
        }

        // Get all materials for the branch selector
        $allMaterials = Material::with(['branchStocks' => function ($q) use ($accessibleBranchIds) {
            $q->whereIn('branch_id', $accessibleBranchIds);
        }])
            ->whereHas('branchStocks', function ($q) use ($accessibleBranchIds) {
                $q->whereIn('branch_id', $accessibleBranchIds);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($material) {
                return [
                    'id' => $material->id,
                    'name' => $material->name,
                    'unit' => $material->unit,
                ];
            })
            ->toArray();

        // Get product's branch ID (from category)
        $productBranchId = $product->category->branch_id ?? null;

        $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        return view('products.edit', compact(
            'product',
            'categories',
            'materials',
            'allMaterials',
            'ingredientsData',
            'productBranchId',
            'branches'
        ));
    }

    public function update(Request $request, Product $product)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify product's category belongs to accessible branches
        $hasAccess = $product->category()->whereIn('branch_id', $accessibleBranchIds)->exists();
        if (! $hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke produk ini.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => ['nullable', 'string', Rule::unique('products', 'sku')->ignore($product->id)],
            'barcode' => ['nullable', 'string', Rule::unique('products', 'barcode')->ignore($product->id)],
            'category_id' => 'required|exists:categories,id',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        // Prevent manual stock adjustment for products with materials
        $hasCurrentMaterials = $product->materials()->exists();
        // Check if request contains materials data (non-empty)
        $materialsInput = $request->input('materials', []);
        $hasIncomingMaterials = false;
        if (is_array($materialsInput)) {
            foreach ($materialsInput as $mat) {
                if (!empty($mat['material_id'])) {
                    $hasIncomingMaterials = true;
                    break;
                }
            }
        }

        // If product has materials (existing or incoming), reject stock changes
        if (($hasCurrentMaterials || $hasIncomingMaterials) && $request->has('stock')) {
            return back()->with('error', 'Produk ini menggunakan bahan baku. Stok tidak dapat diatur manual, silakan atur stok bahan baku di halaman Bahan.');
        }

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        // Generate SKU if not provided
        if (empty($data['sku'])) {
            $data['sku'] = 'SKU-'.strtoupper(Str::random(8));
        }

        $product->update($data);

        // Prevent stock update for products with materials
        if ($product->hasMaterials() && $request->has('stock')) {
            return back()->with('error', 'Produk ini menggunakan bahan baku. Stok tidak dapat diatur manual, silakan atur stok bahan baku.');
        }

        // Sync materials
        if ($request->has('materials')) {
            $syncData = [];
            foreach ($request->materials as $key => $pivot) {
                $materialId = is_numeric($key) ? ($pivot['material_id'] ?? $key) : $key;
                $quantity = $pivot['quantity'] ?? 0;
                if (! empty($quantity) && $quantity > 0) {
                    $syncData[$materialId] = ['quantity' => $quantity];
                }
            }
            $product->materials()->sync($syncData);
        } else {
            $product->materials()->detach();
        }

        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui!');
    }

    public function destroy(Product $product)
    {
        // Verify product's category belongs to accessible branches
        $accessibleBranchIds = $this->getAccessibleBranchIds();
        $hasAccess = $product->category()->whereIn('branch_id', $accessibleBranchIds)->exists();

        if (! $hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke produk ini.');
        }

        // Check if product has sale items
        if ($product->saleItems()->exists()) {
            return back()->with('error', 'Produk tidak dapat dihapus karena memiliki riwayat penjualan!');
        }

        // Delete image if exists
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Produk berhasil dihapus!');
    }

    public function printBarcode(Product $product)
    {
        return view('products.barcode', compact('product'));
    }

    public function printBarcodes(Request $request)
    {
        $request->validate([
            'products' => 'required|array|min:1',
            'products.*' => 'exists:products,id',
        ]);

        $products = Product::whereIn('id', $request->products)->get();

        return view('products.barcode', compact('products'));
    }

    public function stockAdjustment(Request $request, Product $product)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify product's category belongs to accessible branches
        $hasAccess = $product->category()->whereIn('branch_id', $accessibleBranchIds)->exists();
        if (! $hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke produk ini.');
        }

        // Prevent manual adjustment for products with materials
        if ($product->hasMaterials()) {
            return back()->with('error', 'Produk ini menggunakan bahan baku. Stok tidak dapat diatur manual, silakan atur stok bahan baku di halaman Bahan.');
        }

        $request->validate([
            'type' => 'required|in:add,reduce',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        // If branch_id specified, verify it's accessible
        if ($request->filled('branch_id') && ! in_array($request->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        if ($request->type === 'add') {
            if ($request->filled('branch_id')) {
                // Add stock to specific branch
                $product->branchStocks()->updateOrCreate(
                    ['branch_id' => $request->branch_id],
                    ['stock' => DB::raw('stock + '.$request->quantity)]
                );
            } else {
                // Add to global stock (main warehouse concept)
                $product->increment('stock', $request->quantity);
            }
            $message = "Stok {$product->name} ditambah {$request->quantity} unit.";
        } else {
            if ($request->filled('branch_id')) {
                // Reduce from specific branch
                $branchStock = $product->branchStocks()->where('branch_id', $request->branch_id)->first();
                if ($branchStock && $branchStock->stock < $request->quantity) {
                    return back()->with('error', 'Stok tidak mencukupi di cabang tersebut!');
                }
                if ($branchStock) {
                    $branchStock->decrement('stock', $request->quantity);
                }
            } else {
                // Reduce from global stock
                if ($product->stock < $request->quantity) {
                    return back()->with('error', 'Stok tidak mencukupi!');
                }
                $product->decrement('stock', $request->quantity);
            }
            $message = "Stok {$product->name} dikurangi {$request->quantity} unit.";
        }

        return redirect()->route('products.index')->with('success', $message);
    }

    public function branchStock(Request $request, Product $product)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify product's category belongs to accessible branches OR allow new products
        $hasExistingStock = $product->branchStocks()->whereIn('branch_id', $accessibleBranchIds)->exists();
        if (! $hasExistingStock && $product->branchStocks()->count() > 0) {
            $hasAccess = $product->category()->whereIn('branch_id', $accessibleBranchIds)->exists();
            if (! $hasAccess) {
                abort(403, 'Anda tidak memiliki akses ke produk ini.');
            }
        }

        $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        if ($request->ajax()) {
            return response()->json($product->branchStocks()->with('branch')->whereIn('branch_id', $accessibleBranchIds)->get());
        }

        return view('products.branch-stock', compact('product', 'branches'));
    }

    public function updateBranchStock(Request $request, Product $product)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify product's category belongs to accessible branches
        $hasAccess = $product->category()->whereIn('branch_id', $accessibleBranchIds)->exists();
        if (! $hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke produk ini.');
        }

        // Prevent manual adjustment for products with materials
        if ($product->hasMaterials()) {
            return back()->with('error', 'Produk ini menggunakan bahan baku. Stok tidak dapat diatur manual, silakan atur stok bahan baku di halaman Bahan.');
        }

        // Verify branch belongs to user
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'stock' => 'required|integer|min:0',
        ]);

        if (! in_array($request->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        $product->branchStocks()->updateOrCreate(
            ['branch_id' => $request->branch_id],
            ['stock' => $request->stock]
        );

        return back()->with('success', 'Stok cabang berhasil diperbarui!');
    }

    public function bulkBranchStock(Request $request, Product $product)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Prevent manual bulk adjustment for products with materials
        if ($product->hasMaterials()) {
            return back()->with('error', 'Produk ini menggunakan bahan baku. Stok tidak dapat diatur manual, silakan atur stok bahan baku di halaman Bahan.');
        }

        $request->validate([
            'stocks' => 'required|array',
            'stocks.*.branch_id' => 'required|exists:branches,id',
            'stocks.*.stock' => 'required|numeric|min:0',
        ]);

        foreach ($request->stocks as $stockData) {
            if (! in_array($stockData['branch_id'], $accessibleBranchIds)) {
                abort(403, 'Anda tidak memiliki akses ke salah satu cabang.');
            }

            $product->branchStocks()->updateOrCreate(
                ['branch_id' => $stockData['branch_id']],
                ['stock' => $stockData['stock']]
            );
        }

        return redirect()->route('products.index')->with('success', 'Stok semua cabang berhasil diperbarui!');
    }

    // API for product search (for kasir/autocomplete)
    public function search(Request $request)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $products = Product::active()
            ->whereHas('category.branch', function ($q) use ($accessibleBranchIds) {
                $q->whereIn('branches.id', $accessibleBranchIds);
            })
            ->inStock()
            ->search($request->get('q', ''))
            ->with('category')
            ->limit(20)
            ->get();

        return response()->json($products);
    }

    // API for barcode lookup
    public function lookupBarcode(Request $request)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $request->validate([
            'barcode' => 'required|string',
        ]);

        $product = Product::whereHas('category.branch', function ($q) use ($accessibleBranchIds) {
            $q->whereIn('branches.id', $accessibleBranchIds);
        })
            ->where(function ($q) use ($request) {
                $q->where('barcode', $request->barcode)
                    ->orWhere('sku', $request->barcode);
            })
            ->first();

        if (! $product) {
            return response()->json(['error' => 'Produk tidak ditemukan'], 404);
        }

        return response()->json($product);
    }
}
