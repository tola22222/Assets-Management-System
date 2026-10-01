<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which supplier an asset was bought from — picked on the Add Asset
 * form or given by name in the import template's `supplier` column.
 *
 * Optional, and SET NULL on delete: removing a supplier from the Suppliers
 * screen must not delete (or block) the assets bought from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('category_id')
                ->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
