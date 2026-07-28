<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

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

    public function backup()
    {
        $databasePath = database_path('database.sqlite');
        if (!file_exists($databasePath)) {
            return response()->json(['message' => 'Database file not found.'], 422);
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

        return response()->json(['message' => 'Database backed up successfully.', 'filename' => $filename]);
    }

    public function listBackups()
    {
        $backupPath = storage_path('app/backups');
        if (!is_dir($backupPath)) {
            return response()->json([]);
        }

        $files = array_map(function ($file) use ($backupPath) {
            return [
                'name' => $file,
                'size' => filesize($backupPath . '/' . $file),
                'date' => date('Y-m-d H:i:s', filemtime($backupPath . '/' . $file)),
            ];
        }, array_diff(scandir($backupPath), ['.', '..']));

        usort($files, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return response()->json(array_values($files));
    }

    public function downloadBackup(string $filename)
    {
        $backupPath = storage_path('app/backups/' . basename($filename));
        if (!file_exists($backupPath)) {
            return response()->json(['message' => 'Backup file not found.'], 404);
        }

        return Response::download($backupPath);
    }

    public function restoreBackup(string $filename)
    {
        $backupPath = storage_path('app/backups/' . basename($filename));
        if (!file_exists($backupPath)) {
            return response()->json(['message' => 'Backup file not found.'], 404);
        }

        copy($backupPath, database_path('database.sqlite'));

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Restore',
            'description' => 'Restored database from backup: ' . $filename,
        ]);

        return response()->json(['message' => 'Database restored successfully.']);
    }

    public function deleteBackup(string $filename)
    {
        $backupPath = storage_path('app/backups/' . basename($filename));
        if (!file_exists($backupPath)) {
            return response()->json(['message' => 'Backup file not found.'], 404);
        }

        unlink($backupPath);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted database backup: ' . $filename,
        ]);

        return response()->json(['message' => 'Backup deleted.']);
    }
}
