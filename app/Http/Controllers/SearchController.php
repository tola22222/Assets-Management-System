<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\Location;
use App\Models\AssetCategory;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = $request->get('q', '');

        if (strlen($q) < 2) {
            return redirect()->back()->with('error', 'Please enter at least 2 characters.');
        }

        $user = Auth::user();
        $isAdmin = $user->isOperationsHrManager();

        $results = [];

        $results['assets'] = Asset::where(function ($query) use ($q) {
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

        $results['categories'] = AssetCategory::where(function ($query) use ($q) {
            $query->where('name', 'LIKE', "%{$q}%")
                  ->orWhere('short_name', 'LIKE', "%{$q}%")
                  ->orWhere('description', 'LIKE', "%{$q}%");
        })->limit(5)->get();

        $results['suppliers'] = Supplier::where(function ($query) use ($q) {
            $query->where('name', 'LIKE', "%{$q}%")
                  ->orWhere('phone', 'LIKE', "%{$q}%")
                  ->orWhere('address', 'LIKE', "%{$q}%");
        })->limit(5)->get();

        $results['locations'] = Location::where(function ($query) use ($q) {
            $query->where('name', 'LIKE', "%{$q}%")
                  ->orWhere('type', 'LIKE', "%{$q}%")
                  ->orWhere('description', 'LIKE', "%{$q}%");
        })->limit(5)->get();

        $results['programs'] = Program::where(function ($query) use ($q) {
            $query->where('name', 'LIKE', "%{$q}%")
                  ->orWhere('description', 'LIKE', "%{$q}%");
        })->limit(5)->get();

        if ($isAdmin) {
            $results['users'] = User::where(function ($query) use ($q) {
                $query->where('name', 'LIKE', "%{$q}%")
                      ->orWhere('email', 'LIKE', "%{$q}%")
                      ->orWhere('phone', 'LIKE', "%{$q}%")
                      ->orWhere('role', 'LIKE', "%{$q}%");
            })->limit(5)->get();
        }

        return view('search.index', compact('q', 'results'));
    }
}
