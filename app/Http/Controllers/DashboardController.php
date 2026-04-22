<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Today's statistics (across all accessible branches)
        $todaySales = Sale::today()->whereIn('branch_id', $accessibleBranchIds)->completed();
        $todayTotal = $todaySales->sum('total_amount');
        $todayProfit = $todaySales->sum('profit');
        $todayTransactions = $todaySales->count();
        $todayItems = $todaySales->withCount('items')->get()->sum('items_count');

        // Weekly statistics
        $weeklySales = Sale::whereBetween('sale_date', [$startOfWeek, $endOfWeek])
            ->whereIn('branch_id', $accessibleBranchIds)
            ->completed();
        $weeklyTotal = $weeklySales->sum('total_amount');
        $weeklyProfit = $weeklySales->sum('profit');
        $weeklyTransactions = $weeklySales->count();

        // Monthly statistics
        $monthlySales = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth])
            ->whereIn('branch_id', $accessibleBranchIds)
            ->completed();
        $monthlyTotal = $monthlySales->sum('total_amount');
        $monthlyProfit = $monthlySales->sum('profit');
        $monthlyTransactions = $monthlySales->count();

        // Branch summary for today - only user's branches
        $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();
        $branchSummaries = $branches->map(function ($branch) use ($today) {
            $sales = Sale::where('branch_id', $branch->id)->whereDate('sale_date', $today)->completed();

            return [
                'branch' => $branch,
                'total_sales' => (clone $sales)->sum('total_amount'),
                'total_profit' => (clone $sales)->sum('profit'),
                'transaction_count' => (clone $sales)->count(),
            ];
        });

        // Branch data with today stats and total (all time) - show accessible branches
        $branchData = $branches->map(function ($branch) use ($today) {
            $todaySales = Sale::where('branch_id', $branch->id)->whereDate('sale_date', $today)->completed();
            $todayTransactionCount = $todaySales->count();
            $todaySalesTotal = $todaySales->sum('total_amount');

            $allSales = Sale::where('branch_id', $branch->id)->completed();
            $transactionCount = $allSales->count();
            $totalSales = $allSales->sum('total_amount');

            return [
                'branch' => $branch,
                'today_transaction_count' => $todayTransactionCount,
                'today_sales' => $todaySalesTotal,
                'transaction_count' => $transactionCount,
                'total_sales' => $totalSales,
            ];
        });

        // Low stock products - only from accessible branches
        $lowStockProducts = Product::with('category', 'branchStocks', 'materials')
            ->whereHas('branchStocks', function ($q) use ($accessibleBranchIds) {
                $q->whereIn('branch_id', $accessibleBranchIds);
            })
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($product) use ($accessibleBranchIds) {
                $branchId = $accessibleBranchIds[0] ?? null;
                $stock = $product->stock;
                if ($branchId && $product->hasMaterials()) {
                    $calculated = $product->calculateStockFromMaterials($branchId);
                    if ($calculated !== null) {
                        $stock = $calculated;
                    }
                }
                $product->display_stock = $stock;

                return $product;
            })
            ->filter(function ($product) {
                return $product->display_stock <= $product->min_stock;
            })
            ->values();

        // Chart data - Daily sales for the last 7 days across accessible branches
        $last7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $sales = Sale::whereDate('sale_date', $date)
                ->whereIn('branch_id', $accessibleBranchIds)
                ->completed()
                ->sum('total_amount');
            $profit = Sale::whereDate('sale_date', $date)
                ->whereIn('branch_id', $accessibleBranchIds)
                ->completed()
                ->sum('profit');
            $count = Sale::whereDate('sale_date', $date)
                ->whereIn('branch_id', $accessibleBranchIds)
                ->completed()
                ->count();

            $last7Days[] = [
                'date' => $date->format('d M'),
                'label' => $date->format('l'),
                'sales' => $sales,
                'profit' => $profit,
                'count' => $count,
            ];
        }

        // Top selling products today (from accessible branches)
        $topProducts = Product::select('products.*')
            ->whereHas('saleItems.sale', function ($q) use ($accessibleBranchIds) {
                $q->whereDate('sale_date', Carbon::today())
                    ->whereIn('branch_id', $accessibleBranchIds)
                    ->where('status', 'completed');
            })
            ->withCount('saleItems')
            ->orderBy('sale_items_count', 'desc')
            ->limit(5)
            ->get();

        // Popular products (most sold this month) from accessible branches
        $popularProducts = Product::select('products.*')
            ->whereHas('saleItems.sale', function ($q) use ($accessibleBranchIds) {
                $q->whereMonth('sale_date', Carbon::now()->month)
                    ->whereYear('sale_date', Carbon::now()->year)
                    ->whereIn('branch_id', $accessibleBranchIds)
                    ->where('status', 'completed');
            })
            ->withCount('saleItems')
            ->orderBy('sale_items_count', 'desc')
            ->limit(10)
            ->get();

        // Category sales breakdown for today (accessible branches only)
        $categorySales = \App\Models\Category::with([
            'products.saleItems' => function ($query) use ($accessibleBranchIds) {
                $query->whereHas('sale', function ($q) use ($accessibleBranchIds) {
                    $q->whereDate('sale_date', Carbon::today())
                        ->whereIn('branch_id', $accessibleBranchIds)
                        ->completed();
                });
            },
        ])->get()->map(function ($category) {
            $totalSales = $category->products->sum(function ($product) {
                return $product->saleItems->sum('subtotal');
            });
            $category->products_sum_subtotal = $totalSales;

            return $category;
        })->filter(function ($cat) {
            return $cat->products_sum_subtotal > 0;
        });

        // Recent sales from accessible branches
        $recentSales = Sale::with('user', 'items')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->completed()
            ->orderBy('sale_date', 'desc')
            ->limit(5)
            ->get();

        // Revenue comparison (yesterday vs today) across accessible branches
        $yesterdaySales = Sale::whereDate('sale_date', Carbon::yesterday())
            ->whereIn('branch_id', $accessibleBranchIds)
            ->completed()
            ->sum('total_amount');
        $todayComparison = $yesterdaySales > 0
            ? (($todayTotal - $yesterdaySales) / $yesterdaySales) * 100
            : ($todayTotal > 0 ? 100 : 0);

        return view('dashboard', compact(
            'todayTotal',
            'todayProfit',
            'todayTransactions',
            'todayItems',
            'weeklyTotal',
            'weeklyProfit',
            'weeklyTransactions',
            'monthlyTotal',
            'monthlyProfit',
            'monthlyTransactions',
            'lowStockProducts',
            'last7Days',
            'topProducts',
            'popularProducts',
            'categorySales',
            'recentSales',
            'todayComparison',
            'yesterdaySales',
            'branchSummaries',
            'branchData',
            'branches'
        ));
    }
}
