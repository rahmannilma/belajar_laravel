<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\MaterialController;
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
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/logout', function () {
        return redirect()->route('logout');
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/', [DashboardController::class, 'index']);

    // Profile
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::post('/profile', [AuthController::class, 'updateProfile']);

    // Products (only owner can manage)
    Route::resource('products', ProductController::class);
    Route::post('/products/{product}/adjust-stock', [ProductController::class, 'stockAdjustment'])->name('products.adjust-stock');
    Route::get('/products/{product}/barcode', [ProductController::class, 'printBarcode'])->name('products.barcode');
    Route::post('/products/print-barcodes', [ProductController::class, 'printBarcodes'])->name('products.print-barcodes');

    // Materials (only owner can manage)
    Route::resource('materials', MaterialController::class);
    Route::post('/materials/{material}/adjust-stock', [MaterialController::class, 'adjustStock'])->name('materials.adjust-stock');


    // Categories (only owner can manage)
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::post('/categories/quick-store', [CategoryController::class, 'store'])->name('categories.quick-store');

    // Kasir / POS
    Route::get('/kasir', [KasirController::class, 'index'])->name('kasir');
    Route::post('/kasir', [KasirController::class, 'store']);
    Route::get('/kasir/receipt/{sale}', [KasirController::class, 'receipt'])->name('kasir.receipt');
    Route::get('/kasir/print/{sale}', [KasirController::class, 'printReceipt'])->name('kasir.print');

    // Sales History & Reports
    Route::resource('sales', SaleController::class)->only(['index', 'show', 'destroy']);
    Route::get('/sales/daily-report', [SaleController::class, 'dailyReport'])->name('sales.daily-report');
    Route::get('/sales/weekly-report', [SaleController::class, 'weeklyReport'])->name('sales.weekly-report');
    Route::get('/sales/export-csv', [SaleController::class, 'exportCsv'])->name('sales.export-csv');
    Route::get('/sales/print-daily', [SaleController::class, 'printDailyReport'])->name('sales.print-daily');

    // Users Management (only owner)
    Route::middleware('role:owner')->group(function () {
        Route::resource('users', UserController::class);
    });

    // API Routes for AJAX
    Route::prefix('api')->group(function () {
        // Products API
        Route::get('/products/search', [ProductController::class, 'search'])->name('api.products.search');
        Route::get('/products/lookup', [ProductController::class, 'lookupBarcode'])->name('api.products.lookup');

        // Kasir API
        Route::get('/kasir/products', [KasirController::class, 'searchProducts'])->name('api.kasir.products');
        Route::get('/kasir/products/{product}', [KasirController::class, 'getProduct'])->name('api.kasir.product');
        Route::get('/kasir/popular', [KasirController::class, 'popularProducts'])->name('api.kasir.popular');
        Route::get('/kasir/suggestions', [KasirController::class, 'suggestions'])->name('api.kasir.suggestions');
    });
});
