<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\DashboardController;
use App\Http\Controllers\api\LocationController;
use App\Http\Controllers\api\StaffController;
use App\Http\Controllers\api\ProgramApiController;
use App\Http\Controllers\Api\SupplierApiController;
use App\Http\Controllers\Api\AssetCategoryApiController;
use App\Http\Controllers\Api\AssetAssignmentApiController;
use App\Http\Controllers\Api\AssetApiController;
use App\Http\Controllers\Api\AssetStockApiController;
use App\Http\Controllers\Api\AssetVerificationApiController;

// Login route
Route::post('/login', [AuthController::class, 'login']);
// Logout route (POST method)
Route::post('/logout', [AuthController::class, 'logout'])->middleware('web');
// use App\Http\Controllers\Api\AuthApiController;

// Route::post('/login', [AuthApiController::class, 'login']);
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/logout', [AuthApiController::class, 'logout']);
// });


Route::apiResource('asset-verifications', AssetVerificationApiController::class);
Route::apiResource('asset-stocks', AssetStockApiController::class);
Route::apiResource('assets', AssetApiController::class);

Route::get('asset-assignments', [AssetAssignmentApiController::class, 'index']);
Route::post('asset-assignments', [AssetAssignmentApiController::class, 'store']);

Route::apiResource('asset-categories', AssetCategoryApiController::class);
Route::apiResource('suppliers', SupplierApiController::class);
Route::apiResource('programs', ProgramApiController::class);

Route::post('/login', [AuthController::class, 'login']);
Route::get('/login', [AuthController::class, 'showLogin']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

Route::prefix('locations')->group(function () {
    Route::get('/', [LocationController::class, 'index']);          // List all
    Route::get('/{id}', [LocationController::class, 'show']);       // View one
    Route::post('/', [LocationController::class, 'store']);         // Create
    Route::put('/{id}', [LocationController::class, 'update']);     // Update
    Route::delete('/{id}', [LocationController::class, 'destroy']); // Delete
});


Route::prefix('staff')->group(function () {
    Route::get('/', [StaffController::class, 'index']);             // GET /api/staff
    Route::get('/{id}', [StaffController::class, 'show']);          // GET /api/staff/1
    Route::post('/', [StaffController::class, 'store']);            // POST /api/staff (Multipart)
    Route::post('/{id}', [StaffController::class, 'update']);       // POST /api/staff/1
    Route::delete('/{id}', [StaffController::class, 'destroy']);    // DELETE /api/staff/1
});