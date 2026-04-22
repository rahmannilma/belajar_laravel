<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearMasterData extends Command
{
    protected $signature = 'db:clear-masters';

    protected $description = 'Clear master data: categories, products, materials, branches and related pivot tables';

    public function handle()
    {
        if (! $this->confirm('⚠️  This will DELETE ALL master data (categories, products, materials, branches) and related records. Continue?', false)) {
            $this->info('❌ Cancelled.');

            return;
        }

        DB::transaction(function () {
            // Disable foreign key checks
            Schema::disableForeignKeyConstraints();

            // Truncate pivot tables first
            DB::table('branch_category')->truncate();
            DB::table('product_branch_stock')->truncate();
            DB::table('material_branch_stock')->truncate();
            DB::table('product_material')->truncate();

            // Truncate master tables
            DB::table('products')->truncate(); // cascade sale_items
            DB::table('materials')->truncate();
            DB::table('categories')->truncate();
            DB::table('branches')->truncate(); // cascade sales, etc.

            // Re-enable foreign key checks
            Schema::enableForeignKeyConstraints();
        });

        $this->info('✅ Master data cleared successfully.');
    }
}
