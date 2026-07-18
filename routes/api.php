<?php

use App\Http\Controllers\Api\AssetCategoryController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetStockController;
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
});
