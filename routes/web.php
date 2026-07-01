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
use App\Http\Controllers\UserController;
use App\Http\Controllers\AssetTransferController;
use App\Http\Controllers\AssetReturnController;
use App\Http\Controllers\QrScanController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SearchController;

// --- PUBLIC ROUTES ---
Route::get('/asset/{assetCode}', [AssetController::class, 'publicShow'])->name('asset.public.show');
Route::post('/asset/{assetCode}/update-condition', [AssetController::class, 'publicUpdateCondition'])->name('asset.public.update-condition');
Route::get('/asset/{assetCode}/update-condition', fn($code) => redirect()->route('asset.public.show', $code));

// --- GUEST ROUTES ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
});

// --- PROTECTED ROUTES ---
Route::middleware('auth')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/change-password', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Profile
    Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile.show');
    Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Assets
    Route::get('/assets-registeration/{asset}/download-qr', [AssetController::class, 'downloadQr'])->name('assets.download-qr');
    Route::post('/assets-registeration/{asset}/regenerate-qr', [AssetController::class, 'regenerateQr'])->name('assets.regenerate-qr');
    Route::get('/assets-registeration/{asset}/print-qr', [AssetController::class, 'printQr'])->name('assets.print-qr');
    Route::resource('assets-registeration', AssetController::class)->names('assets');

    // QR Scan
    Route::get('/qr-scan', [QrScanController::class, 'index'])->name('qr-scan.index');
    Route::post('/qr-scan', [QrScanController::class, 'scan'])->name('qr-scan.scan');
    Route::get('/qr-scan/{assetCode}', [QrScanController::class, 'result'])->name('qr-scan.result');
    Route::post('/qr-scan/{assetCode}/verify', [QrScanController::class, 'verify'])->name('qr-scan.verify');

    // Categories
    Route::resource('asset-categories', AssetCategoryController::class)->names('categories');

    // Locations
    Route::resource('locations', LocationController::class)->except(['create'])->names('assets-locations');

    // Asset Stocks
    Route::resource('asset-stocks', AssetStockController::class)->except(['create', 'show', 'edit']);

    // Asset Assignments
    Route::resource('asset-assignments', AssetAssignmentController::class)->except(['create']);
    Route::post('/asset-assignments/{assetAssignment}/cancel', [AssetAssignmentController::class, 'cancel'])->name('asset-assignments.cancel');
    Route::post('/asset-assignments/{assetAssignment}/return', [AssetAssignmentController::class, 'returnAsset'])->name('asset-assignments.return');
    Route::get('/asset-assignments/{assetAssignment}/history', [AssetAssignmentController::class, 'history'])->name('asset-assignments.history');

    // Asset Transfers
    Route::resource('asset-transfers', AssetTransferController::class)->except(['create', 'show', 'edit', 'update']);
    Route::post('/asset-transfers/{transfer}/approve', [AssetTransferController::class, 'approve'])->name('asset-transfers.approve');
    Route::post('/asset-transfers/{transfer}/reject', [AssetTransferController::class, 'reject'])->name('asset-transfers.reject');

    // Asset Returns
    Route::get('/asset-returns/create', function () {
        return redirect()->route('asset-returns.index');
    })->name('asset-returns.create');
    Route::resource('asset-returns', AssetReturnController::class)->except(['create', 'edit', 'update', 'destroy']);
    Route::post('/asset-returns/{return}/approve', [AssetReturnController::class, 'approve'])->name('asset-returns.approve');
    Route::put('/asset-returns/{return}/reject', [AssetReturnController::class, 'reject'])->name('asset-returns.reject');

    // Asset Verifications
    Route::resource('asset-verifications', AssetVerificationController::class)->except(['create', 'edit', 'update']);
    Route::post('/asset-verifications/{assetVerification}/complete', [AssetVerificationController::class, 'complete'])->name('asset-verifications.complete');

    // Asset Movements
    Route::resource('asset-movements', AssetMovementController::class)->except(['create', 'store', 'show', 'edit', 'update', 'destroy']);

    // People & Programs
    Route::resource('programs', ProgramController::class)->except(['create', 'show', 'edit']);
    Route::resource('staff', StaffController::class)->except(['create', 'show', 'edit']);

    // Suppliers
    Route::resource('suppliers', SupplierController::class)->except(['create', 'show', 'edit']);

    // Users (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->except(['create', 'show']);
        Route::post('/users/{user}/lock', [UserController::class, 'lock'])->name('users.lock');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    });

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
    Route::get('/reports/assignments', [ReportController::class, 'assignments'])->name('reports.assignments');
    Route::get('/reports/transfers', [ReportController::class, 'transfers'])->name('reports.transfers');
    Route::get('/reports/verifications', [ReportController::class, 'verifications'])->name('reports.verifications');
    Route::get('/reports/returns', [ReportController::class, 'returns'])->name('reports.returns');
    Route::get('/reports/disposed', [ReportController::class, 'disposed'])->name('reports.disposed');
    Route::get('/reports/lost', [ReportController::class, 'lost'])->name('reports.lost');
    Route::get('/reports/locations', [ReportController::class, 'locations'])->name('reports.locations');
    Route::get('/reports/qr-scans', [ReportController::class, 'qrScans'])->name('reports.qr-scans');

    // Settings (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/backup', [SettingController::class, 'backup'])->name('settings.backup');
        Route::get('/settings/restore/{filename}', [SettingController::class, 'restore'])->name('settings.restore');
        Route::get('/settings/backups/list', [SettingController::class, 'listBackups'])->name('settings.backups.list');
    });

    // Activity Logs (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/{activityLog}', [\App\Http\Controllers\ActivityLogController::class, 'show'])->name('activity-logs.show');
        Route::delete('/activity-logs/{activityLog}', [\App\Http\Controllers\ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');
    });

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');

    // Search
    Route::get('/search', SearchController::class)->name('search');
});
