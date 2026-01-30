<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController; // Add this
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PartnerSchoolController;
use App\Http\Controllers\AssetCategoryController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\AssetStockController;
use App\Http\Controllers\AssetMovementController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\AssetAssignmentController;
use App\Http\Controllers\AssetVerificationController;
use App\Http\Controllers\SupplierController;

Route::get('/test-assets', function() {
    return 'Assets route works!';
});

Route::resource('asset-categories', AssetCategoryController::class)->names('categories');
Route::resource('assets-registeration', AssetController::class)->names('assets');
Route::resource('locations', LocationController::class)->names('assets-locations');
Route::resource('asset-movements', AssetMovementController::class);
Route::resource('programs', ProgramController::class);
Route::resource('staff', StaffController::class);
Route::resource('asset-assignments', AssetAssignmentController::class);
Route::resource('asset-verifications', AssetVerificationController::class);
route::resource('suppliers', SupplierController::class);


// --- GUEST ROUTES (Login Page) ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});



// --- PROTECTED ROUTES (Require Login) ---
Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/manage-assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    route::get('/asset-assignments', [AssetAssignmentController::class, 'index'])->name('asset-assignments.index');
    Route::resource('asset-stocks', AssetStockController::class);

    // Mapping the custom URL to the Controller methods
    // Route::get('/manage-assets', [AssetController::class, 'index'])->name('assets.index');
    // Route::post('/manage-assets/store', [AssetController::class, 'store'])->name('assets.store');
    // Route::delete('/manage-assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
});
