<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        $intervalMonths = (int) ($settings['report_interval_months'] ?? 6);
        $lastSentAt = $settings['last_scheduled_report_at'] ?? null;
        $nextReportDue = $lastSentAt
            ? \Illuminate\Support\Carbon::parse($lastSentAt)->addMonths($intervalMonths)
            : null;

        return view('settings.index', compact('settings', 'nextReportDue'));
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

        return redirect()->route('settings.index')->with('success', 'Settings updated successfully.');
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
                return redirect()->route('settings.index')->with('error', 'Database file not found.');
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
                return redirect()->route('settings.index')->with('error', 'Backup failed: ' . trim($result->errorOutput()));
            }
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Backup',
            'description' => 'Created database backup: ' . $filename,
        ]);

        return redirect()->route('settings.index')->with('success', 'Database backed up successfully: ' . $filename);
    }

    public function restore(string $filename)
    {
        $backupPath = storage_path('app/backups/' . basename($filename));

        if (!file_exists($backupPath)) {
            return redirect()->route('settings.index')->with('error', 'Backup file not found.');
        }

        $connection = config('database.default');

        if ($connection === 'sqlite') {
            if (!str_ends_with($filename, '.sqlite')) {
                return redirect()->route('settings.index')->with('error', 'This is a MySQL-format backup, but the app is currently connected to sqlite.');
            }

            copy($backupPath, database_path('database.sqlite'));
        } else {
            if (str_ends_with($filename, '.sqlite')) {
                return redirect()->route('settings.index')->with('error', 'This is a sqlite-format backup, but the app is currently connected to MySQL.');
            }

            $db = config("database.connections.{$connection}");

            $result = Process::timeout(300)
                ->env(['MYSQL_PWD' => $db['password']])
                ->input(fopen($backupPath, 'r'))
                ->run(['mysql', '-h', $db['host'], '-P', (string) $db['port'], '-u', $db['username'], $db['database']]);

            if (!$result->successful()) {
                return redirect()->route('settings.index')->with('error', 'Restore failed: ' . trim($result->errorOutput()));
            }
        }

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
