<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The 13 real PEPY sites (office + 12 partner high schools) and the site
     * codes used in the asset tag scheme (PEY-[SITE]-[CATEGORY]-[####]), per
     * the Asset Checking & Counting Manual and the existing fixed-asset
     * register (asset codes like PEY-SR-FAF-0928 already use these codes).
     */
    private const SITES = [
        'SR' => ['name' => 'PEPY Office', 'type' => 'office'],
        'KL' => ['name' => 'Kralanh HS', 'type' => 'program'],
        'SS' => ['name' => 'Sen Sok HS', 'type' => 'program'],
        'VR' => ['name' => 'Varin HS', 'type' => 'program'],
        'BS' => ['name' => 'Banteay Srei HS', 'type' => 'program'],
        'KD' => ['name' => 'Kork Dong HS', 'type' => 'program'],
        'ST' => ['name' => 'Sna Techo 317 HS', 'type' => 'program'],
        'SV' => ['name' => 'Sreyvibol Khae HS', 'type' => 'program'],
        'PR' => ['name' => 'Pong Ro Leu HS', 'type' => 'program'],
        'PT' => ['name' => 'Preah Theat HS', 'type' => 'program'],
        'SK' => ['name' => 'Srae Khva HS', 'type' => 'program'],
        'RO' => ['name' => 'Roeul HS', 'type' => 'program'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('code', 4)->nullable()->unique()->after('name');
        });

        foreach (self::SITES as $code => $site) {
            $existing = DB::table('locations')->where('name', $site['name'])->first();
            if ($existing) {
                DB::table('locations')->where('id', $existing->id)->update(['code' => $code]);

                continue;
            }
            DB::table('locations')->insert([
                'name' => $site['name'],
                'code' => $code,
                'type' => $site['type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
