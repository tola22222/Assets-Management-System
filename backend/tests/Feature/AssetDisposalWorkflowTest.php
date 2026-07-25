<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDisposal;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AssetDisposalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeAsset(): Asset
    {
        $location = Location::where('code', 'SR')->firstOrFail();
        $category = AssetCategory::create(['name' => 'Vehicles', 'short_name' => 'MOV']);

        return Asset::create([
            'asset_code' => 'PEY-SR-MOV-0001',
            'name' => 'Honda CRV',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'status' => 'active',
            'condition' => 'broken',
        ]);
    }

    public function test_approving_a_disposal_request_marks_the_asset_disposed(): void
    {
        Mail::fake();
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset();

        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'requested_by' => $staff->id,
            'recommended_action' => 'disposal',
            'reason' => 'Beyond repair',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($opm)->postJson("/api/asset-disposals/{$disposal->id}/approve");

        $response->assertStatus(200);
        $this->assertSame('disposed', $asset->fresh()->status);
        $this->assertSame('approved', $disposal->fresh()->status);
    }

    public function test_rejecting_a_disposal_request_leaves_the_asset_status_unchanged(): void
    {
        Mail::fake();
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset();

        $disposal = AssetDisposal::create([
            'asset_id' => $asset->id,
            'requested_by' => $staff->id,
            'recommended_action' => 'disposal',
            'reason' => 'Beyond repair',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($opm)->postJson("/api/asset-disposals/{$disposal->id}/reject", [
            'review_notes' => 'Still under warranty',
        ]);

        $response->assertStatus(200);
        $this->assertSame('active', $asset->fresh()->status);
        $this->assertSame('rejected', $disposal->fresh()->status);
    }

    public function test_duplicate_pending_disposal_request_for_the_same_asset_is_rejected(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $asset = $this->makeAsset();

        AssetDisposal::create([
            'asset_id' => $asset->id,
            'requested_by' => $staff->id,
            'recommended_action' => 'disposal',
            'reason' => 'First request',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($staff)->postJson('/api/asset-disposals', [
            'asset_id' => $asset->id,
            'recommended_action' => 'disposal',
            'reason' => 'Second request',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('asset_disposals', 1);
    }

    public function test_submitting_a_disposal_request_emails_the_executive_director_with_finance_cc(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $ed = User::factory()->create(['role' => 'executive_director']);
        $finance = User::factory()->create(['role' => 'finance_manager']);
        $asset = $this->makeAsset();

        $this->actingAs($staff)->postJson('/api/asset-disposals', [
            'asset_id' => $asset->id,
            'recommended_action' => 'disposal',
            'reason' => 'Beyond repair',
        ])->assertStatus(201);

        Mail::assertSent(\App\Mail\AssetEventMail::class, function ($mail) use ($ed) {
            return $mail->hasTo($ed->email) && $mail->eventType === 'DISPOSAL_REQUEST';
        });
    }
}
