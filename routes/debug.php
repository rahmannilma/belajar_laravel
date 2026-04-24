<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::get('/check-products', function () {
    $products = Product::with('category')->get();

    $data = [];
    foreach ($products as $product) {
        $data[] = [
            'id' => $product->id,
            'name' => $product->name,
            'category_id' => $product->category_id,
            'category_name' => $product->category->name ?? 'N/A',
            'category_branch_id' => $product->category->branch_id ?? 'N/A',
        ];
    }

    // Get all categories with branch IDs
    $categories = Category::all(['id', 'name', 'branch_id']);

    return response()->json([
        'products' => $data,
        'categories' => $categories,
        'total_products' => count($data),
    ]);
})->middleware('auth');
