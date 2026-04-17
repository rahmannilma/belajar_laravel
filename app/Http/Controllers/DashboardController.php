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
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Today's statistics
        $todaySales = Sale::today()->completed();
        $todayTotal = $todaySales->sum('total_amount');
        $todayProfit = $todaySales->sum('profit');
        $todayTransactions = $todaySales->count();
        $todayItems = $todaySales->withCount('items')->get()->sum('items_count');

        // Weekly statistics
        $weeklySales = Sale::whereBetween('sale_date', [$startOfWeek, $endOfWeek])->completed();
        $weeklyTotal = $weeklySales->sum('total_amount');
        $weeklyProfit = $weeklySales->sum('profit');
        $weeklyTransactions = $weeklySales->count();

        // Monthly statistics
        $monthlySales = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth])->completed();
        $monthlyTotal = $monthlySales->sum('total_amount');
        $monthlyProfit = $monthlySales->sum('profit');
        $monthlyTransactions = $monthlySales->count();

        // Branch summary for today - show all branches
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $branchSummaries = $branches->map(function ($branch) use ($today) {
            $sales = Sale::where('branch_id', $branch->id)->whereDate('sale_date', $today)->completed();

            return [
                'branch' => $branch,
                'total_sales' => (clone $sales)->sum('total_amount'),
                'total_profit' => (clone $sales)->sum('profit'),
                'transaction_count' => (clone $sales)->count(),
            ];
        });

        // Branch data with today stats and total (all time) - show all branches
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

        // Low stock products
        $lowStockProducts = Product::lowStock()
            ->with('category')
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get();

        // Chart data - Daily sales for the last 7 days
        $last7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $sales = Sale::whereDate('sale_date', $date)->completed()->sum('total_amount');
            $profit = Sale::whereDate('sale_date', $date)->completed()->sum('profit');
            $count = Sale::whereDate('sale_date', $date)->completed()->count();

            $last7Days[] = [
                'date' => $date->format('d M'),
                'label' => $date->format('l'),
                'sales' => $sales,
                'profit' => $profit,
                'count' => $count,
            ];
        }

        // Top selling products today
        $topProducts = Product::withCount([
            'saleItems' => function ($query) {
                $query->whereHas('sale', function ($q) {
                    $q->whereDate('sale_date', Carbon::today())->completed();
                });
            },
        ])
            ->orderBy('sale_items_count', 'desc')
            ->limit(5)
            ->get();

        // Popular products (most sold this month)
        $popularProducts = Product::withCount([
            'saleItems' => function ($query) {
                $query->whereHas('sale', function ($q) {
                    $q->whereMonth('sale_date', Carbon::now()->month)
                        ->whereYear('sale_date', Carbon::now()->year)->completed();
                });
            },
        ])
            ->orderBy('sale_items_count', 'desc')
            ->limit(10)
            ->get();

        // Category sales breakdown for today
        $categorySales = \App\Models\Category::with([
            'products.saleItems' => function ($query) {
                $query->whereHas('sale', function ($q) {
                    $q->whereDate('sale_date', Carbon::today())->completed();
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

        // Recent sales
        $recentSales = Sale::with('user', 'items')
            ->completed()
            ->orderBy('sale_date', 'desc')
            ->limit(5)
            ->get();

        // Revenue comparison (yesterday vs today)
        $yesterdaySales = Sale::whereDate('sale_date', Carbon::yesterday())->completed()->sum('total_amount');
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
