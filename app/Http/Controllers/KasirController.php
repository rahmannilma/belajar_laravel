<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KasirController extends Controller
{
    public function index()
    {
        $products = Product::active()
            ->with('category')
            ->orderBy('name')
            ->get();

        $categories = \App\Models\Category::withCount('products')->get();

        return view('kasir.index', compact('products', 'categories'));
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

        try {
            DB::beginTransaction();

            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            // Calculate totals
            $subtotal = 0;
            $totalCost = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Check stock
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok {$product->name} tidak mencukupi! Stok tersedia: {$product->stock}");
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

                // Reduce stock
                $product->decrement('stock', $quantity);
            }

            $discountPercent = $request->discount_percent ?? 0;
            $discountAmount = $subtotal * ($discountPercent / 100);
            $taxableAmount = $subtotal - $discountAmount;
            $taxPercent = 11;
            $taxAmount = $taxableAmount * ($taxPercent / 100);
            $totalAmount = $taxableAmount + $taxAmount;
            $profit = $totalAmount - $totalCost - $discountAmount;

            // Create sale
            $sale = Sale::create([
                'user_id' => auth()->id(),
                'invoice_number' => $invoiceNumber,
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
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
            Log::error('Transaction error: ' . $e->getMessage());
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

        $query = Product::active()
            ->inStock()
            ->search($term);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->with('category')
            ->limit(20)
            ->get();

        return response()->json($products);
    }

    // API: Get product details
    public function getProduct(Product $product)
    {
        if (!$product->is_active) {
            return response()->json(['error' => 'Produk tidak aktif'], 404);
        }

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'price' => $product->selling_price,
            'stock' => $product->stock,
            'is_low_stock' => $product->isLowStock(),
            'category' => $product->category->name,
        ]);
    }

    // API: Get popular products
    public function popularProducts(Request $request)
    {
        $days = $request->get('days', 7);
        $date = now()->subDays($days);

        $products = Product::withSum([
            'saleItems' => function ($query) use ($date) {
                $query->whereHas('sale', function ($q) use ($date) {
                    $q->where('sale_date', '>=', $date);
                });
            }
        ], 'quantity')
        ->having('sale_items_sum_quantity', '>', 0)
        ->orderBy('sale_items_sum_quantity', 'desc')
        ->limit(10)
        ->get();

        return response()->json($products);
    }

    // API: Product suggestions
    public function suggestions(Request $request)
    {
        $term = $request->get('q', '');
        
        $products = Product::active()
            ->inStock()
            ->search($term)
            ->with('category')
            ->limit(10)
            ->get();

        // Add low stock warning for each product
        $products->each(function ($product) {
            $product->low_stock_warning = $product->isLowStock();
        });

        return response()->json($products);
    }
}
