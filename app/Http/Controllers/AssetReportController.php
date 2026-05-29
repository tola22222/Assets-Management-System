<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;

class AssetReportController extends Controller
{
    public function index()
    {
        // Fetch assets with relationships
        $assets = Asset::with(['category', 'stocks.location'])->get();

        // Send to view
        return view('reports.index', compact('assets'));
    }
}