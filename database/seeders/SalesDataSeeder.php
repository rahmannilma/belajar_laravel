<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SalesDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Pastikan data master sudah ada
        $owner = User::where('role', 'owner')->first();
        $cashier = User::where('role', 'cashier')->first();

        if (!$owner || !$cashier) {
            $this->command->error('User owner/kasir belum ada. Jalankan DatabaseSeeder terlebih dahulu.');
            return;
        }

        // Hapus data penjualan lama
        Sale::query()->delete();

        $products = Product::all();

        if ($products->isEmpty()) {
            $this->command->error('Belum ada produk. Jalankan DatabaseSeeder terlebih dahulu.');
            return;
        }

        $branchId = $owner->branch_id;
        $today = Carbon::today();
        $totalSalesCreated = 0;

        // Data penjualan per hari selama 30 hari terakhir
        // Pola: weekday 3-5 transaksi, weekend 5-8 transaksi
        // Dengan variasi total penjualan yang realistis
        for ($day = 29; $day >= 0; $day--) {
            $date = $today->copy()->subDays($day);
            $isWeekend = $date->isWeekend();
            $dayOfWeek = $date->englishDayOfWeek;

            // Variasi jumlah transaksi per hari
            if ($isWeekend) {
                $numSales = rand(5, 8);
            } elseif (in_array($dayOfWeek, ['Monday', 'Tuesday'])) {
                $numSales = rand(3, 5);
            } else {
                $numSales = rand(4, 7);
            }

            for ($i = 0; $i < $numSales; $i++) {
                // Pilih produk acak (2-5 item per transaksi)
                $itemCount = rand(2, 5);
                $selectedProducts = $products->shuffle()->take($itemCount);

                $hour = $isWeekend ? rand(8, 20) : rand(9, 19);
                $minute = rand(0, 59);
                $saleDate = $date->copy()->setTime($hour, $minute);

                $subtotal = 0;
                $totalCost = 0;

                // Buat sale dulu untuk dapat ID
                $sale = Sale::create([
                    'user_id' => $cashier->id,
                    'branch_id' => $branchId,
                    'sale_date' => $saleDate,
                    'discount_percent' => rand(0, 1) ? 0 : rand(5, 15),
                    'tax_percent' => 11,
                    'payment_method' => ['cash', 'qris', 'transfer'][rand(0, 2)],
                    'customer_name' => 'Pelanggan ' . strtoupper(substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 6)),
                    'status' => 'completed',
                ]);

                foreach ($selectedProducts as $product) {
                    $quantity = rand(1, 4);
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

                    // Kurangi stok produk
                    $product->decrement('stock', $quantity);
                }

                // Menghitung diskon, pajak, total
                $discountAmount = $subtotal * ($sale->discount_percent / 100);
                $taxableAmount = $subtotal - $discountAmount;
                $taxAmount = round($taxableAmount * ($sale->tax_percent / 100), 2);
                $totalAmount = round($taxableAmount + $taxAmount, 2);
                $profit = round($totalAmount - $totalCost - $discountAmount, 2);

                $sale->update([
                    'subtotal' => $subtotal,
                    'discount_amount' => round($discountAmount, 2),
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'total_cost' => round($totalCost, 2),
                    'profit' => $profit,
                ]);

                $totalSalesCreated++;
            }
        }

        $this->command->info("Seeder berhasil: {$totalSalesCreated} transaksi penjualan dibuat untuk 30 hari terakhir.");
        $this->command->info("Range tanggal: {$today->copy()->subDays(29)->toDateString()} s/d {$today->toDateString()}");
    }
}