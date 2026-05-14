<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearMasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

DB::table('branch_category')->truncate();
         DB::table('product_branch_stock')->truncate();
         DB::table('material_branch_stock')->truncate();
         DB::table('product_material')->truncate();
         DB::table('sale_items')->truncate();
         DB::table('sales')->truncate();
         DB::table('products')->truncate();
         DB::table('materials')->truncate();
         DB::table('categories')->truncate();
         DB::table('branches')->truncate();

        Schema::enableForeignKeyConstraints();
    }
}
