<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;




// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User
    Route::get('/user', function (Request $request) {
        return $request->user()->load(['roles', 'branch', 'store']);
    });

    // dashboard stats
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

    // Sales
    Route::apiResource('sales', SalesController::class);
    
    // Transfers
    Route::apiResource('transfers', TransferController::class);
    Route::post('/transfers/{transfer}/approve', [TransferController::class, 'approve']);
    Route::post('/transfers/{transfer}/receive', [TransferController::class, 'receive']);
    
    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/{store}', [InventoryController::class, 'show']);
    Route::post('/inventory/adjustments', [InventoryController::class, 'adjust']);
    Route::get('/inventory/movements/history', [InventoryController::class, 'history']);
    
    // Reports
    Route::get('/reports/stock-valuation', [ReportController::class, 'stockValuation']);
    Route::get('/reports/movement-history', [ReportController::class, 'movementHistory']);
    Route::get('/reports/low-stock', [ReportController::class, 'lowStock']);
    
    // Master data
    // Route::apiResource('stores', StoreController::class)->only(['index', 'show']);
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/stores/list', [StoreController::class, 'list']);
    Route::get('/stores/{store}', [StoreController::class, 'show']);

    // Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    // Product routes
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/list', [ProductController::class, 'list']);
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
    Route::get('/products/categories', [ProductController::class, 'categories']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::post('/products/bulk-update', [ProductController::class, 'bulkUpdate']);
    Route::post('/products/import', [ProductController::class, 'import']);
    Route::get('/products/export/csv', [ProductController::class, 'export']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    Route::apiResource('customers', CustomerController::class)->only(['index', 'show']);
});