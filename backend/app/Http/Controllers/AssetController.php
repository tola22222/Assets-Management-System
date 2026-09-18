<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Location;

class AssetController extends Controller
{
    /**
     * What a printed QR tag opens: the asset's details, no sign-in needed.
     * The page carries the "Report Condition" form but cannot save anything
     * itself — there is no web POST route. The form only appears once the
     * browser holds a login token, and it submits to Api\QrScanController, so
     * every scan, verification and location change is recorded against a named
     * account.
     */
    public function publicShow($assetCode)
    {
        $asset = Asset::with(['category', 'location', 'assignments' => function ($q) {
            $q->with('assignee')->latest();
        }])->where('asset_code', $assetCode)->firstOrFail();
        $locations = Location::all();

        return view('assets.public-show', compact('asset', 'locations'));
    }
}
