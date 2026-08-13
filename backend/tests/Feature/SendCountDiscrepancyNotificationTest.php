<?php

namespace Tests\Feature;

use App\Mail\AssetEventMail;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendCountDiscrepancyNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAsset(?string $code = null): Asset
    {
        $category = AssetCategory::firstOrCreate(['short_name' => 'FAF'], ['name' => 'Furniture & Fixture']);
        $location = Location::where('code', 'SR')->firstOrFail();

        return Asset::create([
            'asset_code' => $code ?? 'PEY-SR-FAF-0001',
            'name' => 'Office Chair',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'status' => 'active',
            'condition' => 'good',
        ]);
    }

    private function verify(Asset $asset, string $condition, int $quantity = 1): AssetVerification
    {
        return AssetVerification::create([
            'asset_id' => $asset->id,
            'location_id' => $asset->location_id,
            'quantity_verified' => $quantity,
            'condition' => $condition,
            'verified_by' => 1,
            'verified_at' => now(),
        ]);
    }

    public function test_no_discrepancies_sends_nothing_but_still_advances_the_marker(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'operations_hr_manager']);
        $this->verify($this->makeAsset(), 'good');

        $this->artisan('notifications:count-discrepancy')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNotNull(Setting::where('key', 'last_count_discrepancy_check_at')->value('value'));
    }

    public function test_a_broken_or_lost_verification_is_reported_as_a_discrepancy(): void
    {
        Mail::fake();
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $this->verify($this->makeAsset('PEY-SR-FAF-0001'), 'broken');
        $this->verify($this->makeAsset('PEY-SR-FAF-0002'), 'lost');
        $this->verify($this->makeAsset('PEY-SR-FAF-0003'), 'good'); // not a discrepancy

        $this->artisan('notifications:count-discrepancy')->assertSuccessful();

        Mail::assertSent(AssetEventMail::class, function ($mail) use ($opm) {
            return $mail->hasTo($opm->email)
                && $mail->eventType === 'COUNT_DISCREPANCY'
                && $mail->payload['extraData']['count'] === 2;
        });
    }

    public function test_a_quantity_mismatch_is_reported_as_a_discrepancy(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'operations_hr_manager']);
        $this->verify($this->makeAsset(), 'good', quantity: 2);

        $this->artisan('notifications:count-discrepancy')->assertSuccessful();

        Mail::assertSent(AssetEventMail::class, fn ($mail) => $mail->eventType === 'COUNT_DISCREPANCY');
    }

    public function test_finance_manager_is_cced(): void
    {
        Mail::fake();
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $finance = User::factory()->create(['role' => 'finance_manager']);
        $this->verify($this->makeAsset(), 'broken');

        $this->artisan('notifications:count-discrepancy')->assertSuccessful();

        Mail::assertSent(AssetEventMail::class, fn ($mail) => $mail->hasTo($opm->email) && $mail->hasCc($finance->email));
    }

    public function test_a_verification_already_covered_by_a_previous_run_is_not_re_reported(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'operations_hr_manager']);
        $this->verify($this->makeAsset(), 'broken');
        $this->artisan('notifications:count-discrepancy')->assertSuccessful();
        Mail::assertSent(AssetEventMail::class, 1);

        // A second run with no new verifications since the marker must stay silent.
        $this->artisan('notifications:count-discrepancy')->assertSuccessful();
        Mail::assertSent(AssetEventMail::class, 1);
    }
}
