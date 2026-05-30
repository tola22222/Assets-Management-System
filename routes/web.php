<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
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
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\AssetReportController;

// --- GUEST ROUTES ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// --- PROTECTED ROUTES ---
Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::resource('asset-categories', AssetCategoryController::class)->names('categories');
    Route::resource('assets-registeration', AssetController::class)->names('assets');
    Route::resource('locations', LocationController::class)->names('assets-locations');
    Route::resource('asset-movements', AssetMovementController::class);
    Route::resource('asset-stocks', AssetStockController::class);
    Route::resource('programs', ProgramController::class);
    Route::resource('staff', StaffController::class);
    Route::resource('asset-assignments', AssetAssignmentController::class); // removed duplicate get
    Route::resource('asset-verifications', AssetVerificationController::class);
    Route::resource('suppliers', SupplierController::class);                // removed duplicate get
    Route::resource('reports', ReportController::class);                    // removed duplicate outside auth
});