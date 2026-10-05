<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The icon a category is shown with on the Categories list — one of the
 * app's own icons, by key (AssetCategory::ICONS). Nullable: a category with
 * none shows the default box icon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->string('icon', 32)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
