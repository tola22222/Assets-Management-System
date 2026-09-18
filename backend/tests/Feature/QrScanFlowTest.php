<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetScan;
use App\Models\AssetTransfer;
use App\Models\Location;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scan a tag → see the asset → sign in → the scan, the verification and any
 * location change are all recorded against that account and show in the report.
 */
class QrScanFlowTest extends TestCase
{
    use RefreshDatabase;

    private function office(): Location
    {
        return Location::where('code', 'SR')->firstOrFail();
    }

    private function otherSite(): Location
    {
        return Location::where('code', '!=', 'SR')->firstOrFail();
    }

    private function makeAsset(string $code = 'PEY-SR-FAF-0001'): Asset
    {
        $category = AssetCategory::firstOrCreate(['short_name' => 'FAF'], ['name' => 'Furniture & Fixture']);

        return Asset::create([
            'asset_code' => $code,
            'name' => 'Office Chair',
            'category_id' => $category->id,
            'location_id' => $this->office()->id,
            'status' => 'active',
            'condition' => 'good',
        ]);
    }

    public function test_the_public_page_shows_the_asset_and_sends_changes_through_login(): void
    {
        $asset = $this->makeAsset();

        // The Report Condition form is on the page but starts hidden, has no web
        // action to post to, and the visible call to action is the sign-in link
        // that returns here afterwards.
        $this->get("/asset/{$asset->asset_code}")
            ->assertOk()
            ->assertSee('Office Chair')
            ->assertSee('/app/login?return='.urlencode('/asset/'.$asset->asset_code), false)
            ->assertSee('<div id="verify-form-card" hidden', false)
            ->assertDontSee('action=', false);
    }

    public function test_nobody_can_change_an_asset_without_signing_in(): void
    {
        $asset = $this->makeAsset();

        // The old anonymous endpoint is gone, not merely hidden.
        $this->post("/asset/{$asset->asset_code}/update-condition", ['condition' => 'lost', 'location_id' => $this->office()->id])
            ->assertStatus(405);

        $this->postJson('/api/qr-scan', ['asset_code' => $asset->asset_code])->assertStatus(401);
        $this->postJson("/api/qr-scan/{$asset->asset_code}/verify", ['location_id' => $this->office()->id, 'condition' => 'lost'])
            ->assertStatus(401);

        $this->assertSame('good', $asset->fresh()->condition);
        $this->assertDatabaseCount('asset_scans', 0);
    }

    public function test_a_scan_is_recorded_against_the_signed_in_user(): void
    {
        $asset = $this->makeAsset();
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Sokha Staff']);

        $this->actingAs($staff)->postJson('/api/qr-scan', ['asset_code' => $asset->asset_code])
            ->assertOk()
            ->assertJsonPath('asset.asset_code', $asset->asset_code)
            ->assertJsonPath('scan.user_name', 'Sokha Staff')
            ->assertJsonPath('can_change_location', true);

        $this->assertDatabaseHas('asset_scans', [
            'asset_id' => $asset->id,
            'user_id' => $staff->id,
            'action' => AssetScan::ACTION_SCANNED,
            'location_id' => $this->office()->id,
        ]);
    }

    public function test_reloading_the_scan_page_does_not_log_the_same_scan_twice(): void
    {
        $asset = $this->makeAsset();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->postJson('/api/qr-scan', ['asset_code' => $asset->asset_code])->assertOk();
        $this->actingAs($staff)->postJson('/api/qr-scan', ['asset_code' => $asset->asset_code])->assertOk();
        $this->assertDatabaseCount('asset_scans', 1);

        $this->travel(5)->minutes();
        $this->actingAs($staff)->postJson('/api/qr-scan', ['asset_code' => $asset->asset_code])->assertOk();
        $this->assertDatabaseCount('asset_scans', 2);
    }

    public function test_verifying_at_the_recorded_location_leaves_the_asset_where_it_is(): void
    {
        $asset = $this->makeAsset();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->postJson("/api/qr-scan/{$asset->asset_code}/verify", [
            'location_id' => $this->office()->id,
            'condition' => 'fair',
            'remark' => 'Armrest loose',
        ])->assertOk()->assertJsonPath('location_changed', false);

        $this->assertSame($this->office()->id, $asset->fresh()->location_id);
        $this->assertDatabaseHas('asset_scans', [
            'asset_id' => $asset->id,
            'user_id' => $staff->id,
            'action' => AssetScan::ACTION_VERIFIED,
            'condition' => 'fair',
            'remark' => 'Armrest loose',
            'previous_location_id' => null,
        ]);
        $this->assertDatabaseHas('asset_verifications', ['asset_id' => $asset->id, 'verified_by' => $staff->id]);
    }

    public function test_choosing_another_location_updates_the_asset_and_logs_both_ends(): void
    {
        $asset = $this->makeAsset();
        $staff = User::factory()->create(['role' => 'staff']);
        $newSite = $this->otherSite();

        $this->actingAs($staff)->postJson("/api/qr-scan/{$asset->asset_code}/verify", [
            'location_id' => $newSite->id,
            'condition' => 'good',
        ])->assertOk()->assertJsonPath('location_changed', true);

        $this->assertSame($newSite->id, $asset->fresh()->location_id);
        $this->assertDatabaseHas('asset_scans', [
            'asset_id' => $asset->id,
            'user_id' => $staff->id,
            'action' => AssetScan::ACTION_LOCATION_UPDATED,
            'previous_location_id' => $this->office()->id,
            'location_id' => $newSite->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $staff->id, 'action' => 'QR Location Update']);
    }

    public function test_site_scoped_staff_cannot_send_an_asset_to_another_site(): void
    {
        $asset = $this->makeAsset();
        $staffMember = Staff::create(['full_name' => 'Site Staff', 'location_id' => $this->office()->id]);
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => $staffMember->id]);

        $this->actingAs($staff)->postJson('/api/qr-scan', ['asset_code' => $asset->asset_code])
            ->assertOk()->assertJsonPath('can_change_location', false);

        $this->actingAs($staff)->postJson("/api/qr-scan/{$asset->asset_code}/verify", [
            'location_id' => $this->otherSite()->id,
            'condition' => 'good',
        ])->assertStatus(422)->assertJsonValidationErrors('location_id');

        $this->assertSame($this->office()->id, $asset->fresh()->location_id);

        $this->actingAs($staff)->postJson("/api/qr-scan/{$asset->asset_code}/verify", [
            'location_id' => $this->office()->id,
            'condition' => 'good',
        ])->assertOk();
    }

    public function test_location_cannot_be_changed_under_an_open_transfer(): void
    {
        $asset = $this->makeAsset();
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $destination = $this->otherSite();

        AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_location_id' => $this->office()->id,
            'to_location_id' => $destination->id,
            'requested_by' => $opm->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($opm)->postJson("/api/qr-scan/{$asset->asset_code}/verify", [
            'location_id' => $destination->id,
            'condition' => 'good',
        ])->assertStatus(422);

        $this->assertSame($this->office()->id, $asset->fresh()->location_id);

        // Verifying it where it still is stays possible while the transfer is open.
        $this->actingAs($opm)->postJson("/api/qr-scan/{$asset->asset_code}/verify", [
            'location_id' => $this->office()->id,
            'condition' => 'good',
        ])->assertOk();
    }

    public function test_the_qr_scans_report_names_the_user_and_what_they_did(): void
    {
        $asset = $this->makeAsset();
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Sokha Staff']);
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);

        $this->actingAs($staff)->postJson('/api/qr-scan', ['asset_code' => $asset->asset_code])->assertOk();
        $this->actingAs($staff)->postJson("/api/qr-scan/{$asset->asset_code}/verify", [
            'location_id' => $this->otherSite()->id,
            'condition' => 'good',
        ])->assertOk();

        $rows = $this->actingAs($opm)->getJson('/api/reports/qr-scans')->assertOk()->assertJsonCount(2)->json();

        $this->assertEqualsCanonicalizing(['scanned', 'location_updated'], array_column($rows, 'action'));
        foreach ($rows as $row) {
            $this->assertSame('Sokha Staff', $row['user']['name']);
            $this->assertSame($asset->asset_code, $row['asset_code']);
            // `message` is the one field the Reports table actually prints.
            $this->assertStringContainsString('Sokha Staff', $row['message']);
            $this->assertStringContainsString($asset->asset_code, $row['message']);
        }

        // Staff still cannot pull reports, this one included.
        $this->actingAs($staff)->getJson('/api/reports/qr-scans')->assertStatus(403);
    }

    public function test_scan_history_survives_the_asset_being_deleted(): void
    {
        $asset = $this->makeAsset();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->postJson('/api/qr-scan', ['asset_code' => $asset->asset_code])->assertOk();
        $asset->delete();

        $this->assertDatabaseHas('asset_scans', ['asset_id' => null, 'asset_code' => 'PEY-SR-FAF-0001', 'asset_name' => 'Office Chair']);
    }
}
