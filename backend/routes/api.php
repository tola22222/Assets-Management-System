<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AssetAssignmentController;
use App\Http\Controllers\Api\AssetCategoryController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetDisposalController;
use App\Http\Controllers\Api\AssetImportController;
use App\Http\Controllers\Api\AssetReturnController;
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
use App\Http\Controllers\Api\StockItemController;
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

        Route::post('/assets/{asset}/regenerate-qr', [AssetController::class, 'regenerateQr']);
        Route::post('/assets/{asset}/flag', [AssetController::class, 'flagIssue']);
        Route::apiResource('assets', AssetController::class)->only(['index', 'show']);

        Route::apiResource('locations', LocationController::class)->only(['index', 'show']);

        Route::middleware('role:operations_hr_manager')->group(function () {
            // Bulk import — defined before the resource so "import" is not treated as an {asset}.
            Route::get('/assets/import/template', [AssetImportController::class, 'template']);
            Route::post('/assets/import', [AssetImportController::class, 'store']);
            Route::apiResource('assets', AssetController::class)->only(['store', 'destroy']);
            Route::apiResource('locations', LocationController::class)->only(['store', 'update', 'destroy']);
        });
        Route::middleware('role:operations_hr_manager,finance_manager')->group(function () {
            Route::apiResource('assets', AssetController::class)->only(['update']);
        });

        Route::apiResource('categories', AssetCategoryController::class)->only(['index']);
        Route::middleware('role:operations_hr_manager')->group(function () {
            Route::apiResource('categories', AssetCategoryController::class)->only(['store', 'update', 'destroy']);
        });

        Route::get('/asset-assignments/{asset_assignment}/history', [AssetAssignmentController::class, 'history']);
        Route::apiResource('asset-assignments', AssetAssignmentController::class)->only(['index']);
        Route::middleware('role:operations_hr_manager,finance_manager')->group(function () {
            Route::post('/asset-assignments/{asset_assignment}/cancel', [AssetAssignmentController::class, 'cancel']);
            Route::post('/asset-assignments/{asset_assignment}/return', [AssetAssignmentController::class, 'returnAsset']);
            Route::apiResource('asset-assignments', AssetAssignmentController::class)->only(['store', 'update', 'destroy']);
        });

        Route::middleware('role:operations_hr_manager')->group(function () {
            Route::post('/asset-transfers/{asset_transfer}/approve', [AssetTransferController::class, 'approve']);
            Route::post('/asset-transfers/{asset_transfer}/reject', [AssetTransferController::class, 'reject']);
        });
        Route::apiResource('asset-transfers', AssetTransferController::class)->only(['index', 'store', 'destroy']);

        Route::middleware('role:operations_hr_manager')->group(function () {
            Route::post('/asset-returns/{asset_return}/approve', [AssetReturnController::class, 'approve']);
            Route::post('/asset-returns/{asset_return}/reject', [AssetReturnController::class, 'reject']);
        });
        Route::apiResource('asset-returns', AssetReturnController::class)->only(['index', 'store']);

        Route::post('/asset-verifications/{asset_verification}/complete', [AssetVerificationController::class, 'complete'])->middleware('role:operations_hr_manager');
        Route::apiResource('asset-verifications', AssetVerificationController::class)->only(['index']);
        // Staff submit condition reports only through the QR scan flow (/qr-scan/{code}/verify
        // above), never this direct endpoint — it would let them bypass the own-site restriction.
        Route::middleware('role:operations_hr_manager,finance_manager')->group(function () {
            Route::apiResource('asset-verifications', AssetVerificationController::class)->only(['store']);
        });
        Route::middleware('role:operations_hr_manager')->group(function () {
            Route::apiResource('asset-verifications', AssetVerificationController::class)->only(['destroy']);
        });

        Route::post('/asset-disposals/{asset_disposal}/approve', [AssetDisposalController::class, 'approve'])->middleware('role:operations_hr_manager,executive_director');
        Route::post('/asset-disposals/{asset_disposal}/reject', [AssetDisposalController::class, 'reject'])->middleware('role:operations_hr_manager,executive_director');
        Route::apiResource('asset-disposals', AssetDisposalController::class)->only(['index', 'store', 'destroy']);

        Route::apiResource('programs', ProgramController::class)->only(['index']);
        Route::apiResource('suppliers', SupplierController::class)->only(['index']);
        Route::apiResource('staff', StaffController::class)->except(['create', 'show', 'edit']);
        Route::middleware('role:operations_hr_manager')->group(function () {
            Route::apiResource('programs', ProgramController::class)->only(['store', 'update', 'destroy']);
        });
        Route::middleware('role:operations_hr_manager,finance_manager')->group(function () {
            Route::apiResource('suppliers', SupplierController::class)->only(['store', 'update', 'destroy']);
        });

        // Stock (consumables) — every role can view the grid/dashboard; only
        // OPM/Finance can receive, issue, or delete, mirroring suppliers above.
        Route::get('/stock-items/by-category', [StockItemController::class, 'byCategory']);
        Route::apiResource('stock-items', StockItemController::class)->only(['index', 'show']);
        Route::middleware('role:operations_hr_manager,finance_manager')->group(function () {
            Route::post('/stock-items/receive', [StockItemController::class, 'receive']);
            Route::post('/stock-items/{stock_item}/issue', [StockItemController::class, 'issue']);
            Route::delete('/stock-items/{stock_item}', [StockItemController::class, 'destroy']);
        });

        // Staff cannot pull reports — matches the manual's counting process, which is
        // led by OPM and verified by Finance, with the ED reading summaries only.
        Route::middleware('role:operations_hr_manager,finance_manager,executive_director')->group(function () {
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
            Route::post('/reports/email', [ReportController::class, 'email']);
        });

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
