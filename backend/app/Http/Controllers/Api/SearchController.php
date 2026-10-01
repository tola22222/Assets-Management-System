<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\Program;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = $request->get('q', '');

        if (strlen($q) < 2) {
            return response()->json(['message' => 'Please enter at least 2 characters.'], 422);
        }

        $user = $request->user();
        $isAdmin = $user->isAdministrator();

        $results = [];

        // Staff find only what is at their own site, as on the register.
        $results['assets'] = Asset::visibleTo($user)->where(function ($query) use ($q) {
            $query->where('asset_code', 'LIKE', "%{$q}%")
                ->orWhere('name', 'LIKE', "%{$q}%")
                ->orWhere('description', 'LIKE', "%{$q}%")
                ->orWhere('brand', 'LIKE', "%{$q}%")
                ->orWhere('model', 'LIKE', "%{$q}%")
                ->orWhere('serial_number', 'LIKE', "%{$q}%");
        })->with('category')->limit(5)->get();

        if ($isAdmin) {
            $results['staff'] = Staff::where(function ($query) use ($q) {
                $query->where('full_name', 'LIKE', "%{$q}%")
                    ->orWhere('email', 'LIKE', "%{$q}%")
                    ->orWhere('phone', 'LIKE', "%{$q}%")
                    ->orWhere('position', 'LIKE', "%{$q}%");
            })->limit(5)->get();
        }

        // User accounts are Administration, which is HR's alone.
        if ($user->isOperationsHrManager()) {
            $results['users'] = User::where(function ($query) use ($q) {
                $query->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('email', 'LIKE', "%{$q}%")
                    ->orWhere('phone', 'LIKE', "%{$q}%")
                    ->orWhere('role', 'LIKE', "%{$q}%");
            })->limit(5)->get();
        }

        // Staff: only categories of assets at their own site, as on the
        // Categories page.
        $results['categories'] = AssetCategory::where(function ($query) use ($q) {
            $query->where('name', 'LIKE', "%{$q}%")
                ->orWhere('short_name', 'LIKE', "%{$q}%")
                ->orWhere('description', 'LIKE', "%{$q}%");
        })->when($user->isSiteScoped(), fn ($query) => $query->whereHas('assets', fn ($a) => $a->visibleTo($user)))
            ->limit(5)->get();

        // Suppliers only for accounts allowed to see them (not staff, by default).
        $results['suppliers'] = ! $user->hasPermission('suppliers', 'view') ? [] : Supplier::where(function ($query) use ($q) {
            $query->where('name', 'LIKE', "%{$q}%")
                ->orWhere('phone', 'LIKE', "%{$q}%")
                ->orWhere('address', 'LIKE', "%{$q}%");
        })->limit(5)->get();

        // Staff find only their program's schools and their own program,
        // the same scope as the Programs page.
        $results['locations'] = Location::where(function ($query) use ($q) {
            $query->where('name', 'LIKE', "%{$q}%")
                ->orWhere('type', 'LIKE', "%{$q}%")
                ->orWhere('description', 'LIKE', "%{$q}%");
        })->when($user->isSiteScoped(), fn ($query) => $query->whereKey($user->siteLocationIds()))->limit(5)->get();

        $results['programs'] = Program::visibleTo($user)->where(function ($query) use ($q) {
            $query->where('name', 'LIKE', "%{$q}%")
                ->orWhere('description', 'LIKE', "%{$q}%");
        })->limit(5)->get();

        return response()->json($results);
    }
}
