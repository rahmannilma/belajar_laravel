<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
        $todaySales = Sale::today();
        $todayTotal = $todaySales->sum('total_amount');
        $todayProfit = $todaySales->sum('profit');
        $todayTransactions = $todaySales->count();
        $todayItems = $todaySales->withCount('items')->get()->sum('items_count');

        // Weekly statistics
        $weeklySales = Sale::whereBetween('sale_date', [$startOfWeek, $endOfWeek]);
        $weeklyTotal = $weeklySales->sum('total_amount');
        $weeklyProfit = $weeklySales->sum('profit');
        $weeklyTransactions = $weeklySales->count();

        // Monthly statistics
        $monthlySales = Sale::whereBetween('sale_date', [$startOfMonth, $endOfMonth]);
        $monthlyTotal = $monthlySales->sum('total_amount');
        $monthlyProfit = $monthlySales->sum('profit');
        $monthlyTransactions = $monthlySales->count();

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
            $sales = Sale::whereDate('sale_date', $date)->sum('total_amount');
            $profit = Sale::whereDate('sale_date', $date)->sum('profit');
            $count = Sale::whereDate('sale_date', $date)->count();
            
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
                    $q->whereDate('sale_date', Carbon::today());
                });
            }
        ])
        ->orderBy('sale_items_count', 'desc')
        ->limit(5)
        ->get();

        // Popular products (most sold this month)
        $popularProducts = Product::withCount([
            'saleItems' => function ($query) {
                $query->whereHas('sale', function ($q) {
                    $q->whereMonth('sale_date', Carbon::now()->month)
                      ->whereYear('sale_date', Carbon::now()->year);
                });
            }
        ])
        ->orderBy('sale_items_count', 'desc')
        ->limit(10)
        ->get();

        // Category sales breakdown for today
        $categorySales = \App\Models\Category::with([
            'products.saleItems' => function ($query) {
                $query->whereHas('sale', function ($q) {
                    $q->whereDate('sale_date', Carbon::today());
                });
            }
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
            ->orderBy('sale_date', 'desc')
            ->limit(5)
            ->get();

        // Revenue comparison (yesterday vs today)
        $yesterdaySales = Sale::whereDate('sale_date', Carbon::yesterday())->sum('total_amount');
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
            'yesterdaySales'
        ));
    }
}
