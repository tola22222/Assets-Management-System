<?php

use App\Http\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

// --- PUBLIC ROUTES ---
// These serve the QR-code scan flow (report a condition on a scanned asset) and are
// used regardless of which frontend the rest of the app runs — not part of the
// removed legacy Blade admin UI. Keep them working.
Route::get('/asset/{assetCode}', [AssetController::class, 'publicShow'])->name('asset.public.show');
Route::post('/asset/{assetCode}/update-condition', [AssetController::class, 'publicUpdateCondition'])
    ->middleware('throttle:10,1')
    ->name('asset.public.update-condition');
Route::get('/asset/{assetCode}/update-condition', fn ($code) => redirect()->route('asset.public.show', $code));

// --- VUE 3 SPA ---
// Serve the built Vue app under /app so `php artisan serve` shows the frontend
// with no separate Vite process. Build it with:  cd frontend && npm run build:local
//
// Files are served from frontend/dist (NOT public/) on purpose: a real public/app
// directory makes PHP's built-in server strip "/app" from deep-link paths. Serving
// through this route means Laravel sees the full path and history-mode routing works.
// In the Docker image frontend/dist is absent, so this falls back to public/app,
// though in production nginx serves those static files before Laravel is reached.
Route::get('/app/{path?}', function (string $path = '') {
    $sibling = dirname(base_path()).'/frontend/dist';
    $dist = is_dir($sibling) ? $sibling : public_path('app');

    // Serve a real built asset (js/css/img) with a correct Content-Type.
    if ($path !== '' && is_file($dist.'/'.$path)) {
        $mimes = [
            'js' => 'application/javascript', 'mjs' => 'application/javascript',
            'css' => 'text/css', 'svg' => 'image/svg+xml', 'json' => 'application/json',
            'map' => 'application/json', 'ico' => 'image/x-icon', 'png' => 'image/png',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
        ];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $response = response()->file($dist.'/'.$path);
        if (isset($mimes[$ext])) {
            $response->headers->set('Content-Type', $mimes[$ext]);
        }

        return $response;
    }

    // Otherwise it's a client-side route: return the SPA shell.
    abort_unless(is_file($dist.'/index.html'), 404, 'Build the SPA first:  cd frontend && npm run build:local');

    return response()->file($dist.'/index.html');
})->where('path', '.*')->name('spa');

// Site root shows the Vue frontend.
Route::get('/', fn () => redirect('/app'))->name('dashboard');
