<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AssetAssignmentController;
use App\Http\Controllers\Api\AssetCategoryController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetDisposalController;
use App\Http\Controllers\Api\AssetImportController;
use App\Http\Controllers\Api\AssetMovementController;
use App\Http\Controllers\Api\AssetReturnController;
use App\Http\Controllers\Api\AssetStockController;
use App\Http\Controllers\Api\AssetTransferController;
use App\Http\Controllers\Api\AssetVerificationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\QrScanController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Every route name in this file is prefixed with "api." so it can never collide
// with routes/web.php's resource names (e.g. both define an "assets" resource) -
// route names must be globally unique or `route:cache` fails to build.
Route::name('api.')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/profile/password', [AuthController::class, 'changePassword']);

        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/by-period', [DashboardController::class, 'byPeriod']);

        // Bulk import — defined before the resource so "import" is not treated as an {asset}.
        Route::get('/assets/import/template', [AssetImportController::class, 'template']);
        Route::post('/assets/import', [AssetImportController::class, 'store']);
        Route::post('/assets/{asset}/regenerate-qr', [AssetController::class, 'regenerateQr']);
        Route::post('/assets/{asset}/flag', [AssetController::class, 'flagIssue']);
        Route::apiResource('assets', AssetController::class);

        Route::apiResource('categories', AssetCategoryController::class)->except(['show']);
        Route::apiResource('locations', LocationController::class);
        Route::apiResource('asset-stocks', AssetStockController::class)->except(['show', 'update']);

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

        Route::post('/asset-disposals/{asset_disposal}/approve', [AssetDisposalController::class, 'approve'])->middleware('role:operations_hr_manager,executive_director');
        Route::post('/asset-disposals/{asset_disposal}/reject', [AssetDisposalController::class, 'reject'])->middleware('role:operations_hr_manager,executive_director');
        Route::apiResource('asset-disposals', AssetDisposalController::class)->only(['index', 'store', 'destroy']);

        Route::apiResource('asset-movements', AssetMovementController::class)->only(['index', 'destroy']);

        Route::apiResource('programs', ProgramController::class)->except(['create', 'show', 'edit']);
        Route::apiResource('staff', StaffController::class)->except(['create', 'show', 'edit']);
        Route::apiResource('suppliers', SupplierController::class)->except(['create', 'show', 'edit']);

        Route::get('/reports/inventory', [ReportController::class, 'inventory']);
        Route::get('/reports/by-model', [ReportController::class, 'byModel']);
        Route::get('/reports/assignments', [ReportController::class, 'assignments']);
        Route::get('/reports/transfers', [ReportController::class, 'transfers']);
        Route::get('/reports/verifications', [ReportController::class, 'verifications']);
        Route::get('/reports/returns', [ReportController::class, 'returns']);
        Route::get('/reports/disposed', [ReportController::class, 'disposed']);
        Route::get('/reports/lost', [ReportController::class, 'lost']);
        Route::get('/reports/locations', [ReportController::class, 'locations']);
        Route::get('/reports/qr-scans', [ReportController::class, 'qrScans']);
        Route::get('/reports/data-completeness', [ReportController::class, 'dataCompleteness']);

        Route::middleware('role:operations_hr_manager')->group(function () {
            Route::apiResource('users', UserController::class)->except(['create', 'show']);
            Route::post('/users/{user}/lock', [UserController::class, 'lock']);
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);

            Route::get('/settings', [SettingController::class, 'index']);
            Route::post('/settings', [SettingController::class, 'update']);
            Route::post('/settings/backup', [SettingController::class, 'backup']);
            Route::get('/settings/backups', [SettingController::class, 'listBackups']);
            Route::get('/settings/backups/{filename}/download', [SettingController::class, 'downloadBackup']);
            Route::post('/settings/backups/{filename}/restore', [SettingController::class, 'restoreBackup']);
            Route::delete('/settings/backups/{filename}', [SettingController::class, 'deleteBackup']);

            Route::apiResource('activity-logs', ActivityLogController::class)->only(['index', 'show', 'destroy']);
        });

        Route::post('/qr-scan', [QrScanController::class, 'scan']);
        Route::get('/qr-scan/{assetCode}', [QrScanController::class, 'result']);
        Route::post('/qr-scan/{assetCode}/verify', [QrScanController::class, 'verify']);

        Route::get('/search', SearchController::class);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/mark-read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    });
});
