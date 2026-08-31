<?php

namespace Tests\Feature;

use App\Mail\AssetEventMail;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\StockItem;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\AssetCodeService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private function location(string $code = 'SR'): Location
    {
        return Location::where('code', $code)->firstOrFail();
    }

    /**
     * Puts an item on the shelf with an opening Stock-In row — the same shape
     * a historical receive left behind. This stands in for the removed
     * StockService::receive(); there is no longer any production path that
     * creates a StockItem, but issue/status/notification behaviour still has
     * to work against rows that already exist.
     */
    private function stockItem(array $attributes = []): StockItem
    {
        $quantity = $attributes['quantity'] ?? 10;
        unset($attributes['quantity']);

        $item = StockItem::create(array_merge([
            'stock_code' => AssetCodeService::nextStockCode(),
            'name' => 'Paper',
            'unit' => 'box',
            'balance' => $quantity,
            'location_id' => $this->location()->id,
        ], $attributes));

        StockTransaction::create([
            'stock_item_id' => $item->id,
            'type' => 'in',
            'quantity' => $quantity,
            'transaction_date' => now()->toDateString(),
        ]);

        return $item->fresh();
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

    // ---- Stock-In is gone -------------------------------------------------

    public function test_the_receive_stock_route_no_longer_exists(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);

        // 405 rather than 404: the surviving GET /stock-items/{stock_item}
        // route still matches this path, it just refuses POST.
        $this->actingAs($opm)->postJson('/api/stock-items/receive', [
            'name' => 'A4 Paper', 'unit' => 'box', 'quantity' => 10, 'location_id' => $this->location()->id,
        ])->assertStatus(405);

        $this->assertSame(0, StockItem::count(), 'A removed endpoint must not have created anything.');
    }

    // ---- Issue (Stock-Out) ----------------------------------------------

    public function test_issuing_more_than_the_current_balance_is_blocked(): void
    {
        $service = new StockService;
        $item = $this->stockItem(['name' => 'Batteries', 'unit' => 'pcs', 'quantity' => 4]);

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
        $item = $this->stockItem(['name' => 'Batteries', 'unit' => 'pcs', 'quantity' => 10]);

        $issued = $service->issue($item, ['quantity' => 6, 'reason' => 'issued to Kralanh HS']);

        $this->assertEquals(4, $issued->balance);
        $this->assertDatabaseHas('stock_transactions', [
            'stock_item_id' => $item->id, 'type' => 'out', 'quantity' => 6, 'reason' => 'issued to Kralanh HS',
        ]);
    }

    public function test_balance_is_always_re_derivable_by_replaying_the_transaction_log(): void
    {
        $service = new StockService;
        $item = $this->stockItem(['name' => 'Cleaning Supplies', 'unit' => 'liter', 'quantity' => 20]);
        $service->issue($item, ['quantity' => 7]);
        $service->issue($item, ['quantity' => 3]);

        $replayed = StockTransaction::where('stock_item_id', $item->id)
            ->get()
            ->reduce(fn ($carry, $t) => $carry + ($t->type === 'in' ? $t->quantity : -$t->quantity), 0);

        $this->assertEquals($replayed, $item->fresh()->balance);
        $this->assertEquals(10, $item->fresh()->balance); // 20 - 7 - 3
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
        $item = $this->stockItem(['quantity' => 10, 'min_threshold' => 5]);
        $this->assertSame('normal', $item->status);

        $item = $service->issue($item, ['quantity' => 6]);

        $this->assertSame('low', $item->status);
    }

    // ---- Low-stock notification -------------------------------------------

    public function test_issuing_stock_that_crosses_the_min_threshold_emails_opm(): void
    {
        Mail::fake();
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $service = new StockService;
        $item = $this->stockItem(['quantity' => 10, 'min_threshold' => 5]);

        $service->issue($item, ['quantity' => 6]);

        Mail::assertSent(AssetEventMail::class, fn ($mail) => $mail->hasTo($opm->email) && $mail->eventType === 'LOW_STOCK');
    }

    public function test_issuing_stock_that_stays_low_does_not_re_notify(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'operations_hr_manager']);
        $service = new StockService;
        $item = $this->stockItem(['quantity' => 10, 'min_threshold' => 5]);

        $item = $service->issue($item, ['quantity' => 6]); // crosses into low, notifies once
        $service->issue($item, ['quantity' => 1]); // still low, must not notify again

        Mail::assertSent(AssetEventMail::class, fn ($mail) => $mail->eventType === 'LOW_STOCK', 1);
    }

    public function test_issuing_stock_with_no_min_threshold_never_notifies(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'operations_hr_manager']);
        $service = new StockService;
        $item = $this->stockItem(['quantity' => 10]);

        $service->issue($item, ['quantity' => 10]);

        Mail::assertNotSent(AssetEventMail::class, fn ($mail) => $mail->eventType === 'LOW_STOCK');
    }

    // ---- API / role permissions ------------------------------------------

    public function test_staff_can_view_stock_but_cannot_issue_or_delete(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $item = $this->stockItem(['quantity' => 10]);

        $this->actingAs($staff)->getJson('/api/stock-items')->assertStatus(200);
        $this->actingAs($staff)->getJson("/api/stock-items/{$item->id}")->assertStatus(200);

        $this->actingAs($staff)->postJson("/api/stock-items/{$item->id}/issue", ['quantity' => 1])->assertStatus(403);
        $this->actingAs($staff)->deleteJson("/api/stock-items/{$item->id}")->assertStatus(403);
    }

    public function test_opm_can_issue_stock_via_the_api(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $item = $this->stockItem(['name' => 'Printer Paper', 'quantity' => 20]);

        $issueResponse = $this->actingAs($opm)->postJson("/api/stock-items/{$item->id}/issue", [
            'quantity' => 8, 'reason' => 'issued to Office',
        ]);

        $issueResponse->assertStatus(200);
        $issueResponse->assertJsonPath('balance', '12.00');
    }

    public function test_api_blocks_an_over_issue_with_a_422_and_a_clear_message(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $item = $this->stockItem(['quantity' => 3]);

        $response = $this->actingAs($opm)->postJson("/api/stock-items/{$item->id}/issue", ['quantity' => 10]);

        $response->assertStatus(422);
        $this->assertStringContainsString('only 3', $response->json('message'));
    }

    public function test_deleting_a_stock_item_with_transaction_history_is_blocked(): void
    {
        $opm = User::factory()->create(['role' => 'operations_hr_manager']);
        $item = $this->stockItem(['quantity' => 3]);

        $response = $this->actingAs($opm)->deleteJson("/api/stock-items/{$item->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('stock_items', ['id' => $item->id]);
    }

    // ---- Total assets by location -----------------------------------------

    public function test_by_location_returns_a_live_count_of_registered_assets_excluding_disposed(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $category = AssetCategory::create(['name' => 'Furniture & Fixture', 'short_name' => 'FAF']);
        $office = $this->location('SR');

        Asset::create(['asset_code' => 'PEY-SR-FAF-0001', 'name' => 'Chair', 'category_id' => $category->id, 'location_id' => $office->id, 'status' => 'active', 'condition' => 'good']);
        Asset::create(['asset_code' => 'PEY-SR-FAF-0002', 'name' => 'Desk', 'category_id' => $category->id, 'location_id' => $office->id, 'status' => 'active', 'condition' => 'good']);
        Asset::create(['asset_code' => 'PEY-SR-FAF-0003', 'name' => 'Old Desk', 'category_id' => $category->id, 'location_id' => $office->id, 'status' => 'disposed', 'condition' => 'broken']);

        $response = $this->actingAs($staff)->getJson('/api/stock-items/by-location');

        $response->assertStatus(200);
        $row = collect($response->json())->firstWhere('location_id', $office->id);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['total'], 'The disposed asset must not be counted.');
        $this->assertSame('PEPY Office', $row['name']);
        $this->assertSame('SR', $row['code']);
    }

    public function test_by_location_lists_every_site_including_ones_holding_nothing(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->getJson('/api/stock-items/by-location');

        $rows = collect($response->json());
        $this->assertSame(Location::count(), $rows->count());
        $this->assertTrue($rows->every(fn ($row) => $row['total'] === 0));
        $this->assertNotNull($rows->firstWhere('code', 'KL'), 'A site with no assets still has to appear.');
    }

    public function test_by_location_appends_an_unplaced_bucket_only_when_assets_have_no_site(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $category = AssetCategory::create(['name' => 'Computer', 'short_name' => 'COM']);

        $withoutSite = fn () => collect($this->actingAs($staff)->getJson('/api/stock-items/by-location')->json())
            ->firstWhere('location_id', null);

        $this->assertNull($withoutSite(), 'No unplaced bucket while every asset has a site.');

        Asset::create(['asset_code' => 'PEY-SR-COM-0001', 'name' => 'Stray Laptop', 'category_id' => $category->id, 'location_id' => null, 'status' => 'active', 'condition' => 'good']);

        $this->assertSame(1, $withoutSite()['total']);
    }

    public function test_by_location_sorts_the_busiest_site_first(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $category = AssetCategory::create(['name' => 'Computer', 'short_name' => 'COM']);
        $busy = $this->location('KL');

        Asset::create(['asset_code' => 'PEY-SR-COM-0001', 'name' => 'Laptop', 'category_id' => $category->id, 'location_id' => $this->location('SR')->id, 'status' => 'active', 'condition' => 'good']);
        foreach (['0002', '0003', '0004'] as $n) {
            Asset::create(['asset_code' => "PEY-KL-COM-{$n}", 'name' => 'Laptop', 'category_id' => $category->id, 'location_id' => $busy->id, 'status' => 'active', 'condition' => 'good']);
        }

        $rows = $this->actingAs($staff)->getJson('/api/stock-items/by-location')->json();

        $this->assertSame($busy->id, $rows[0]['location_id']);
        $this->assertSame(3, $rows[0]['total']);
    }
}
