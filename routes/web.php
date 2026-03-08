<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use Illuminate\Http\Request;

// Public routes (guest only)
Route::middleware('guest')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome', [
            'canLogin' => true,
            'canRegister' => true,
            'laravelVersion' => app()->version(),
            'phpVersion' => PHP_VERSION,
        ]);
    })->name('home');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// Protected routes (require authentication)
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // User info (available via Inertia shared data, but keep for API if needed)
    Route::get('/user', function (Request $request) {
        return $request->user()->load(['roles', 'branch', 'store']);
    })->name('user.info');

    // Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Sales Routes
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [SalesController::class, 'index'])->name('index');
        Route::get('/create', [SalesController::class, 'create'])->name('create');
        Route::post('/', [SalesController::class, 'store'])->name('store');
        Route::get('/{sale}', [SalesController::class, 'show'])->name('show');
        Route::get('/{sale}/edit', [SalesController::class, 'edit'])->name('edit');
        Route::put('/{sale}', [SalesController::class, 'update'])->name('update');
        Route::delete('/{sale}', [SalesController::class, 'destroy'])->name('destroy');
    });

    // Transfers Routes
    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::get('/', [TransferController::class, 'index'])->name('index');
        Route::get('/create', [TransferController::class, 'create'])->name('create');
        Route::post('/', [TransferController::class, 'store'])->name('store');
        Route::get('/{transfer}', [TransferController::class, 'show'])->name('show');
        Route::post('/{transfer}/approve', [TransferController::class, 'approve'])->name('approve');
        Route::post('/{transfer}/receive', [TransferController::class, 'receive'])->name('receive');
        Route::get('/{transfer}/edit', [TransferController::class, 'edit'])->name('edit');
        Route::put('/{transfer}', [TransferController::class, 'update'])->name('update');
        Route::delete('/{transfer}', [TransferController::class, 'destroy'])->name('destroy');
    });

    // Inventory Routes
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('/{store}', [InventoryController::class, 'show'])->name('show');
        // Route::post('/adjustments', [InventoryController::class, 'adjust'])->name('adjust');
        Route::get('/movements/history', [InventoryController::class, 'history'])->name('history');
        Route::get('/low-stock/alerts', [InventoryController::class, 'lowStock'])->name('low-stock');

        Route::get('/adjustment/create', [InventoryController::class, 'createAdjustment'])->name('inventory.adjustment.create');
        Route::post('/adjustment', [InventoryController::class, 'adjust'])->name('inventory.adjustment.store');

        Route::post('/check-availability', [InventoryController::class, 'checkAvailability']);
        Route::post('/check-single-availability', [InventoryController::class, 'checkSingleProductAvailability']);

    });

    // Reports Routes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/stock-valuation', [ReportController::class, 'stockValuation'])->name('stock-valuation');
        Route::get('/movement-history', [ReportController::class, 'movementHistory'])->name('movement-history');
        Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('/products', [ReportController::class, 'products'])->name('products');
    });

    // Master Data Routes
    // Route::prefix('master')->name('master.')->group(function () {
        // Stores
        Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');
        Route::get('/stores/{store}', [StoreController::class, 'show'])->name('stores.show');
        Route::get('/api/stores/list', [StoreController::class, 'list'])->name('stores.list');
        
        // // Products
        // Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        // Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
        
        // // Customers
        // Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        // Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // });
});

// Keep API routes separate for mobile/third-party apps (optional)
Route::prefix('api')->group(function () {
    // You can keep your API routes here if needed
    require __DIR__.'/api.php';
});