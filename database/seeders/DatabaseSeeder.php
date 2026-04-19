<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create branch first
        $branch = \App\Models\Branch::create([
            'name' => 'Toko Utama',
            'address' => 'Jl. Contoh No. 123',
            'phone' => '081234567890',
            'city' => 'Jakarta',
            'is_active' => true,
            'owner_id' => null, // Will be set after owner creation
        ]);

        // Create categories with branch_id
        $categories = [
            ['name' => 'Minuman', 'slug' => 'minuman', 'description' => 'Berbagai minuman'],
            ['name' => 'Makanan', 'slug' => 'makanan', 'description' => 'Makanan ringan & berat'],
            ['name' => 'Snack', 'slug' => 'snack', 'description' => 'Camilan & gorengan'],
            ['name' => 'Rokok', 'slug' => 'rokok', 'description' => 'Rokok & tembakau'],
            ['name' => 'Barang', 'slug' => 'barang', 'description' => 'Barang keperluan sehari-hari'],
        ];

        foreach ($categories as $category) {
            Category::create(array_merge($category, ['branch_id' => $branch->id]));
        }

        // Create users with branch_id
        $owner = User::create([
            'name' => 'Budi Santoso',
            'email' => 'owner@pos.id',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        // Set branch owner
        $branch->update(['owner_id' => $owner->id]);

        User::create([
            'name' => 'Siti Rahayu',
            'email' => 'kasir@pos.id',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        // Create products (Indonesian store items)
        $products = [
            // Minuman
            ['category' => 'minuman', 'name' => 'Kopi Hitam', 'sku' => 'KPH001', 'barcode' => '8901234567890', 'purchase_price' => 8000, 'selling_price' => 15000, 'stock' => 100, 'min_stock' => 20],
            ['category' => 'minuman', 'name' => 'Kopi Susu', 'sku' => 'KPS002', 'barcode' => '8901234567891', 'purchase_price' => 10000, 'selling_price' => 20000, 'stock' => 80, 'min_stock' => 15],
            ['category' => 'minuman', 'name' => 'Teh Manis', 'sku' => 'THM003', 'barcode' => '8901234567892', 'purchase_price' => 3000, 'selling_price' => 8000, 'stock' => 150, 'min_stock' => 30],
            ['category' => 'minuman', 'name' => 'Es Jeruk', 'sku' => 'EJR004', 'barcode' => '8901234567893', 'purchase_price' => 5000, 'selling_price' => 12000, 'stock' => 60, 'min_stock' => 15],
            ['category' => 'minuman', 'name' => 'Es Teh Manis', 'sku' => 'ETH005', 'barcode' => '8901234567894', 'purchase_price' => 4000, 'selling_price' => 10000, 'stock' => 120, 'min_stock' => 25],
            ['category' => 'minuman', 'name' => 'Aqua 600ml', 'sku' => 'AQ6006', 'barcode' => '8901234567895', 'purchase_price' => 3500, 'selling_price' => 6000, 'stock' => 200, 'min_stock' => 50],
            ['category' => 'minuman', 'name' => 'Coca Cola 600ml', 'sku' => 'COL007', 'barcode' => '8901234567896', 'purchase_price' => 6000, 'selling_price' => 12000, 'stock' => 50, 'min_stock' => 20],

            // Makanan
            ['category' => 'makanan', 'name' => 'Nasi Goreng', 'sku' => 'NFG001', 'barcode' => '8901234567897', 'purchase_price' => 18000, 'selling_price' => 30000, 'stock' => 25, 'min_stock' => 5],
            ['category' => 'makanan', 'name' => 'Mie Goreng', 'sku' => 'MGR002', 'barcode' => '8901234567898', 'purchase_price' => 15000, 'selling_price' => 25000, 'stock' => 30, 'min_stock' => 5],
            ['category' => 'makanan', 'name' => 'Nasi Padang', 'sku' => 'NPD003', 'barcode' => '8901234567899', 'purchase_price' => 25000, 'selling_price' => 40000, 'stock' => 15, 'min_stock' => 5],
            ['category' => 'makanan', 'name' => 'Soto Ayam', 'sku' => 'STO004', 'barcode' => '8901234567900', 'purchase_price' => 20000, 'selling_price' => 35000, 'stock' => 20, 'min_stock' => 5],

            // Snack
            ['category' => 'snack', 'name' => 'Pisang Goreng', 'sku' => 'PGO001', 'barcode' => '8901234567901', 'purchase_price' => 5000, 'selling_price' => 10000, 'stock' => 40, 'min_stock' => 10],
            ['category' => 'snack', 'name' => 'Tahu Crispy', 'sku' => 'TCR002', 'barcode' => '8901234567902', 'purchase_price' => 4000, 'selling_price' => 8000, 'stock' => 50, 'min_stock' => 15],
            ['category' => 'snack', 'name' => 'Tempe Goreng', 'sku' => 'TGO003', 'barcode' => '8901234567903', 'purchase_price' => 3000, 'selling_price' => 6000, 'stock' => 60, 'min_stock' => 15],
            ['category' => 'snack', 'name' => 'Chicken Wings', 'sku' => 'CWI004', 'barcode' => '8901234567904', 'purchase_price' => 12000, 'selling_price' => 25000, 'stock' => 8, 'min_stock' => 10],

            // Rokok
            ['category' => 'rokok', 'name' => 'Gudang Garam Merah', 'sku' => 'GGM001', 'barcode' => '8901234567905', 'purchase_price' => 22000, 'selling_price' => 28000, 'stock' => 100, 'min_stock' => 30],
            ['category' => 'rokok', 'name' => 'Sampoerna Mild', 'sku' => 'SML002', 'barcode' => '8901234567906', 'purchase_price' => 26000, 'selling_price' => 33000, 'stock' => 80, 'min_stock' => 25],
            ['category' => 'rokok', 'name' => 'Dji Sam Soe', 'sku' => 'DSS003', 'barcode' => '8901234567907', 'purchase_price' => 23000, 'selling_price' => 29000, 'stock' => 60, 'min_stock' => 20],

            // Barang
            ['category' => 'barang', 'name' => 'Sabun Lifebuoy 100g', 'sku' => 'SLB001', 'barcode' => '8901234567908', 'purchase_price' => 8000, 'selling_price' => 15000, 'stock' => 35, 'min_stock' => 10],
            ['category' => 'barang', 'name' => 'Shampo Head Shoulders', 'sku' => 'SHS002', 'barcode' => '8901234567909', 'purchase_price' => 22000, 'selling_price' => 38000, 'stock' => 20, 'min_stock' => 5],
        ];

        foreach ($products as $productData) {
            $category = Category::where('slug', $productData['category'])->first();
            Product::create([
                'category_id' => $category->id,
                'name' => $productData['name'],
                'sku' => $productData['sku'],
                'barcode' => $productData['barcode'],
                'description' => $productData['name'].' - Produk berkualitas',
                'purchase_price' => $productData['purchase_price'],
                'selling_price' => $productData['selling_price'],
                'stock' => $productData['stock'],
                'min_stock' => $productData['min_stock'],
                'is_active' => true,
            ]);
        }

        // Create materials
        $materials = [
            ['name' => 'Gula Pasir', 'unit' => 'kg', 'stock' => 50, 'min_stock' => 10, 'purchase_price' => 14000],
            ['name' => 'Gula Merah', 'unit' => 'kg', 'stock' => 30, 'min_stock' => 5, 'purchase_price' => 18000],
            ['name' => 'Kopi Bubuk', 'unit' => 'kg', 'stock' => 20, 'min_stock' => 5, 'purchase_price' => 80000],
            ['name' => 'Susu Bubuk', 'unit' => 'kg', 'stock' => 15, 'min_stock' => 3, 'purchase_price' => 75000],
            ['name' => 'Teh Celup', 'unit' => 'pcs', 'stock' => 100, 'min_stock' => 20, 'purchase_price' => 2000],
            ['name' => 'Mie Instant', 'unit' => 'pcs', 'stock' => 100, 'min_stock' => 20, 'purchase_price' => 3500],
            ['name' => 'Telur Ayam', 'unit' => 'pcs', 'stock' => 60, 'min_stock' => 10, 'purchase_price' => 2500],
            ['name' => 'Tepung Terigu', 'unit' => 'kg', 'stock' => 25, 'min_stock' => 5, 'purchase_price' => 12000],
            ['name' => 'Minyak Goreng', 'unit' => 'liter', 'stock' => 20, 'min_stock' => 5, 'purchase_price' => 16000],
            ['name' => 'Bawang Putih', 'unit' => 'kg', 'stock' => 10, 'min_stock' => 2, 'purchase_price' => 35000],
            ['name' => 'Bawang Merah', 'unit' => 'kg', 'stock' => 10, 'min_stock' => 2, 'purchase_price' => 40000],
            ['name' => 'Cabai Merah', 'unit' => 'kg', 'stock' => 5, 'min_stock' => 1, 'purchase_price' => 60000],
            ['name' => 'Kemasan Cup', 'unit' => 'pcs', 'stock' => 200, 'min_stock' => 50, 'purchase_price' => 800],
            ['name' => 'Sedotan', 'unit' => 'pak', 'stock' => 50, 'min_stock' => 10, 'purchase_price' => 5000],
            ['name' => 'Plastik Putih', 'unit' => 'pcs', 'stock' => 500, 'min_stock' => 100, 'purchase_price' => 500],
        ];

        foreach ($materials as $materialData) {
            Material::create([
                'name' => $materialData['name'],
                'unit' => $materialData['unit'],
                'stock' => $materialData['stock'],
                'min_stock' => $materialData['min_stock'],
                'purchase_price' => $materialData['purchase_price'],
            ]);
        }

        // Create some sample sales for today
        $owner = User::where('role', 'owner')->first();
        $cashier = User::where('role', 'cashier')->first();

        // Create 5 sales for today
        for ($i = 0; $i < 5; $i++) {
            $sale = \App\Models\Sale::create([
                'user_id' => $cashier->id,
                'sale_date' => now()->subHours(rand(1, 8)),
                'discount_percent' => rand(0, 1) ? 0 : rand(5, 10),
                'tax_percent' => 11,
                'payment_method' => ['cash', 'qris', 'transfer'][rand(0, 2)],
                'customer_name' => 'Pelanggan '.($i + 1),
            ]);

            // Add 2-4 random items to each sale
            $items = Product::inRandomOrder()->limit(rand(2, 4))->get();
            $subtotal = 0;
            $totalCost = 0;

            foreach ($items as $product) {
                $quantity = rand(1, 3);
                $price = $product->selling_price;
                $costPrice = $product->purchase_price;

                \App\Models\SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $price * $quantity,
                    'cost_price' => $costPrice,
                ]);

                $subtotal += $price * $quantity;
                $totalCost += $costPrice * $quantity;

                // Reduce stock
                $product->decrement('stock', $quantity);
            }

            $discountAmount = $subtotal * ($sale->discount_percent / 100);
            $taxableAmount = $subtotal - $discountAmount;
            $taxAmount = $taxableAmount * ($sale->tax_percent / 100);
            $totalAmount = $taxableAmount + $taxAmount;
            $profit = $totalAmount - $totalCost - $discountAmount;

            $sale->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'profit' => $profit,
            ]);
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Owner login: owner@pos.id / password');
        $this->command->info('Cashier login: kasir@pos.id / password');
    }
}
