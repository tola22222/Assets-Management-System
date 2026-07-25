<?php

use App\Services\AssetCodeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Before the category create/edit forms were restricted to a fixed
     * MOV/FAF/COM/EQU dropdown, the short_name field was free text — some
     * environments ended up with categories like "Vehicle" / "VE" or
     * "Funture" / "FT" that AssetCodeService::nextCode() rejects, so
     * Register Asset throws "Invalid category code" for any asset in that
     * category. This repairs existing rows; it can't stop new ones (the
     * validation fix already does that going forward).
     *
     * Where the category name clearly implies one of the four manual
     * categories, remap it there. Otherwise clear short_name to null so it
     * shows as unset in the Categories screen instead of silently crashing
     * the next asset registration — an admin picks the right code from the
     * now-restricted dropdown.
     */
    public function up(): void
    {
        $validCodes = AssetCodeService::CATEGORY_CODES;

        $categories = DB::table('asset_categories')->get();

        foreach ($categories as $category) {
            $code = strtoupper((string) $category->short_name);
            if (in_array($code, $validCodes, true)) {
                continue;
            }

            $inferred = $this->inferCode((string) $category->name);

            DB::table('asset_categories')->where('id', $category->id)->update([
                'short_name' => $inferred,
                'updated_at' => now(),
            ]);

            if ($inferred === null) {
                Log::warning("Category #{$category->id} (\"{$category->name}\") had an unrecognized short_name \"{$category->short_name}\" that couldn't be auto-mapped to MOV/FAF/COM/EQU — cleared, needs a manual pick in the Categories screen.");
            } else {
                Log::info("Category #{$category->id} (\"{$category->name}\") short_name \"{$category->short_name}\" auto-corrected to \"{$inferred}\".");
            }
        }
    }

    private function inferCode(string $name): ?string
    {
        $n = strtolower($name);

        return match (true) {
            str_contains($n, 'vehicle') || str_contains($n, 'motor') => 'MOV',
            str_contains($n, 'furniture') || str_contains($n, 'fixture') || str_contains($n, 'funture') => 'FAF',
            str_contains($n, 'computer') => 'COM',
            str_contains($n, 'equipment') => 'EQU',
            default => null,
        };
    }

    /**
     * Renaming/clearing short_name is a data repair, not a reversible schema
     * change — the original bad values aren't worth restoring.
     */
    public function down(): void
    {
        //
    }
};
