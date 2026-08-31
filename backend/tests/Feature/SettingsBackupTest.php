<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MysqlBinaryLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Backup upload/listing and the mysql client resolution behind backup/restore.
 *
 * The dump and restore themselves shell out to real binaries against a real
 * server, so they are not exercised here — what is covered is everything that
 * decides *whether those calls can even be made correctly*: locating the
 * binary, the child environment, and which files are offered for restore.
 */
class SettingsBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        MysqlBinaryLocator::flush();

        $this->backupPath = storage_path('app/backups');
        if (! is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->backupPath.'/phpunit-*') ?: [] as $file) {
            @unlink($file);
        }

        MysqlBinaryLocator::flush();

        parent::tearDown();
    }

    private function opm(): User
    {
        return User::factory()->create(['role' => 'operations_hr_manager']);
    }

    // ---- Upload -----------------------------------------------------------

    public function test_a_sql_dump_can_be_uploaded_for_restore(): void
    {
        $response = $this->actingAs($this->opm())->postJson('/api/settings/backups/upload', [
            'file' => UploadedFile::fake()->createWithContent('phpunit-dump.sql', "-- dump\nCREATE TABLE t(id INT);\n"),
        ]);

        $response->assertStatus(201)->assertJsonPath('filename', 'phpunit-dump.sql');
        $this->assertFileExists($this->backupPath.'/phpunit-dump.sql');
    }

    public function test_a_non_backup_file_is_rejected(): void
    {
        $this->actingAs($this->opm())->postJson('/api/settings/backups/upload', [
            'file' => UploadedFile::fake()->createWithContent('phpunit-notes.txt', 'not a backup'),
        ])->assertStatus(422);

        $this->assertFileDoesNotExist($this->backupPath.'/phpunit-notes.txt');
    }

    public function test_an_uploaded_filename_cannot_escape_the_backup_directory(): void
    {
        // The stored name is later concatenated into a path by restore/download,
        // so a traversal attempt must not survive as one.
        $response = $this->actingAs($this->opm())->postJson('/api/settings/backups/upload', [
            'file' => UploadedFile::fake()->createWithContent('phpunit-../../evil.sql', 'x'),
        ]);

        $response->assertStatus(201);

        $stored = $response->json('filename');
        $this->assertStringNotContainsString('..', $stored);
        $this->assertStringNotContainsString('/', $stored);
        $this->assertStringNotContainsString('\\', $stored);
        $this->assertFileExists($this->backupPath.'/'.$stored);
        @unlink($this->backupPath.'/'.$stored);
    }

    public function test_only_opm_can_upload_a_backup(): void
    {
        foreach (['staff', 'finance_manager', 'executive_director'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->postJson('/api/settings/backups/upload', [
                    'file' => UploadedFile::fake()->createWithContent('phpunit-dump.sql', '-- dump'),
                ])->assertStatus(403);
        }
    }

    // ---- Listing ----------------------------------------------------------

    public function test_the_listing_hides_files_that_could_never_be_restored(): void
    {
        file_put_contents($this->backupPath.'/phpunit-real.sql', '-- dump');
        file_put_contents($this->backupPath.'/phpunit-stray.log', 'noise');

        $names = collect($this->actingAs($this->opm())->getJson('/api/settings/backups')->json())
            ->pluck('name');

        $this->assertTrue($names->contains('phpunit-real.sql'));
        $this->assertFalse($names->contains('phpunit-stray.log'));
    }

    public function test_a_backup_for_another_engine_is_flagged_as_not_restorable(): void
    {
        file_put_contents($this->backupPath.'/phpunit-mysql.sql', '-- dump');
        file_put_contents($this->backupPath.'/phpunit-sqlite.sqlite', 'x');

        $rows = collect($this->actingAs($this->opm())->getJson('/api/settings/backups')->json())
            ->keyBy('name');

        // The suite runs on sqlite, so the .sqlite dump is the restorable one.
        $this->assertTrue($rows['phpunit-sqlite.sqlite']['restorable']);
        $this->assertFalse($rows['phpunit-mysql.sql']['restorable']);
    }

    // ---- Client resolution -------------------------------------------------

    public function test_a_misconfigured_binary_path_fails_with_an_actionable_message(): void
    {
        config(['database.mysql_bin_path' => $this->backupPath]);
        MysqlBinaryLocator::flush();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/MYSQL_BIN_PATH/');

        MysqlBinaryLocator::dump();
    }

    public function test_the_client_environment_carries_the_password_out_of_band(): void
    {
        // On the command line the password would be visible in the process list.
        $environment = MysqlBinaryLocator::environment('s3cret');

        $this->assertSame('s3cret', $environment['MYSQL_PWD']);
    }

    public function test_the_client_environment_preserves_the_os_variables_the_client_needs(): void
    {
        // Without these the child process launches with an environment too bare
        // for Winsock to initialise under `artisan serve`, and mysqldump dies
        // with "Can't create TCP/IP socket (10106)".
        $environment = MysqlBinaryLocator::environment(null);

        foreach (['SystemRoot', 'PATH'] as $name) {
            if (getenv($name) !== false && getenv($name) !== '') {
                $this->assertArrayHasKey($name, $environment, "{$name} must be passed through to the client process.");
            }
        }
    }
}
