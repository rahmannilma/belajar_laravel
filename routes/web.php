<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Guest routes (unauthenticated)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register/business', [AuthController::class, 'showRegisterForm'])->name('register.business');
    Route::post('/register/business', [AuthController::class, 'registerBusiness']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    // Home redirect based on role
    Route::get('/', function () {
        if (auth()->user()->isCashier()) {
            return redirect()->route('kasir');
        }

        return redirect()->route('dashboard');
    });

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/logout', function () {
        return redirect()->route('logout');
    });

    // Dashboard (owner only)
    Route::middleware('role:owner')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });

    // Profile
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::post('/profile', [AuthController::class, 'updateProfile']);

    // Products (only owner can manage)
    Route::resource('products', ProductController::class);
    Route::post('/products/{product}/adjust-stock', [ProductController::class, 'stockAdjustment'])->name('products.adjust-stock');
    Route::get('/products/{product}/branch-stock', [ProductController::class, 'branchStock'])->name('products.branch-stock');
    Route::post('/products/{product}/branch-stock', [ProductController::class, 'updateBranchStock'])->name('products.branch-stock.update');
    Route::post('/products/{product}/branch-stock/bulk', [ProductController::class, 'bulkBranchStock'])->name('products.branch-stock.bulk');
    Route::get('/products/{product}/barcode', [ProductController::class, 'printBarcode'])->name('products.barcode');
    Route::post('/products/print-barcodes', [ProductController::class, 'printBarcodes'])->name('products.print-barcodes');

    // Materials (only owner can manage)
    Route::resource('materials', MaterialController::class);
    Route::get('/materials/{material}/branch-stock', [MaterialController::class, 'branchStock'])->name('materials.branch-stock');
    Route::post('/materials/{material}/branch-stock', [MaterialController::class, 'updateBranchStock'])->name('materials.branch-stock.update');
    Route::post('/materials/{material}/branch-stock/bulk', [MaterialController::class, 'bulkBranchStock'])->name('materials.branch-stock.bulk');

    // Categories (only owner can manage)
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::post('/categories/quick-store', [CategoryController::class, 'store'])->name('categories.quick-store');

    // Branches (only owner can manage)
    Route::resource('branches', BranchController::class);
    Route::post('/branches/{branch}/toggle-active', [BranchController::class, 'toggleActive'])->name('branches.toggle-active');
    Route::post('/branches/{branch}/adjust-stock/{item}', [BranchController::class, 'adjustStock'])->name('branches.adjust-stock');
    Route::delete('/branches/{branch}/remove-stock/{item}', [BranchController::class, 'removeStock'])->name('branches.remove-stock');
    Route::get('/cabang/stok', [BranchController::class, 'stockOverview'])->name('branches.stock-overview');
    Route::get('/cabang/stok/{branch}', [BranchController::class, 'stockOverview']);
    Route::get('/api/cabang/stok/{branch}', [BranchController::class, 'stockApi']);

    // Sales (owner only)
    Route::resource('sales', SaleController::class)->only(['index', 'show', 'destroy']);
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::get('/sales/daily-report', [SaleController::class, 'dailyReport'])->name('sales.daily-report');
    Route::get('/sales/weekly-report', [SaleController::class, 'weeklyReport'])->name('sales.weekly-report');
    Route::get('/sales/export-csv', [SaleController::class, 'exportCsv'])->name('sales.export-csv');
    Route::get('/sales/print-daily', [SaleController::class, 'printDailyReport'])->name('sales.print-daily');
    Route::get('/transaksi-cabang', [SaleController::class, 'branchTransactions'])->name('sales.by-branch');
    Route::get('/sales/by-branch-new', [SaleController::class, 'branchTransactions'])->name('sales.by-branch-new');

    // Kasir / POS (cashier only)
    Route::middleware('role:cashier')->group(function () {
        Route::get('/kasir', [KasirController::class, 'index'])->name('kasir');
        Route::post('/kasir', [KasirController::class, 'store']);
        Route::get('/kasir/receipt/{sale}', [KasirController::class, 'receipt'])->name('kasir.receipt');
        Route::get('/kasir/print/{sale}', [KasirController::class, 'printReceipt'])->name('kasir.print');

        // Kasir API
        Route::prefix('api')->group(function () {
            Route::get('/kasir/products', [KasirController::class, 'searchProducts'])->name('api.kasir.products');
            Route::get('/kasir/products/{product}', [KasirController::class, 'getProduct'])->name('api.kasir.product');
            Route::get('/kasir/popular', [KasirController::class, 'popularProducts'])->name('api.kasir.popular');
            Route::get('/kasir/suggestions', [KasirController::class, 'suggestions'])->name('api.kasir.suggestions');
        });
    });

    // API Routes (owner only)
    Route::prefix('api')->group(function () {
        // Products API
        Route::get('/products/search', [ProductController::class, 'search'])->name('api.products.search');
        Route::get('/products/lookup', [ProductController::class, 'lookupBarcode'])->name('api.products.lookup');
    });

    // Users Management (only owner)
    Route::middleware('role:owner')->group(function () {
        Route::resource('users', UserController::class);
    });
});
