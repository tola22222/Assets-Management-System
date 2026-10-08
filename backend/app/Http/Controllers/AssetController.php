<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Location;
use App\Services\LoginCookie;
use Illuminate\Http\Request;

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
    public function publicShow(Request $request, $assetCode)
    {
        // Only the assignment the asset is out on now — past (returned)
        // assignments are history, not "current".
        $asset = Asset::with(['category', 'location', 'assignments' => function ($q) {
            $q->whereIn('status', AssetAssignment::CURRENT_STATUSES)->latest();
        }])->where('asset_code', $assetCode)->firstOrFail();
        $locations = Location::orderBy('name')->get();

        // A phone that signed in before but whose browser dropped the stored
        // login (Safari does) is recognised by the sign-in cookie, so the page
        // opens ready to edit instead of asking to sign in again. The token is
        // then part of the page, so it must not be cached or shared.
        $sessionToken = LoginCookie::session($request)[0] ?? null;

        return response()
            ->view('assets.public-show', compact('asset', 'locations', 'sessionToken'))
            ->header('Cache-Control', 'no-store, private');
    }
}
