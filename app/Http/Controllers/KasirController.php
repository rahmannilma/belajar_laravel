<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KasirController extends Controller
{
    public function index()
    {
        $branchId = auth()->user()->branch_id;

        $products = Product::active()
            ->with('category', 'branchStocks', 'materials.branchStocks')
            ->orderBy('name')
            ->get()
            ->filter(function ($product) use ($branchId) {
                $branchStock = $product->branchStocks()->where('branch_id', $branchId)->first();
                if (! $branchStock) {
                    return false;
                }

                $productStock = $branchStock->stock;
                if ($productStock <= 0) {
                    return false;
                }

                foreach ($product->materials as $material) {
                    $materialBranchStock = $material->branchStocks()->where('branch_id', $branchId)->first();
                    if (! $materialBranchStock) {
                        return false;
                    }
                    $materialStock = $materialBranchStock->stock;
                    if ($materialStock <= 0) {
                        return false;
                    }
                }

                return true;
            })
            ->map(function ($product) use ($branchId) {
                $branchStock = $product->branchStocks()->where('branch_id', $branchId)->first();
                $product->display_stock = $branchStock?->stock ?? 0;
                $product->stock = $branchStock?->stock ?? 0;

                return $product;
            })
            ->values();

        $categories = \App\Models\Category::whereHas('products', function ($query) use ($branchId) {
            $query->whereHas('branchStocks', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        })->withCount('products')->get();

        $hasAnyStock = \App\Models\ProductBranchStock::where('branch_id', $branchId)->exists();

        $recentSales = Sale::with('user')
            ->where('branch_id', $branchId)
            ->whereDate('sale_date', today())
            ->orderBy('sale_date', 'desc')
            ->limit(10)
            ->get();

        return view('kasir.index', compact('products', 'categories', 'recentSales', 'hasAnyStock'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'payment_method' => ['required', Rule::in(['cash', 'qris', 'transfer'])],
            'customer_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $branchId = auth()->user()->branch_id;

        try {
            DB::beginTransaction();

            $invoiceNumber = 'INV-'.date('Ymd').'-'.strtoupper(Str::random(6));

            // Calculate totals
            $subtotal = 0;
            $totalCost = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $product = Product::with('branchStocks', 'materials.branchStocks')->findOrFail($item['product_id']);

                // Get branch stock
                $productStock = $product->getStockForBranch($branchId);

                // Check product stock
                if ($productStock < $item['quantity']) {
                    throw new \Exception("Stok produk {$product->name} tidak mencukupi! Stok tersedia: {$productStock}");
                }

                // Check material stock (recipe)
                foreach ($product->materials as $material) {
                    $requiredAmount = $material->pivot->quantity * $item['quantity'];
                    $materialStock = $material->getStockForBranch($branchId);
                    if ($materialStock < $requiredAmount) {
                        throw new \Exception("Stok bahan {$material->name} tidak mencukupi untuk membuat {$item['quantity']} {$product->name}! Stok tersedia: {$materialStock} {$material->unit}, Dibutuhkan: {$requiredAmount} {$material->unit}");
                    }
                }

                $price = $product->selling_price;
                $costPrice = $product->purchase_price;
                $quantity = $item['quantity'];
                $itemSubtotal = $price * $quantity;

                $subtotal += $itemSubtotal;
                $totalCost += $costPrice * $quantity;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $itemSubtotal,
                    'cost_price' => $costPrice,
                ];

                // Reduce product branch stock
                $branchStock = $product->branchStocks()->where('branch_id', $branchId)->first();
                if ($branchStock) {
                    $branchStock->decrement('stock', $quantity);
                } else {
                    $product->decrement('stock', $quantity);
                }

                // Reduce material branch stock
                foreach ($product->materials as $material) {
                    $materialBranchStock = $material->branchStocks()->where('branch_id', $branchId)->first();
                    if ($materialBranchStock) {
                        $materialBranchStock->decrement('stock', $material->pivot->quantity * $quantity);
                    } else {
                        $material->decrement('stock', $material->pivot->quantity * $quantity);
                    }
                }
            }

            $discountPercent = $request->discount_percent ?? 0;
            $discountAmount = $subtotal * ($discountPercent / 100);
            $totalAmount = $subtotal - $discountAmount;
            $profit = $totalAmount - $totalCost;

            // Create sale
            $sale = Sale::create([
                'user_id' => auth()->id(),
                'branch_id' => auth()->user()->branch_id,
                'invoice_number' => $invoiceNumber,
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'tax_percent' => 0,
                'tax_amount' => 0,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'profit' => $profit,
                'payment_method' => $request->payment_method,
                'customer_name' => $request->customer_name,
                'notes' => $request->notes,
            ]);

            // Create sale items
            foreach ($itemsData as $itemData) {
                $sale->items()->create($itemData);
            }

            DB::commit();

            // Load items for receipt
            $sale->load('items', 'user');

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil disimpan!',
                'sale' => $sale,
                'invoice_number' => $invoiceNumber,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Transaction error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function receipt(Sale $sale)
    {
        $sale->load('items', 'user');

        return view('kasir.receipt', compact('sale'));
    }

    public function printReceipt(Sale $sale)
    {
        $sale->load('items', 'user');

        return view('kasir.print-receipt', compact('sale'));
    }

    // API: Search products for kasir
    public function searchProducts(Request $request)
    {
        $term = $request->get('q', '');
        $categoryId = $request->get('category');
        $branchId = auth()->user()->branch_id;

        $query = Product::active()
            ->with('category', 'branchStocks', 'materials.branchStocks')
            ->search($term);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->limit(50)->get()
            ->filter(function ($product) use ($branchId) {
                $branchStock = $product->branchStocks()->where('branch_id', $branchId)->first();
                if (! $branchStock) {
                    return false;
                }

                $productStock = $branchStock->stock;
                if ($productStock <= 0) {
                    return false;
                }

                foreach ($product->materials as $material) {
                    $materialBranchStock = $material->branchStocks()->where('branch_id', $branchId)->first();
                    if (! $materialBranchStock) {
                        return false;
                    }
                    $materialStock = $materialBranchStock->stock;
                    if ($materialStock <= 0) {
                        return false;
                    }
                }

                return true;
            })
            ->map(function ($product) use ($branchId) {
                $branchStock = $product->branchStocks()->where('branch_id', $branchId)->first();
                $product->display_stock = $branchStock?->stock ?? 0;

                return $product;
            })
            ->take(20)
            ->values();

        return response()->json($products);
    }

    // API: Get product details
    public function getProduct(Product $product)
    {
        $branchId = auth()->user()->branch_id;

        if (! $product->is_active) {
            return response()->json(['error' => 'Produk tidak aktif'], 404);
        }

        $product->load('branchStocks');
        $productStock = $product->getStockForBranch($branchId);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'price' => $product->selling_price,
            'stock' => $productStock,
            'is_low_stock' => $product->isLowStock($branchId),
            'category' => $product->category->name,
        ]);
    }

    // API: Get popular products
    public function popularProducts(Request $request)
    {
        $days = $request->get('days', 7);
        $date = now()->subDays($days);
        $branchId = auth()->user()->branch_id;

        $products = Product::withSum([
            'saleItems' => function ($query) use ($date, $branchId) {
                $query->whereHas('sale', function ($q) use ($date, $branchId) {
                    $q->where('sale_date', '>=', $date)
                        ->where('branch_id', $branchId);
                });
            },
        ], 'quantity')
            ->having('sale_items_sum_quantity', '>', 0)
            ->orderBy('sale_items_sum_quantity', 'desc')
            ->limit(10)
            ->get()
            ->filter(function ($product) use ($branchId) {
                $branchStock = $product->branchStocks()->where('branch_id', $branchId)->first();
                if (! $branchStock) {
                    return false;
                }

                $productStock = $branchStock->stock;
                if ($productStock <= 0) {
                    return false;
                }

                foreach ($product->materials as $material) {
                    $materialBranchStock = $material->branchStocks()->where('branch_id', $branchId)->first();
                    if (! $materialBranchStock) {
                        return false;
                    }
                    $materialStock = $materialBranchStock->stock;
                    if ($materialStock <= 0) {
                        return false;
                    }
                }

                return true;
            })
            ->map(function ($product) use ($branchId) {
                $branchStock = $product->branchStocks()->where('branch_id', $branchId)->first();
                $product->display_stock = $branchStock?->stock ?? 0;

                return $product;
            });

        return response()->json($products);
    }

    // API: Product suggestions
    public function suggestions(Request $request)
    {
        $term = $request->get('q', '');
        $branchId = auth()->user()->branch_id;

        $products = Product::active()
            ->with('category', 'branchStocks', 'materials.branchStocks')
            ->search($term)
            ->limit(10)
            ->get()
            ->filter(function ($product) use ($branchId) {
                $branchStock = $product->branchStocks()->where('branch_id', $branchId)->first();
                if (! $branchStock) {
                    return false;
                }

                $productStock = $branchStock->stock;
                if ($productStock <= 0) {
                    return false;
                }

                foreach ($product->materials as $material) {
                    $materialBranchStock = $material->branchStocks()->where('branch_id', $branchId)->first();
                    if (! $materialBranchStock) {
                        return false;
                    }
                    $materialStock = $materialBranchStock->stock;
                    if ($materialStock <= 0) {
                        return false;
                    }
                }

                return true;
            })
            ->map(function ($product) use ($branchId) {
                $branchStock = $product->branchStocks()->where('branch_id', $branchId)->first();
                $product->display_stock = $branchStock?->stock ?? 0;
                $product->low_stock_warning = $branchStock ? ($branchStock->stock <= $product->min_stock) : true;

                return $product;
            });

        return response()->json($products);
    }
}
