<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        return view('settings.index', compact('settings'));
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

        return redirect()->route('settings.index')->with('success', 'Settings updated successfully.');
    }

    public function backup()
    {
        $databasePath = database_path('database.sqlite');
        if (!file_exists($databasePath)) {
            return redirect()->route('settings.index')->with('error', 'Database file not found.');
        }

        $backupPath = storage_path('app/backups');
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $filename = 'backup-' . date('Y-m-d-His') . '.sqlite';
        copy($databasePath, $backupPath . '/' . $filename);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Backup',
            'description' => 'Created database backup: ' . $filename,
        ]);

        return redirect()->route('settings.index')->with('success', 'Database backed up successfully: ' . $filename);
    }

    public function restore($filename)
    {
        $backupPath = storage_path('app/backups/' . basename($filename));

        if (!file_exists($backupPath)) {
            return redirect()->route('settings.index')->with('error', 'Backup file not found.');
        }

        copy($backupPath, database_path('database.sqlite'));

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Restore',
            'description' => 'Restored database from backup: ' . $filename,
        ]);

        return redirect()->route('settings.index')->with('success', 'Database restored successfully.');
    }

    public function listBackups()
    {
        $backupPath = storage_path('app/backups');
        if (!is_dir($backupPath)) {
            return response()->json([]);
        }

        $files = array_map(function ($file) {
            return [
                'name' => $file,
                'size' => filesize(storage_path('app/backups/' . $file)),
                'date' => date('Y-m-d H:i:s', filemtime(storage_path('app/backups/' . $file))),
            ];
        }, array_diff(scandir($backupPath), ['.', '..']));

        return response()->json(array_values($files));
    }
}
