<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with('user', 'items');

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

        $sales = $query->orderBy('sale_date', 'desc')->paginate(20);

        // Summary stats
        $totalAmount = $query->sum('total_amount');
        $totalProfit = $query->sum('profit');
        $totalTransactions = $query->count();

        return view('sales.index', compact('sales', 'totalAmount', 'totalProfit', 'totalTransactions'));
    }

    public function show(Sale $sale)
    {
        $sale->load('user', 'items.product');
        return view('sales.show', compact('sale'));
    }

    public function dailyReport(Request $request)
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $parsedDate = Carbon::parse($date);

        $sales = Sale::with('user', 'items')
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
        $topProducts = \App\Models\SaleItem::whereHas('sale', function ($query) use ($parsedDate) {
            $query->whereDate('sale_date', $parsedDate);
        })
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

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $sales = Sale::with('user', 'items')
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
        $topProducts = \App\Models\SaleItem::whereHas('sale', function ($query) use ($start, $end) {
            $query->whereBetween('sale_date', [$start, $end]);
        })
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

        $start = Carbon::parse($request->start_date)->startOfDay();
        $end = Carbon::parse($request->end_date)->endOfDay();

        $sales = Sale::with('user', 'items')
            ->whereBetween('sale_date', [$start, $end])
            ->orderBy('sale_date', 'desc')
            ->get();

        $filename = 'sales-report-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
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
        
        $parsedDate = Carbon::parse($date);
        $sales = Sale::with('user', 'items')
            ->whereDate('sale_date', $parsedDate)
            ->orderBy('sale_date', 'desc')
            ->get();

        $totalAmount = $sales->sum('total_amount');
        $totalProfit = $sales->sum('profit');

        return view('sales.print-daily-report', compact('date', 'sales', 'totalAmount', 'totalProfit'));
    }

    public function destroy(Sale $sale)
    {
        // Only owner can delete sales
        if (!auth()->user()->isOwner()) {
            abort(403, 'Hanya pemilik yang dapat menghapus penjualan!');
        }

        // Restore stock for each item
        foreach ($sale->items as $item) {
            $item->product->increment('stock', $item->quantity);
        }

        $sale->items()->delete();
        $sale->delete();

        return redirect()->route('sales.index')->with('success', 'Penjualan berhasil dihapus dan stok dikembalikan!');
    }
}
