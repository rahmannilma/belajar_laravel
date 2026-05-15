<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $query = Sale::with('user', 'items', 'branch')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->completed();

        // Branch filter
        if ($request->branch) {
            // Verify branch is accessible
            if (in_array($request->branch, $accessibleBranchIds)) {
                $query->where('branch_id', $request->branch);
            }
        }

        // Date filters
        if ($request->date) {
            $query->whereDate('sale_date', $request->date);
        }

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('sale_date', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
        } elseif ($request->start_date) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        } elseif ($request->end_date) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        // Quick filters
        if ($request->filter === 'today') {
            $query->today();
        } elseif ($request->filter === 'week') {
            $query->thisWeek();
        } elseif ($request->filter === 'month') {
            $query->thisMonth();
        }

        // Payment method filter
        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }

        // Search by invoice
        if ($request->search) {
            $query->where('invoice_number', 'like', "%{$request->search}%");
        }

        // Summary stats (before pagination)
        $totalAmount = (clone $query)->sum('total_amount');
        $totalProfit = (clone $query)->sum('profit');
        $totalTransactions = (clone $query)->count();

        $sales = $query->orderBy('sale_date', 'desc')->paginate(20);

        // Branches for filter (only accessible ones)
        $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        return view('sales.index', compact('sales', 'totalAmount', 'totalProfit', 'totalTransactions', 'branches'));
    }

    public function overview(Request $request)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();
        $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        // Date filters
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::today()->startOfDay();
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::today()->endOfDay();

        $branchSummaries = $branches->map(function ($branch) use ($startDate, $endDate) {
            $sales = Sale::where('branch_id', $branch->id)
                ->whereBetween('sale_date', [$startDate, $endDate]);

            $salesCount = (clone $sales)->count();
            $totalSales = (clone $sales)->sum('total_amount');
            $totalProfit = (clone $sales)->sum('profit');
            $totalCost = (clone $sales)->sum('total_cost');

            return [
                'branch' => $branch,
                'sales_count' => $salesCount,
                'total_sales' => $totalSales,
                'total_profit' => $totalProfit,
                'total_cost' => $totalCost,
            ];
        });

        // Overall summary
        $overallSales = Sale::whereIn('branch_id', $accessibleBranchIds)->whereBetween('sale_date', [$startDate, $endDate]);
        $overallCount = (clone $overallSales)->count();
        $overallTotal = (clone $overallSales)->sum('total_amount');
        $overallProfit = (clone $overallSales)->sum('profit');
        $overallCost = (clone $overallSales)->sum('total_cost');

        return view('sales.overview', compact('branches', 'branchSummaries', 'startDate', 'endDate', 'overallCount', 'overallTotal', 'overallProfit', 'overallCost'));
    }

    public function show(Sale $sale)
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify sale belongs to accessible branch
        if (! in_array($sale->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }

        $sale->load('user', 'items.product');

        return view('sales.show', compact('sale'));
    }

    public function dailyReport(Request $request)
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $parsedDate = Carbon::parse($date);
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $sales = Sale::with('user', 'items')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->whereDate('sale_date', $parsedDate)
            ->orderBy('sale_date', 'desc')
            ->get();

        $totalAmount = $sales->sum('total_amount');
        $totalProfit = $sales->sum('profit');
        $totalCost = $sales->sum('total_cost');
        $transactionCount = $sales->count();
        $totalItems = $sales->sum(function ($sale) {
            return $sale->items->sum('quantity');
        });

        // Payment method breakdown
        $paymentBreakdown = $sales->groupBy('payment_method')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('total_amount'),
            ];
        });

        // Top products sold
        $topProducts = \App\Models\SaleItem::whereIn('sale_id', $sales->pluck('id'))
            ->selectRaw('product_id, product_name, SUM(quantity) as total_qty, SUM(subtotal) as total_sales')
            ->groupBy('product_id', 'product_name')
            ->orderBy('total_qty', 'desc')
            ->limit(10)
            ->get();

        // Hourly sales
        $hourlySales = $sales->groupBy(function ($sale) {
            return $sale->sale_date->format('H');
        })->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('total_amount'),
            ];
        });

        return view('sales.daily-report', compact(
            'date',
            'sales',
            'totalAmount',
            'totalProfit',
            'totalCost',
            'transactionCount',
            'totalItems',
            'paymentBreakdown',
            'topProducts',
            'hourlySales'
        ));
    }

    public function weeklyReport(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfWeek()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfWeek()->format('Y-m-d'));
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $sales = Sale::with('user', 'items')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->whereBetween('sale_date', [$start, $end])
            ->orderBy('sale_date', 'desc')
            ->get();

        $totalAmount = $sales->sum('total_amount');
        $totalProfit = $sales->sum('profit');
        $totalCost = $sales->sum('total_cost');
        $transactionCount = $sales->count();

        // Daily breakdown
        $dailySales = [];
        for ($i = 0; $i <= $start->diffInDays($end); $i++) {
            $day = $start->copy()->addDays($i);
            $daySales = $sales->filter(function ($sale) use ($day) {
                return $sale->sale_date->isSameDay($day);
            });

            $dailySales[] = [
                'date' => $day->format('d M Y'),
                'day' => $day->format('l'),
                'count' => $daySales->count(),
                'total' => $daySales->sum('total_amount'),
                'profit' => $daySales->sum('profit'),
            ];
        }

        // Top products
        $topProducts = \App\Models\SaleItem::whereIn('sale_id', $sales->pluck('id'))
            ->selectRaw('product_id, product_name, SUM(quantity) as total_qty, SUM(subtotal) as total_sales')
            ->groupBy('product_id', 'product_name')
            ->orderBy('total_qty', 'desc')
            ->limit(10)
            ->get();

        return view('sales.weekly-report', compact(
            'startDate',
            'endDate',
            'sales',
            'totalAmount',
            'totalProfit',
            'totalCost',
            'transactionCount',
            'dailySales',
            'topProducts'
        ));
    }

    public function exportCsv(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $start = Carbon::parse($request->start_date)->startOfDay();
        $end = Carbon::parse($request->end_date)->endOfDay();

        $sales = Sale::with('user', 'items')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->whereBetween('sale_date', [$start, $end])
            ->orderBy('sale_date', 'desc')
            ->get();

        $filename = 'sales-report-'.$start->format('Ymd').'-'.$end->format('Ymd').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $handle = fopen('php://temp', 'r+');

        // Header row
        fputcsv($handle, [
            'Invoice',
            'Tanggal',
            'Kasir',
            'Items',
            'Subtotal',
            'Diskon %',
            'Diskon Rp',
            'Pajak %',
            'Pajak Rp',
            'Total',
            'Keuntungan',
            'Metode Bayar',
            'Pelanggan',
        ]);

        // Data rows
        foreach ($sales as $sale) {
            fputcsv($handle, [
                $sale->invoice_number,
                $sale->sale_date->format('d/m/Y H:i'),
                $sale->user->name,
                $sale->items->count(),
                number_format($sale->subtotal, 0, ',', '.'),
                $sale->discount_percent,
                number_format($sale->discount_amount, 0, ',', '.'),
                $sale->tax_percent,
                number_format($sale->tax_amount, 0, ',', '.'),
                number_format($sale->total_amount, 0, ',', '.'),
                number_format($sale->profit, 0, ',', '.'),
                $sale->payment_method_label,
                $sale->customer_name ?? '-',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, $headers);
    }

    public function printDailyReport(Request $request)
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        $parsedDate = Carbon::parse($date);
        $sales = Sale::with('user', 'items')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->whereDate('sale_date', $parsedDate)
            ->orderBy('sale_date', 'desc')
            ->get();

        $totalAmount = $sales->sum('total_amount');
        $totalProfit = $sales->sum('profit');

        return view('sales.print-daily-report', compact('date', 'sales', 'totalAmount', 'totalProfit'));
    }

    public function destroy(Sale $sale)
    {
        $user = auth()->user();
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify sale belongs to accessible branch
        if (! in_array($sale->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }

        // Owner can delete any sale, cashier can only delete cancelled sales from their branch
        if (! $user->isOwner()) {
            if (! $sale->isCancelled()) {
                abort(403, 'Gunakan fitur batalkan untuk membatalkan transaksi!');
            }
        }

        // If already cancelled, allow permanent delete
        if ($sale->isCancelled()) {
            $sale->items()->delete();
            $sale->delete();

            return redirect()->route('sales.index')->with('success', 'Transaksi berhasil dihapus permanen!');
        }

        return redirect()->route('sales.index')->with('error', 'Gunakan fitur batalkan untuk membatalkan transaksi!');
    }

    public function cancel(Sale $sale)
    {
        $user = auth()->user();
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Verify sale belongs to accessible branch
        if (! in_array($sale->branch_id, $accessibleBranchIds)) {
            abort(403, 'Anda tidak dapat membatalkan transaksi cabang lain!');
        }

        // Cannot cancel already cancelled sales
        if ($sale->isCancelled()) {
            abort(400, 'Transaksi sudah dibatalkan!');
        }

        $branchId = $sale->branch_id;

        // Restore stock for each item
        foreach ($sale->items as $item) {
            // Restore product branch stock
            $productBranchStock = $item->product->branchStocks()->where('branch_id', $branchId)->first();
            if ($productBranchStock) {
                $productBranchStock->increment('stock', $item->quantity);
            } else {
                $item->product->increment('stock', $item->quantity);
            }

            // Restore material branch stock
            foreach ($item->product->materials as $material) {
                $materialBranchStock = $material->branchStocks()->where('branch_id', $branchId)->first();
                if ($materialBranchStock) {
                    $materialBranchStock->increment('stock', $material->pivot->quantity * $item->quantity);
                } else {
                    $material->increment('stock', $material->pivot->quantity * $item->quantity);
                }
            }
        }

        // Mark as cancelled
        $sale->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Penjualan berhasil dibatalkan dan stok dikembalikan!');
    }

    public function branchTransactions(Request $request)
    {
        $user = auth()->user();
        $accessibleBranchIds = $this->getAccessibleBranchIds();

        // Branches for filter - only accessible ones
        $branches = Branch::whereIn('id', $accessibleBranchIds)->where('is_active', true)->orderBy('name')->get();

        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->subDays(6)->startOfDay();
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

        $query = Sale::with('user', 'items', 'branch')->completed();

        // Branch filter
        if ($request->branch_id) {
            if (in_array($request->branch_id, $accessibleBranchIds)) {
                $query->where('branch_id', $request->branch_id);
            }
        } else {
            // Default to accessible branches
            $query->whereIn('branch_id', $accessibleBranchIds);
        }

        if ($startDate) {
            $query->whereBetween('sale_date', [$startDate, $endDate]);
        }

        // Summary stats (before pagination)
        $overallTotal = (clone $query)->sum('total_amount');
        $overallProfit = (clone $query)->sum('profit');
        $overallCost = (clone $query)->sum('total_cost');
        $overallCount = (clone $query)->count();

        $sales = $query->orderBy('sale_date', 'desc')->paginate(20);

        $branchData = $branches->map(function ($branch) use ($startDate, $endDate) {
            $query = Sale::where('branch_id', $branch->id)->with('user', 'items')->completed();

            if ($startDate) {
                $query->whereBetween('sale_date', [$startDate, $endDate]);
            }

            $sales = (clone $query)->orderBy('sale_date', 'desc')->get();

            $totalSales = (clone $query)->sum('total_amount');
            $totalProfit = (clone $query)->sum('profit');
            $totalCost = (clone $query)->sum('total_cost');
            $transactionCount = (clone $query)->count();

            $todayQuery = Sale::where('branch_id', $branch->id)->completed()->whereDate('sale_date', today());
            $todaySales = $todayQuery->sum('total_amount');
            $todayTransactionCount = $todayQuery->count();

            return [
                'branch' => $branch,
                'sales' => $sales,
                'total_sales' => $totalSales,
                'total_profit' => $totalProfit,
                'total_cost' => $totalCost,
                'transaction_count' => $transactionCount,
                'today_sales' => $todaySales,
                'today_transaction_count' => $todayTransactionCount,
            ];
        });

        return view('sales.branch-transactions', compact(
            'branches',
            'overallTotal',
            'overallProfit',
            'overallCost',
            'overallCount',
            'startDate',
            'endDate',
            'branchData'
        ));
    }
}
