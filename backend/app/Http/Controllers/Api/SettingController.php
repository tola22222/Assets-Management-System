<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\MailConfigService;
use App\Services\MysqlBinaryLocator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Response;
use RuntimeException;

class SettingController extends Controller
{
    /** The only file types restore() knows how to load back. */
    private const BACKUP_EXTENSIONS = ['sql', 'sqlite'];

    public function index()
    {
        $settings = Setting::pluck('value', 'key');

        // The SMTP password is stored encrypted and must never travel to the
        // client, not even masked — the UI only needs to know whether one is on
        // file so it can show "leave blank to keep current".
        $settings['mail_password_set'] = filled($settings['mail_password'] ?? null);
        unset($settings['mail_password']);

        // "Next report due" — the old Blade settings screen showed this and it
        // was lost when that UI was removed. Derived rather than stored: the
        // scheduler only records last_scheduled_report_at, and the due date is
        // that plus the configured interval. Mirrors SendScheduledAssetReport's
        // own Carbon math so the screen and the command can't disagree. Null
        // means nothing has been sent yet, i.e. it goes on the next check.
        $lastSentAt = $settings['last_scheduled_report_at'] ?? null;
        $settings['next_report_due'] = filled($lastSentAt)
            ? Carbon::parse($lastSentAt)
                ->addMonths((int) ($settings['report_interval_months'] ?? 6))
                ->toDateString()
            : null;

        // Which engine is live decides what a backup file even looks like, and
        // the restore mismatch errors ("MySQL-format backup, but connected to
        // sqlite") only make sense if the screen says which one is in use.
        $settings['database_driver'] = config('database.default');

        return response()->json($settings);
    }

    public function branding()
    {
        $branding = Setting::whereIn('key', ['organization_name', 'system_name', 'logo'])
            ->pluck('value', 'key');

        // The logo has to be served from here rather than from index(): index()
        // is OPM-only, but every role's sidebar and the logged-out login screen
        // need to render the logo. Stored as a relative disk path; the client
        // needs a resolvable URL.
        $branding['logo_url'] = filled($branding['logo'] ?? null)
            ? asset('storage/'.$branding['logo'])
            : null;
        unset($branding['logo']);

        return response()->json($branding);
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
            'mail_mailer' => 'nullable|in:smtp,log',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer|min:1|max:65535',
            'mail_encryption' => 'nullable|in:tls,ssl,none',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_from_address' => 'nullable|email',
            'mail_from_name' => 'nullable|string|max:255',
        ]);

        if ($request->has('include_staff_in_reports')) {
            $validated['include_staff_in_reports'] = $request->boolean('include_staff_in_reports') ? '1' : '0';
        }

        // Encrypt the SMTP password at rest, and treat a blank submission as
        // "keep what's already stored" — index() never sends the current value
        // back, so the field arrives empty on every load and would otherwise
        // wipe a working password every time the form is saved.
        if (blank($validated['mail_password'] ?? null)) {
            unset($validated['mail_password']);
        } else {
            $validated['mail_password'] = Crypt::encryptString($validated['mail_password']);
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

        // Rebuild the live mail config so a test send in the very next request
        // uses what was just saved, without waiting for a restart.
        MailConfigService::apply(true);

        return $this->index();
    }

    /**
     * Send a one-off email using the currently saved settings.
     *
     * The whole point of moving SMTP config into the database is that an admin
     * can fix mail without a redeploy — this is how they confirm it worked,
     * rather than needing shell access to run `php artisan app:test-mail`.
     */
    public function testMail(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email']);

        MailConfigService::apply(true);

        if (config('mail.default') === 'log') {
            return response()->json([
                'message' => 'Mail driver is set to "log", so nothing is actually delivered. Set an SMTP host and save before testing.',
            ], 422);
        }

        try {
            Mail::raw(
                "This is a test message from {$this->systemName()}.\n\nIf you received it, outgoing email is configured correctly.",
                fn ($message) => $message->to($validated['email'])->subject('Test email from '.$this->systemName())
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Send failed: '.$e->getMessage(),
            ], 422);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Sent a test email to '.$validated['email'],
        ]);

        return response()->json(['message' => 'Test email sent to '.$validated['email'].'.']);
    }

    private function systemName(): string
    {
        return Setting::where('key', 'system_name')->value('value') ?: config('app.name');
    }

    public function backup()
    {
        $backupPath = storage_path('app/backups');
        if (! is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $connection = config('database.default');

        if ($connection === 'sqlite') {
            $databasePath = database_path('database.sqlite');
            if (! file_exists($databasePath)) {
                return response()->json(['message' => 'Database file not found.'], 422);
            }

            $filename = 'backup-'.date('Y-m-d-His').'.sqlite';
            copy($databasePath, $backupPath.'/'.$filename);
        } else {
            $db = config("database.connections.{$connection}");
            $filename = 'backup-'.date('Y-m-d-His').'.sql';

            try {
                $mysqldump = MysqlBinaryLocator::dump();
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

            $result = Process::timeout(300)
                ->env(MysqlBinaryLocator::environment($db['password']))
                ->run([
                    $mysqldump,
                    '-h', $db['host'],
                    '-P', (string) $db['port'],
                    '-u', $db['username'],
                    // Consistent snapshot without locking the app out mid-dump.
                    '--single-transaction',
                    // Without this the dump carries the source server's GTID
                    // state and refuses to load into any other server, which
                    // would make these backups un-restorable off-box.
                    '--set-gtid-purged=OFF',
                    // A schema-only dump would restore to an app that boots but
                    // silently lost its stored logic.
                    '--routines',
                    '--triggers',
                    '--events',
                    // Dumping tablespace info needs the PROCESS privilege, which
                    // a scoped app user typically lacks.
                    '--no-tablespaces',
                    '--result-file='.$backupPath.'/'.$filename,
                    $db['database'],
                ]);

            if (! $result->successful()) {
                // mysqldump still creates the --result-file before it fails, so
                // without this every failure leaves a 0-byte .sql in the list
                // offering a Restore button that could only destroy data.
                @unlink($backupPath.'/'.$filename);

                return response()->json(['message' => 'Backup failed: '.trim($result->errorOutput())], 500);
            }
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Backup',
            'description' => 'Created database backup: '.$filename,
        ]);

        return response()->json(['message' => 'Database backed up successfully.', 'filename' => $filename]);
    }

    /**
     * Accept a backup file produced elsewhere — another install, a colleague's
     * export, or one downloaded from this screen before a rebuild. Without this
     * the restore list can only ever contain dumps this very server made, so a
     * downloaded backup could never be put back.
     */
    public function uploadBackup(Request $request)
    {
        $request->validate([
            // Kept as a plain `file` rule: `mimes:sql` leans on the detected MIME
            // type, and a .sql dump is just text — real backups get rejected.
            // The extension is what actually decides restorability, checked below.
            'file' => 'required|file|max:512000',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::BACKUP_EXTENSIONS, true)) {
            return response()->json([
                'message' => 'Only .sql or .sqlite backup files can be uploaded.',
            ], 422);
        }

        $backupPath = storage_path('app/backups');
        if (! is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        // Never trust the client's filename: it lands on disk and is later
        // echoed back into a path by restore/download.
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $name = ltrim($name, '.') ?: 'backup.'.$extension;

        if (file_exists($backupPath.'/'.$name)) {
            $name = pathinfo($name, PATHINFO_FILENAME).'-'.date('Y-m-d-His').'.'.$extension;
        }

        $file->move($backupPath, $name);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Backup',
            'description' => 'Uploaded database backup: '.$name,
        ]);

        return response()->json(['message' => 'Backup uploaded.', 'filename' => $name], 201);
    }

    public function listBackups()
    {
        $backupPath = storage_path('app/backups');
        if (! is_dir($backupPath)) {
            return response()->json([]);
        }

        $files = array_values(array_filter(
            array_diff(scandir($backupPath), ['.', '..']),
            // Anything else in this directory is not restorable, so listing it
            // would just offer the admin a button that cannot work.
            fn ($file) => in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::BACKUP_EXTENSIONS, true)
                && is_file($backupPath.'/'.$file)
        ));

        $files = array_map(fn ($file) => [
            'name' => $file,
            'size' => filesize($backupPath.'/'.$file),
            'date' => date('Y-m-d H:i:s', filemtime($backupPath.'/'.$file)),
            // Lets the UI flag a backup the current connection cannot restore
            // before the admin clicks Restore and gets a 422.
            'restorable' => $this->isRestorable($file),
        ], $files);

        usort($files, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return response()->json(array_values($files));
    }

    private function isRestorable(string $filename): bool
    {
        return str_ends_with(strtolower($filename), '.sqlite')
            === (config('database.default') === 'sqlite');
    }

    public function downloadBackup(string $filename)
    {
        $backupPath = storage_path('app/backups/'.basename($filename));
        if (! file_exists($backupPath)) {
            return response()->json(['message' => 'Backup file not found.'], 404);
        }

        return Response::download($backupPath);
    }

    public function restoreBackup(string $filename)
    {
        $backupPath = storage_path('app/backups/'.basename($filename));
        if (! file_exists($backupPath)) {
            return response()->json(['message' => 'Backup file not found.'], 404);
        }

        $connection = config('database.default');

        if ($connection === 'sqlite') {
            if (! str_ends_with($filename, '.sqlite')) {
                return response()->json(['message' => 'This is a MySQL-format backup, but the app is currently connected to sqlite.'], 422);
            }

            copy($backupPath, database_path('database.sqlite'));
        } else {
            if (str_ends_with($filename, '.sqlite')) {
                return response()->json(['message' => 'This is a sqlite-format backup, but the app is currently connected to MySQL.'], 422);
            }

            $db = config("database.connections.{$connection}");

            try {
                $mysql = MysqlBinaryLocator::client();
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

            $result = Process::timeout(300)
                ->env(MysqlBinaryLocator::environment($db['password']))
                ->input(fopen($backupPath, 'r'))
                ->run([$mysql, '-h', $db['host'], '-P', (string) $db['port'], '-u', $db['username'], $db['database']]);

            if (! $result->successful()) {
                return response()->json(['message' => 'Restore failed: '.trim($result->errorOutput())], 500);
            }
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Restore',
            'description' => 'Restored database from backup: '.$filename,
        ]);

        return response()->json(['message' => 'Database restored successfully.']);
    }

    public function deleteBackup(string $filename)
    {
        $backupPath = storage_path('app/backups/'.basename($filename));
        if (! file_exists($backupPath)) {
            return response()->json(['message' => 'Backup file not found.'], 404);
        }

        unlink($backupPath);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted database backup: '.$filename,
        ]);

        return response()->json(['message' => 'Backup deleted.']);
    }
}
