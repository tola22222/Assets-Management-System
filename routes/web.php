<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController; // Add this
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ProductController;

// --- GUEST ROUTES (Login Page) ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// --- PROTECTED ROUTES (Require Login) ---
Route::middleware('auth')->group(function () {
    
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Resources
    Route::resource('schools', SchoolController::class);
    Route::resource('activity-logs', ActivityLogController::class)->only(['index', 'destroy']);
    
    // Standard Routes
    Route::get('/manage-assets', [AssetController::class, 'index'])->name('assets.index');
    // Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/inventories', [InventoryController::class, 'index'])->name('inventories.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::resource('suppliers', SupplierController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('products', ProductController::class);
    Route::resource('purchase-orders', PurchaseOrderController::class);
});