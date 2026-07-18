<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(Setting::pluck('value', 'key'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'organization_name' => 'nullable|string|max:255',
            'system_name' => 'nullable|string|max:255',
            'theme_color' => 'nullable|string|max:7',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'qr_size' => 'nullable|integer|min:100|max:1000',
            'locale' => 'nullable|in:en,km',
            'report_interval_months' => 'nullable|integer|min:1|max:24',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'logo'], ['value' => $path]);
        }

        foreach ($validated as $key => $value) {
            if ($key !== 'logo' && $value !== null) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated system settings',
        ]);

        return response()->json(Setting::pluck('value', 'key'));
    }
}
