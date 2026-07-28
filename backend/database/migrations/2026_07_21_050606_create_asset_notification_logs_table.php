<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for AssetNotificationService::send() — one row per
     * recipient per attempt, so HR can see what was sent and what failed.
     * asset_code is a plain string (not a FK) because several event types
     * (missing-fields digest, count reminder, discrepancy digest) aren't
     * about a single asset at all.
     */
    public function up(): void
    {
        Schema::create('asset_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('asset_code')->nullable();
            $table->string('recipient');
            $table->string('subject');
            $table->string('status'); // sent | failed
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_notification_logs');
    }
};
