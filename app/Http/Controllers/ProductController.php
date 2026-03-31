<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
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
        $products = Product::with('category')
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

        $categories = Category::orderBy('name')->get();

        return view('products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        return view('products.create', compact('categories'));
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
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        $data = $request->except('image');
        
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        // Generate SKU if not provided
        if (empty($data['sku'])) {
            $data['sku'] = 'SKU-' . strtoupper(Str::random(8));
        }

        Product::create($data);

        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan!');
    }

    public function show(Product $product)
    {
        $product->load('category', 'saleItems.sale');
        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $categories = Category::orderBy('name')->get();
        return view('products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => ['nullable', 'string', Rule::unique('products', 'sku')->ignore($product->id)],
            'barcode' => ['nullable', 'string', Rule::unique('products', 'barcode')->ignore($product->id)],
            'category_id' => 'required|exists:categories,id',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

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
            $data['sku'] = 'SKU-' . strtoupper(Str::random(8));
        }

        $product->update($data);

        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui!');
    }

    public function destroy(Product $product)
    {
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
        $request->validate([
            'type' => 'required|in:add,reduce',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($request->type === 'add') {
            $product->increment('stock', $request->quantity);
            $message = "Stok {$product->name} ditambah {$request->quantity} unit.";
        } else {
            if ($product->stock < $request->quantity) {
                return back()->with('error', 'Stok tidak mencukupi!');
            }
            $product->decrement('stock', $request->quantity);
            $message = "Stok {$product->name} dikurangi {$request->quantity} unit.";
        }

        return redirect()->route('products.index')->with('success', $message);
    }

    // API for product search (for kasir/autocomplete)
    public function search(Request $request)
    {
        $term = $request->get('q', '');
        
        $products = Product::active()
            ->inStock()
            ->search($term)
            ->with('category')
            ->limit(20)
            ->get();

        return response()->json($products);
    }

    // API for barcode lookup
    public function lookupBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);

        $product = Product::where('barcode', $request->barcode)
            ->orWhere('sku', $request->barcode)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Produk tidak ditemukan'], 404);
        }

        return response()->json($product);
    }
}
