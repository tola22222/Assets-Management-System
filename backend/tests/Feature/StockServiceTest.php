<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\StockItem;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\AssetCodeService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private function location(string $code = 'SR'): Location
    {
        return Location::where('code', $code)->firstOrFail();
    }

    // ---- Code generation ----------------------------------------------

    public function test_the_first_ever_stock_code_starts_at_0001(): void
    {
        $code = AssetCodeService::nextStockCode();

        $this->assertSame('PEY-STK-0001', $code);
    }

    public function test_stock_codes_increment_from_the_existing_max_and_never_repeat(): void
    {
        AssetCodeService::nextStockCode();
        AssetCodeService::nextStockCode();
        $third = AssetCodeService::nextStockCode();

        $this->assertSame('PEY-STK-0003', $third);
    }

    public function test_concurrent_stock_code_requests_never_collide(): void
    {
        $codes = [];
        for ($i = 0; $i < 15; $i++) {
            $codes[] = AssetCodeService::nextStockCode();
        }

        $this->assertCount(15, array_unique($codes));
        $this->assertSame('PEY-STK-0015', end($codes));
    }

    public function test_stock_sequence_is_independent_of_the_asset_category_sequences(): void
    {
        $category = \App\Models\AssetCategory::create(['name' => 'Computer Equipment', 'short_name' => 'COM']);
        AssetCodeService::nextCode($this->location()->id, $category->id);
        AssetCodeService::nextCode($this->location()->id, $category->id);

        $stockCode = AssetCodeService::nextStockCode();

        $this->assertSame('PEY-STK-0001', $stockCode);
    }

    // ---- Receive (Stock-In) --------------------------------------------

    public function test_receiving_a_brand_new_item_creates_it_with_a_stock_code_and_correct_balance(): void
    {
        $item = (new StockService)->receive([
            'name' => 'A4 Paper',
            'unit' => 'box',
            'quantity' => 10,
            'location_id' => $this->location()->id,
        ]);

        $this->assertSame('PEY-STK-0001', $item->stock_code);
        $this->assertEquals(10, $item->balance);
        $this->assertDatabaseHas('stock_transactions', ['stock_item_id' => $item->id, 'type' => 'in', 'quantity' => 10]);
    }

    public function test_receiving_stock_for_an_existing_item_reuses_it_instead_of_duplicating(): void
    {
        $service = new StockService;
        $first = $service->receive(['name' => 'Toner Cartridge', 'unit' => 'pcs', 'quantity' => 5, 'location_id' => $this->location()->id]);
        $second = $service->receive(['name' => 'toner cartridge', 'unit' => 'pcs', 'quantity' => 3, 'location_id' => $this->location()->id]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->stock_code, $second->stock_code);
        $this->assertEquals(8, $second->balance);
        $this->assertSame(1, StockItem::count());
        $this->assertSame(2, StockTransaction::count());
    }

    public function test_the_same_item_name_at_a_different_site_is_a_separate_stock_item(): void
    {
        $service = new StockService;
        $siteA = $service->receive(['name' => 'A4 Paper', 'unit' => 'box', 'quantity' => 5, 'location_id' => $this->location('SR')->id]);
        $siteB = $service->receive(['name' => 'A4 Paper', 'unit' => 'box', 'quantity' => 5, 'location_id' => $this->location('KL')->id]);

        $this->assertNotSame($siteA->id, $siteB->id);
        $this->assertSame(2, StockItem::count());
    }

    // ---- Issue (Stock-Out) ----------------------------------------------

    public function test_issuing_more_than_the_current_balance_is_blocked(): void
    {
        $service = new StockService;
        $item = $service->receive(['name' => 'Batteries', 'unit' => 'pcs', 'quantity' => 4, 'location_id' => $this->location()->id]);

        $this->expectException(\InvalidArgumentException::class);
        try {
            $service->issue($item, ['quantity' => 5]);
        } finally {
            $this->assertEquals(4, $item->fresh()->balance, 'Balance must be untouched on a blocked issue.');
            $this->assertSame(0, StockTransaction::where('type', 'out')->count(), 'No Stock-Out row should be recorded.');
        }
    }

    public function test_issuing_within_balance_decrements_it_and_logs_a_transaction(): void
    {
        $service = new StockService;
        $item = $service->receive(['name' => 'Batteries', 'unit' => 'pcs', 'quantity' => 10, 'location_id' => $this->location()->id]);

        $issued = $service->issue($item, ['quantity' => 6, 'reason' => 'issued to Kralanh HS']);

        $this->assertEquals(4, $issued->balance);
        $this->assertDatabaseHas('stock_transactions', [
            'stock_item_id' => $item->id, 'type' => 'out', 'quantity' => 6, 'reason' => 'issued to Kralanh HS',
        ]);
    }

    public function test_balance_is_always_re_derivable_by_replaying_the_transaction_log(): void
    {
        $service = new StockService;
        $item = $service->receive(['name' => 'Cleaning Supplies', 'unit' => 'liter', 'quantity' => 20, 'location_id' => $this->location()->id]);
        $service->issue($item, ['quantity' => 7]);
        $service->receive(['name' => 'Cleaning Supplies', 'unit' => 'liter', 'quantity' => 5, 'location_id' => $this->location()->id]);
        $service->issue($item, ['quantity' => 3]);

        $replayed = StockTransaction::where('stock_item_id', $item->id)
            ->get()
            ->reduce(fn ($carry, $t) => $carry + ($t->type === 'in' ? $t->quantity : -$t->quantity), 0);

        $this->assertEquals($replayed, $item->fresh()->balance);
        $this->assertEquals(15, $item->fresh()->balance); // 20 - 7 + 5 - 3
    }

    // ---- Low / Normal / High status -------------------------------------

    public function test_status_is_low_when_balance_is_at_or_below_min_threshold(): void
    {
        $item = StockItem::create([
            'stock_code' => 'PEY-STK-0001', 'name' => 'Paper', 'unit' => 'box',
            'balance' => 5, 'min_threshold' => 5, 'location_id' => $this->location()->id,
        ]);

        $this->assertSame('low', $item->status);
    }

    public function test_status_is_high_only_when_max_threshold_is_set_and_reached(): void
    {
        $withMax = StockItem::create([
            'stock_code' => 'PEY-STK-0001', 'name' => 'Paper', 'unit' => 'box',
            'balance' => 100, 'max_threshold' => 100, 'location_id' => $this->location()->id,
        ]);
        $this->assertSame('high', $withMax->status);

        $withoutMax = StockItem::create([
            'stock_code' => 'PEY-STK-0002', 'name' => 'Pens', 'unit' => 'box',
            'balance' => 100000, 'location_id' => $this->location()->id,
        ]);
        $this->assertSame('normal', $withoutMax->status, 'HIGH must never trigger when max_threshold is unset.');
    }

    public function test_status_is_normal_between_thresholds(): void
    {
        $item = StockItem::create([
            'stock_code' => 'PEY-STK-0001', 'name' => 'Paper', 'unit' => 'box',
            'balance' => 50, 'min_threshold' => 10, 'max_threshold' => 100, 'location_id' => $this->location()->id,
        ]);

        $this->assertSame('normal', $item->status);
    }

    public function test_status_recalculates_live_after_a_stock_out_crosses_the_min_threshold(): void
    {
        $service = new StockService;
        $item = $service->receive(['name' => 'Paper', 'unit' => 'box', 'quantity' => 10, 'min_threshold' => 5, 'location_id' => $this->location()->id]);
        $this->assertSame('normal', $item->status);

        $item = $service->issue($item, ['quantity' => 6]);

        $this->assertSame('low', $item->status);
    }

    // ---- API / role permissions ------------------------------------------

    public function test_staff_can_view_stock_but_cannot_receive_issue_or_delete(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $service = new StockService;
        $item = $service->receive(['name' => 'Paper', 'unit' => 'box', 'quantity' => 10, 'location_id' => $this->location()->id]);

        $this->actingAs($staff)->getJson('/api/stock-items')->assertStatus(200);
        $this->actingAs($staff)->getJson("/api/stock-items/{$item->id}")->assertStatus(200);

        $this->actingAs($staff)->postJson('/api/stock-items/receive', [
            'name' => 'New Item', 'unit' => 'pcs', 'quantity' => 1, 'location_id' => $this->location()->id,
        ])->assertStatus(403);

        $this->actingAs($staff)->postJson("/api/stock-items/{$item->id}/issue", ['quantity' => 1])->assertStatus(403);
        $this->actingAs($staff)->deleteJson("/api/stock-items/{$item->id}")->assertStatus(403);
    }

    public function test_opm_can_receive_and_issue_stock_via_the_api(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);

        $receiveResponse = $this->actingAs($opm)->postJson('/api/stock-items/receive', [
            'name' => 'Printer Paper', 'unit' => 'box', 'quantity' => 20, 'location_id' => $this->location()->id,
        ]);
        $receiveResponse->assertStatus(201);
        $itemId = $receiveResponse->json('id');

        $issueResponse = $this->actingAs($opm)->postJson("/api/stock-items/{$itemId}/issue", [
            'quantity' => 8, 'reason' => 'issued to Office',
        ]);
        $issueResponse->assertStatus(200);
        $issueResponse->assertJsonPath('balance', '12.00');
    }

    public function test_api_blocks_an_over_issue_with_a_422_and_a_clear_message(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $item = (new StockService)->receive(['name' => 'Paper', 'unit' => 'box', 'quantity' => 3, 'location_id' => $this->location()->id]);

        $response = $this->actingAs($opm)->postJson("/api/stock-items/{$item->id}/issue", ['quantity' => 10]);

        $response->assertStatus(422);
        $this->assertStringContainsString('only 3', $response->json('message'));
    }

    public function test_deleting_a_stock_item_with_transaction_history_is_blocked(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $item = (new StockService)->receive(['name' => 'Paper', 'unit' => 'box', 'quantity' => 3, 'location_id' => $this->location()->id]);

        $response = $this->actingAs($opm)->deleteJson("/api/stock-items/{$item->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('stock_items', ['id' => $item->id]);
    }
}
