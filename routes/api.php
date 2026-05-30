<?php

// use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\api\AuthController;
// use App\Http\Controllers\api\DashboardController;
// use App\Http\Controllers\api\LocationController;
// use App\Http\Controllers\api\StaffController;
// use App\Http\Controllers\api\ProgramApiController;
// use App\Http\Controllers\Api\SupplierApiController;
// use App\Http\Controllers\Api\AssetCategoryApiController;
// use App\Http\Controllers\Api\AssetAssignmentApiController;
// use App\Http\Controllers\Api\AssetApiController;
// use App\Http\Controllers\Api\AssetStockApiController;
// use App\Http\Controllers\Api\AssetVerificationApiController;

// // Login route (remove duplicate — was defined twice)
// Route::post('/login', [AuthController::class, 'login']);
// Route::get('/login', [AuthController::class, 'showLogin']);
// Route::post('/logout', [AuthController::class, 'logout'])->middleware('web');

// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/dashboard', [DashboardController::class, 'index']);
// });

// // API Resources — add names() to avoid colliding with web.php route names
// Route::apiResource('asset-verifications', AssetVerificationApiController::class)->names('api.asset-verifications');
// Route::apiResource('asset-stocks', AssetStockApiController::class)->names('api.asset-stocks');
// Route::apiResource('assets', AssetApiController::class)->names('api.assets');
// Route::apiResource('asset-assignments', AssetAssignmentApiController::class)->names('api.asset-assignments');
// Route::apiResource('asset-categories', AssetCategoryApiController::class)->names('api.asset-categories');
// Route::apiResource('suppliers', SupplierApiController::class)->names('api.suppliers');
// Route::apiResource('programs', ProgramApiController::class)->names('api.programs');

// Route::prefix('locations')->group(function () {
//     Route::get('/', [LocationController::class, 'index']);
//     Route::get('/{id}', [LocationController::class, 'show']);
//     Route::post('/', [LocationController::class, 'store']);
//     Route::put('/{id}', [LocationController::class, 'update']);
//     Route::delete('/{id}', [LocationController::class, 'destroy']);
// });

// Route::prefix('staff')->group(function () {
//     Route::get('/', [StaffController::class, 'index']);
//     Route::get('/{id}', [StaffController::class, 'show']);
//     Route::post('/', [StaffController::class, 'store']);
//     Route::post('/{id}', [StaffController::class, 'update']);
//     Route::delete('/{id}', [StaffController::class, 'destroy']);
// });