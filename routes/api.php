<?php

use App\Http\Controllers\Api\AssetAssignmentController;
use App\Http\Controllers\Api\AssetCategoryController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetDisposalController;
use App\Http\Controllers\Api\AssetMovementController;
use App\Http\Controllers\Api\AssetReturnController;
use App\Http\Controllers\Api\AssetStockController;
use App\Http\Controllers\Api\AssetTransferController;
use App\Http\Controllers\Api\AssetVerificationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::post('/assets/{asset}/regenerate-qr', [AssetController::class, 'regenerateQr']);
    Route::apiResource('assets', AssetController::class);

    Route::apiResource('categories', AssetCategoryController::class)->except(['show']);
    Route::apiResource('locations', LocationController::class);
    Route::apiResource('asset-stocks', AssetStockController::class)->except(['show']);

    Route::post('/asset-assignments/{asset_assignment}/cancel', [AssetAssignmentController::class, 'cancel']);
    Route::post('/asset-assignments/{asset_assignment}/return', [AssetAssignmentController::class, 'returnAsset']);
    Route::get('/asset-assignments/{asset_assignment}/history', [AssetAssignmentController::class, 'history']);
    Route::apiResource('asset-assignments', AssetAssignmentController::class)->except(['show']);

    Route::post('/asset-transfers/{asset_transfer}/approve', [AssetTransferController::class, 'approve']);
    Route::post('/asset-transfers/{asset_transfer}/reject', [AssetTransferController::class, 'reject']);
    Route::apiResource('asset-transfers', AssetTransferController::class)->only(['index', 'store', 'destroy']);

    Route::post('/asset-returns/{asset_return}/approve', [AssetReturnController::class, 'approve']);
    Route::post('/asset-returns/{asset_return}/reject', [AssetReturnController::class, 'reject']);
    Route::apiResource('asset-returns', AssetReturnController::class)->only(['index', 'store']);

    Route::post('/asset-verifications/{asset_verification}/complete', [AssetVerificationController::class, 'complete']);
    Route::apiResource('asset-verifications', AssetVerificationController::class)->only(['index', 'store', 'destroy']);

    Route::post('/asset-disposals/{asset_disposal}/approve', [AssetDisposalController::class, 'approve'])->middleware('role:admin,executive_director');
    Route::post('/asset-disposals/{asset_disposal}/reject', [AssetDisposalController::class, 'reject'])->middleware('role:admin,executive_director');
    Route::apiResource('asset-disposals', AssetDisposalController::class)->only(['index', 'store', 'destroy']);

    Route::apiResource('asset-movements', AssetMovementController::class)->only(['index', 'destroy']);
});
