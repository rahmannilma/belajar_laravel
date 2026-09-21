<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Database\Seeder;

class BranchStockSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::where('is_active', true)->get();

        if ($branches->isEmpty()) {
            $branches = Branch::create([
                'name' => 'Toko Utama',
                'address' => 'Jl. Raya Utama No. 1',
                'phone' => '081234567890',
                'city' => 'Jakarta',
                'is_active' => true,
            ]);
        }

        $branches = Branch::where('is_active', true)->get();

        foreach (Product::with('category.branch')->get() as $product) {
            $ownerId = $product->category?->branch?->owner_id;
            $productBranches = $ownerId 
                ? $branches->where('owner_id', $ownerId)
                : ($product->category?->branch_id ? $branches->where('id', $product->category->branch_id) : $branches);

            foreach ($productBranches as $branch) {
                $stock = $product->stock > 0
                    ? rand(ceil($product->stock * 0.3), $product->stock)
                    : 0;

                \App\Models\ProductBranchStock::create([
                    'product_id' => $product->id,
                    'branch_id' => $branch->id,
                    'stock' => $stock,
                ]);
            }
        }

        foreach (Material::all() as $material) {
            foreach ($branches as $branch) {
                $stock = $material->stock > 0
                    ? rand(ceil($material->stock * 0.3), $material->stock)
                    : 0;

                \App\Models\MaterialBranchStock::create([
                    'material_id' => $material->id,
                    'branch_id' => $branch->id,
                    'stock' => $stock,
                ]);
            }
        }

        $this->command->info('Branch stock data seeded successfully!');
    }
}
