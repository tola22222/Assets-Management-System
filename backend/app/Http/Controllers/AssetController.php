<?php

namespace App\Http\Controllers;

use App\Models\Asset;

class AssetController extends Controller
{
    /**
     * What a printed QR tag opens: a read-only view of the asset, no sign-in.
     * It deliberately offers no way to change anything — verifying the asset or
     * updating its location happens in the SPA's /qr-scan/{code} page, behind a
     * login, so every change is recorded against a named account
     * (Api\QrScanController).
     */
    public function publicShow($assetCode)
    {
        $asset = Asset::with(['category', 'location', 'assignments' => function ($q) {
            $q->with('assignee')->latest();
        }])->where('asset_code', $assetCode)->firstOrFail();

        return view('assets.public-show', compact('asset'));
    }
}
