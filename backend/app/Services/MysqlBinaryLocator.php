<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Finds the `mysqldump` / `mysql` client binaries that backup and restore shell
 * out to.
 *
 * In the production Docker image these are on PATH, so the bare name works. On
 * a developer machine they usually are not — DBngin, XAMPP, Laragon, WAMP and
 * the official installers all keep them inside their own directory and none of
 * them touch PATH. The symptom is a backup failing with
 * "'mysqldump' is not recognized as an internal or external command", which
 * says nothing about how to fix it.
 *
 * Resolution order: an explicit MYSQL_BIN_PATH, then PATH, then the well-known
 * install directories. If all three miss, the error names MYSQL_BIN_PATH so the
 * admin knows the lever to pull.
 */
class MysqlBinaryLocator
{
    /** Cache per binary — resolution can shell out, and both backup and restore call it. */
    private static array $resolved = [];

    public static function dump(): string
    {
        return self::locate('mysqldump');
    }

    public static function client(): string
    {
        return self::locate('mysql');
    }

    /** Only for tests — the static cache would otherwise outlive a changed config. */
    public static function flush(): void
    {
        self::$resolved = [];
    }

    /**
     * Environment for a mysqldump/mysql child process.
     *
     * Symfony builds a child's environment by intersecting getenv() with
     * $_SERVER. Under `php artisan serve` the SAPI is cli-server and PHP's
     * variables_order is typically "GPCS" — no "E" — so $_SERVER carries no OS
     * variables at all and that intersection comes out empty. The client then
     * launches without SystemRoot, Winsock cannot initialise its providers, and
     * mysqldump fails with "Got error: 2004: Can't create TCP/IP socket (10106)"
     * even though the identical command works from the CLI. Passing the handful
     * of variables the client genuinely needs sidesteps the whole thing.
     *
     * MYSQL_PWD keeps the password off the process command line, where it would
     * otherwise be readable from the process list.
     */
    public static function environment(?string $password): array
    {
        $environment = ['MYSQL_PWD' => (string) $password];

        foreach (['SystemRoot', 'windir', 'PATH', 'TEMP', 'TMP', 'APPDATA', 'HOME'] as $name) {
            $value = getenv($name);
            if ($value !== false && $value !== '') {
                $environment[$name] = $value;
            }
        }

        return $environment;
    }

    private static function locate(string $binary): string
    {
        if (isset(self::$resolved[$binary])) {
            return self::$resolved[$binary];
        }

        return self::$resolved[$binary] = self::resolve($binary);
    }

    private static function resolve(string $binary): string
    {
        $filename = $binary.(self::onWindows() ? '.exe' : '');

        // 1. Explicitly configured directory wins, and a wrong value is an
        //    error rather than a silent fallback — otherwise a typo here shows
        //    up later as a confusing "not found on PATH" message instead.
        $configured = config('database.mysql_bin_path');
        if (filled($configured)) {
            $path = rtrim($configured, '\\/').DIRECTORY_SEPARATOR.$filename;

            if (is_file($path)) {
                return $path;
            }

            throw new RuntimeException(
                "MYSQL_BIN_PATH is set to \"{$configured}\" but {$filename} is not in that directory."
            );
        }

        // 2. On PATH (the Docker image, and most Linux/macOS setups).
        if (self::isRunnable($binary)) {
            return $binary;
        }

        // 3. Known install locations.
        foreach (self::candidateDirectories() as $directory) {
            $path = $directory.DIRECTORY_SEPARATOR.$filename;
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException(
            "Could not find \"{$filename}\": it is not on PATH and not in any known MySQL install directory. ".
            'Set MYSQL_BIN_PATH in backend/.env to the folder containing it '.
            '(the "bin" directory of your MySQL, DBngin, XAMPP or Laragon install).'
        );
    }

    private static function isRunnable(string $binary): bool
    {
        try {
            return Process::timeout(10)->run([$binary, '--version'])->successful();
        } catch (\Throwable) {
            // A missing binary surfaces as an exception on some platforms and a
            // non-zero exit on others; both just mean "not on PATH".
            return false;
        }
    }

    /**
     * Directories to search, newest-looking first. Globs are expanded so
     * version-numbered folders (DBngin, Laragon, Program Files) are matched
     * without hardcoding a version.
     */
    private static function candidateDirectories(): array
    {
        $patterns = self::onWindows()
            ? [
                // DBngin keeps every engine under LOCALAPPDATA, versioned.
                getenv('LOCALAPPDATA').'\\com.tinyapp.DBngin\\Binaries\\mysql\\*\\bin',
                'C:\\xampp\\mysql\\bin',
                'C:\\laragon\\bin\\mysql\\*\\bin',
                'C:\\wamp64\\bin\\mysql\\*\\bin',
                'C:\\Program Files\\MySQL\\*\\bin',
                'C:\\Program Files\\MariaDB*\\bin',
            ]
            : [
                getenv('HOME').'/Library/Application Support/com.tinyapp.DBngin/Binaries/mysql/*/bin',
                '/usr/local/mysql/bin',
                '/opt/homebrew/opt/mysql*/bin',
                '/usr/local/opt/mysql*/bin',
                '/opt/lampp/bin',
            ];

        $directories = [];
        foreach ($patterns as $pattern) {
            $matches = glob($pattern, GLOB_ONLYDIR) ?: [];
            rsort($matches); // highest version first
            $directories = array_merge($directories, $matches);
        }

        return $directories;
    }

    private static function onWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
