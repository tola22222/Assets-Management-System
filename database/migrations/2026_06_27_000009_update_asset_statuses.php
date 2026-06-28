<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets_new', function ($table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('asset_categories');
            $table->text('description')->nullable();
            $table->string('model')->nullable();
            $table->string('brand')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->string('condition', 20)->default('good');
            $table->string('status', 20)->default('active');
            $table->string('image_path')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->timestamps();
        });

        $cols = ['id', 'asset_code', 'name', 'category_id', 'description',
            'model', 'brand', 'serial_number', 'purchase_date', 'purchase_price',
            'condition', 'status', 'image_path', 'qr_code_path', 'created_at', 'updated_at'];
        $select = implode(', ', array_map(fn($c) => "`$c`", $cols));

        DB::statement("INSERT INTO assets_new ($select) SELECT $select FROM assets");
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::drop('assets');
        Schema::rename('assets_new', 'assets');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        Schema::create('assets_old', function ($table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('asset_categories');
            $table->text('description')->nullable();
            $table->string('model')->nullable();
            $table->string('brand')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->enum('condition', ['good', 'fair', 'broken', 'lost'])->default('good');
            $table->enum('status', ['active', 'disposed'])->default('active');
            $table->timestamps();
        });

        $cols = ['id', 'asset_code', 'name', 'category_id', 'description',
            'model', 'brand', 'serial_number', 'purchase_date', 'purchase_price',
            'created_at', 'updated_at'];
        $select = implode(', ', array_map(fn($c) => "`$c`", $cols));

        DB::statement("INSERT INTO assets_old ($select) SELECT $select FROM assets");
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::drop('assets');
        Schema::rename('assets_old', 'assets');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
