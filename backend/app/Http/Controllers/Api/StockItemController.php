<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Location;
use App\Models\StockItem;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockItemController extends Controller
{
    public function index(Request $request)
    {
        // Staff see only their own site's consumables (none until HR sets it).
        $user = $request->user();

        return response()->json(
            StockItem::with('location')
                ->when($user->isSiteScoped(), fn ($q) => $q->whereIn('location_id', $user->siteLocationIds()))
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * Live tally of the Asset Register grouped by site — not a stored balance,
     * so it can never drift: it's a straight count of assets as they're
     * registered/imported, always exactly matching the register (disposals,
     * deletes, and transfers all show up automatically).
     *
     * It counts AVAILABLE stock: an asset verified lost or broken drops out
     * (Asset::scopeAvailable), and comes back if it is later verified good.
     *
     * Every location is returned, including ones holding nothing — a site
     * sitting at 0 is a meaningful reading on this panel ("nothing has been
     * tagged there yet"), not a row to hide. The null-location bucket is the
     * exception: it's a data-quality warning, so it's only appended when
     * there actually are unplaced assets.
     */
    public function byLocation(Request $request)
    {
        // Staff get just their own site's row — never another site's numbers
        // (and no rows at all until HR sets their location).
        $user = $request->user();
        $scoped = $user->isSiteScoped();

        $counts = Asset::select('location_id', DB::raw('count(*) as total'))
            ->available()
            ->visibleTo($user)
            ->whereNotNull('location_id')
            ->groupBy('location_id')
            ->pluck('total', 'location_id');

        $rows = Location::orderBy('name')
            ->when($scoped, fn ($q) => $q->whereKey($user->siteLocationIds()))
            ->get(['id', 'name', 'code'])
            ->map(fn ($location) => [
                'location_id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'total' => $total = (int) ($counts[$location->id] ?? 0),
                'level' => self::levelFor($total),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $unplaced = $scoped ? 0 : Asset::available()->whereNull('location_id')->count();

        if ($unplaced > 0) {
            $rows[] = ['location_id' => null, 'name' => null, 'code' => null, 'total' => $unplaced, 'level' => self::levelFor($unplaced)];
        }

        return response()->json($rows);
    }

    /**
     * Same thresholds as the "Assets by Model" report (Asset::stockLevelFor),
     * with its 'medium' named 'normal' to match the Stock page's Low / Normal /
     * High filter and the sidebar's ?status= links.
     */
    private static function levelFor(int $total): string
    {
        $level = Asset::stockLevelFor($total);

        return $level === 'medium' ? 'normal' : $level;
    }

    public function show(Request $request, StockItem $stock_item)
    {
        abort_unless($request->user()->canAccessLocation($stock_item->location_id), 404);

        return response()->json(
            $stock_item->load(['location', 'transactions' => fn ($q) => $q->with('recordedBy')->latest('transaction_date')->latest('id')])
        );
    }

    public function issue(Request $request, StockItem $stock_item, StockService $service)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
            'transaction_date' => 'nullable|date',
        ]);
        $validated['recorded_by'] = Auth::id();

        try {
            $item = $service->issue($stock_item, $validated);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Stock Out',
            'description' => "Issued {$validated['quantity']} {$item->unit} of \"{$item->name}\" ({$item->stock_code})".
                (! empty($validated['reason']) ? " — {$validated['reason']}" : '').'.',
        ]);

        return response()->json($item->load('location'));
    }

    public function destroy(StockItem $stock_item)
    {
        if ($stock_item->transactions()->exists()) {
            return response()->json(['message' => 'Cannot delete a stock item that already has transaction history.'], 422);
        }

        $stock_item->delete();

        ActivityLog::createAndNotify([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => "Deleted stock item: {$stock_item->name} ({$stock_item->stock_code}).",
        ]);

        return response()->json(['message' => 'Stock item deleted.']);
    }
}
