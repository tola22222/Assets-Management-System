<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A real home for QR scan activity.
 *
 * Scans used to be written as `notifications` rows of type `qr_scan` — a row
 * addressed to the very person who scanned, carrying nothing but a sentence.
 * The QR Scans report read those back, so it could never say WHO scanned,
 * what they did next, or where the asset ended up.
 *
 * One row per event: opening a scanned asset (`scanned`), recording a
 * verification (`verified`), or a verification that also moved the asset
 * (`location_updated`). Asset code/name and the user's name are snapshotted
 * and both foreign keys are SET NULL, so the trail outlives a deleted asset
 * or account — same reasoning as 2026_09_01_090000.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_code');
            $table->string('asset_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('action', 30)->default('scanned');
            // Where the asset was recorded once this event was saved, and — only
            // when the event moved it — where it had been recorded before.
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('previous_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('condition', 20)->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('asset_verification_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['asset_id', 'created_at']);
        });

        $this->backfillFromNotifications();
    }

    /**
     * Carries the old notification-based scan history across so the report
     * doesn't start empty. The only structured data those rows have is the
     * recipient and the "(ASSET-CODE)" suffix of the message; anything that
     * doesn't parse is skipped rather than guessed at. The notifications
     * themselves are left alone.
     */
    private function backfillFromNotifications(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')->where('type', 'qr_scan')->orderBy('id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                if (! preg_match('/^QR scanned: (.*) \(([^()]+)\)$/', (string) $row->message, $m)) {
                    continue;
                }

                $asset = DB::table('assets')->where('asset_code', $m[2])->first();

                DB::table('asset_scans')->insert([
                    'asset_id' => $asset?->id,
                    'asset_code' => $m[2],
                    'asset_name' => $m[1],
                    'user_id' => $row->user_id,
                    'user_name' => DB::table('users')->where('id', $row->user_id)->value('name'),
                    'action' => 'scanned',
                    'location_id' => $asset?->location_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->created_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_scans');
    }
};
