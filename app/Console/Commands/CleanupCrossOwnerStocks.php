<?php

namespace App\Console\Commands;

use App\Models\ProductBranchStock;
use Illuminate\Console\Command;

class CleanupCrossOwnerStocks extends Command
{
    protected $signature = 'stocks:cleanup-cross-owner {--dry-run : Hanya cek data tanpa menghapus apapun} {--force : Jalankan tanpa konfirmasi}';

    protected $description = 'Clean up product branch stocks that belong to different owners/businesses';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Scanning product_branch_stocks for cross-owner entries...');

        $crossOwnerStocks = ProductBranchStock::with(['product.category.branch', 'branch'])
            ->get()
            ->filter(function ($pbs) {
                $productOwnerId = $pbs->product?->category?->branch?->owner_id;
                $branchOwnerId = $pbs->branch?->owner_id;

                if ($productOwnerId && $branchOwnerId && $productOwnerId !== $branchOwnerId) {
                    return true;
                }

                return false;
            });

        $count = $crossOwnerStocks->count();

        if ($count === 0) {
            $this->info('✅ Tidak ada rekaman stok cabang lintas-owner yang salah.');
            return 0;
        }

        $this->warn("Ditemukan {$count} rekaman stok cabang yang salah (nyasar ke cabang owner lain):");

        foreach ($crossOwnerStocks as $item) {
            $productName = $item->product?->name ?? 'Unknown Product';
            $branchName = $item->branch?->name ?? 'Unknown Branch';
            $productOwner = $item->product?->category?->branch?->owner_id;
            $branchOwner = $item->branch?->owner_id;

            $this->line("- [ID: {$item->id}] Produk: {$productName} (Owner: {$productOwner}) di Cabang: {$branchName} (Owner Cabang: {$branchOwner})");
        }

        if ($isDryRun) {
            $this->info("\nMode [DRY-RUN]: Tidak ada data yang dihapus.");
            return 0;
        }

        if (! $this->option('force') && ! $this->confirm("\nApakah Anda yakin ingin menghapus {$count} rekaman stok nyasar di atas?", false)) {
            $this->info('Dibatalkan. Tidak ada data yang dihapus.');
            return 0;
        }

        $deleted = ProductBranchStock::whereIn('id', $crossOwnerStocks->pluck('id'))->delete();

        $this->info("✅ Berhasil menghapus {$deleted} rekaman stok nyasar.");

        return 0;
    }
}
