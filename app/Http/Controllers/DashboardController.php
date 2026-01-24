<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // This looks for resources/views/dashboard/index.blade.php
        return view('dashboard.index'); 
    }
}
