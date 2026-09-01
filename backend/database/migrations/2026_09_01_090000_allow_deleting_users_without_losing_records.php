<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes user accounts deletable again, without destroying the records they
 * touched.
 *
 * Two separate problems, both about foreign keys pointing at `users`:
 *
 * 1. `activity_logs.user_id` was NOT NULL with ON DELETE NO ACTION, and
 *    AuthController::login writes an activity row on EVERY sign-in. So the
 *    moment an account had been used once it could never be deleted — the
 *    request died with a raw SQLSTATE[23000] 500. That is the bug people hit.
 *
 * 2. The workflow tables went the other way: requested_by / returned_by were
 *    ON DELETE CASCADE, so deleting a person would have quietly deleted their
 *    transfer requests, disposal requests and returns — including approved
 *    ones. In an asset register those rows are organisational history about an
 *    ASSET, not personal data about the requester, and they must outlive the
 *    account.
 *
 * Both become nullable + ON DELETE SET NULL, which is what
 * `notification_logs.recipient_user_id` and `stock_transactions.recorded_by`
 * already do. The record survives with no user attached, and the UI is already
 * null-safe everywhere it renders one (`log.user?.name || 'System'`,
 * `d.requester?.name || 'N/A'`).
 *
 * `notifications.user_id` is deliberately left CASCADE: a notification is
 * addressed to one person and has no meaning once that account is gone.
 */
return new class extends Migration
{
    /** table => [column, foreign key name] */
    private const RELINK = [
        'activity_logs' => ['user_id', 'activity_logs_user_id_foreign'],
        'asset_transfers' => ['requested_by', 'asset_transfers_requested_by_foreign'],
        'asset_disposals' => ['requested_by', 'asset_disposals_requested_by_foreign'],
        'asset_returns' => ['returned_by', 'asset_returns_returned_by_foreign'],
    ];

    public function up(): void
    {
        // Production is MySQL; the test suite runs on sqlite, which cannot drop
        // a foreign key by name at all (RuntimeException from the schema
        // grammar). On sqlite a change() rebuilds the whole table and carries
        // the constraints across, so making the column nullable is both
        // sufficient and all the driver will allow.
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        foreach (self::RELINK as $table => [$column, $fk]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            if ($sqlite) {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->unsignedBigInteger($column)->nullable()->change();
                });

                // change() alone is not enough here: sqlite rebuilds the table
                // from its own introspected DDL, which carries the OLD delete
                // rule straight back in. The column ends up nullable while the
                // constraint still says "no action"/"cascade", so the test suite
                // would keep reproducing the very bug this migration fixes.
                $this->rewriteSqliteDeleteRule($table, $column);

                continue;
            }

            // The constraint has to go before the column can be made nullable,
            // and it is recreated afterwards with the new delete rule.
            Schema::table($table, function (Blueprint $t) use ($fk) {
                $t->dropForeign($fk);
            });

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->unsignedBigInteger($column)->nullable()->change();
            });

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->foreign($column)->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    /**
     * SQLite cannot ALTER a constraint, so the table is rebuilt: copy the
     * CREATE TABLE statement sqlite stores for itself, swap this column's
     * delete rule to SET NULL, and move the rows across.
     *
     * The rewrite is guarded — if the expected clause isn't found the table is
     * left exactly as it was rather than rebuilt from a statement we didn't
     * fully understand.
     */
    private function rewriteSqliteDeleteRule(string $table, string $column): void
    {
        $ddl = DB::table('sqlite_master')->where('type', 'table')->where('name', $table)->value('sql');

        if (! $ddl) {
            return;
        }

        $pattern = '/(foreign key\("'.preg_quote($column, '/').'"\) references users\("id"\) on delete )(no action|cascade|restrict)/i';

        if (! preg_match($pattern, $ddl)) {
            return;
        }

        $newDdl = preg_replace($pattern, '$1set null', $ddl, 1);
        $temp = $table.'__relink';

        // Index definitions live in their own rows and are dropped with the
        // table, so they are replayed against the rebuilt one.
        $indexes = DB::table('sqlite_master')
            ->where('type', 'index')->where('tbl_name', $table)->whereNotNull('sql')
            ->pluck('sql');

        $columns = implode(', ', array_map(
            fn ($c) => '"'.$c.'"',
            Schema::getColumnListing($table)
        ));

        Schema::disableForeignKeyConstraints();

        DB::statement(preg_replace('/^CREATE TABLE "'.preg_quote($table, '/').'"/', 'CREATE TABLE "'.$temp.'"', $newDdl, 1));
        DB::statement("INSERT INTO \"{$temp}\" ({$columns}) SELECT {$columns} FROM \"{$table}\"");
        Schema::drop($table);
        DB::statement("ALTER TABLE \"{$temp}\" RENAME TO \"{$table}\"");

        foreach ($indexes as $indexSql) {
            DB::statement($indexSql);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Rows orphaned while this migration was applied cannot be given an
        // owner back, so they are removed rather than blocking the rollback on
        // a NOT NULL violation. activity_logs is the exception: an audit trail
        // is worth more than a clean rollback, so its rows are kept and the
        // column simply goes back to being restrictive but nullable.
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        foreach (self::RELINK as $table => [$column, $fk]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            if ($sqlite) {
                if ($table !== 'activity_logs') {
                    \Illuminate\Support\Facades\DB::table($table)->whereNull($column)->delete();
                    Schema::table($table, function (Blueprint $t) use ($column) {
                        $t->unsignedBigInteger($column)->nullable(false)->change();
                    });
                }

                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($fk) {
                $t->dropForeign($fk);
            });

            if ($table === 'activity_logs') {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->foreign($column)->references('id')->on('users')->restrictOnDelete();
                });

                continue;
            }

            \Illuminate\Support\Facades\DB::table($table)->whereNull($column)->delete();

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->unsignedBigInteger($column)->nullable(false)->change();
            });

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->foreign($column)->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
