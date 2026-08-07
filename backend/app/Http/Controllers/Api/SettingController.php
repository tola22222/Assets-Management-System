<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Response;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(Setting::pluck('value', 'key'));
    }

    public function branding()
    {
        return response()->json(
            Setting::whereIn('key', ['organization_name', 'system_name'])->pluck('value', 'key')
        );
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
            'report_recipient_email' => 'nullable|email',
            'include_staff_in_reports' => 'nullable|boolean',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->has('include_staff_in_reports')) {
            $validated['include_staff_in_reports'] = $request->boolean('include_staff_in_reports') ? '1' : '0';
        }

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
        $backupPath = storage_path('app/backups');
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $connection = config('database.default');

        if ($connection === 'sqlite') {
            $databasePath = database_path('database.sqlite');
            if (!file_exists($databasePath)) {
                return response()->json(['message' => 'Database file not found.'], 422);
            }

            $filename = 'backup-' . date('Y-m-d-His') . '.sqlite';
            copy($databasePath, $backupPath . '/' . $filename);
        } else {
            $db = config("database.connections.{$connection}");
            $filename = 'backup-' . date('Y-m-d-His') . '.sql';

            $result = Process::timeout(300)
                ->env(['MYSQL_PWD' => $db['password']])
                ->run([
                    'mysqldump',
                    '-h', $db['host'],
                    '-P', (string) $db['port'],
                    '-u', $db['username'],
                    '--result-file=' . $backupPath . '/' . $filename,
                    $db['database'],
                ]);

            if (!$result->successful()) {
                return response()->json(['message' => 'Backup failed: ' . trim($result->errorOutput())], 500);
            }
        }

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

        $connection = config('database.default');

        if ($connection === 'sqlite') {
            if (!str_ends_with($filename, '.sqlite')) {
                return response()->json(['message' => 'This is a MySQL-format backup, but the app is currently connected to sqlite.'], 422);
            }

            copy($backupPath, database_path('database.sqlite'));
        } else {
            if (str_ends_with($filename, '.sqlite')) {
                return response()->json(['message' => 'This is a sqlite-format backup, but the app is currently connected to MySQL.'], 422);
            }

            $db = config("database.connections.{$connection}");

            $result = Process::timeout(300)
                ->env(['MYSQL_PWD' => $db['password']])
                ->input(fopen($backupPath, 'r'))
                ->run(['mysql', '-h', $db['host'], '-P', (string) $db['port'], '-u', $db['username'], $db['database']]);

            if (!$result->successful()) {
                return response()->json(['message' => 'Restore failed: ' . trim($result->errorOutput())], 500);
            }
        }

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
