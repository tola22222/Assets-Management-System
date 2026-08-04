<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\StockItem;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockItemController extends Controller
{
    public function index()
    {
        return response()->json(StockItem::with('location')->orderBy('name')->get());
    }

    /**
     * Live tally of the Asset Register grouped by category — not a stored
     * balance, so it can never drift: it's a straight count of assets as
     * they're registered/imported, always exactly matching the register
     * (disposals, deletes, and transfers all show up automatically).
     */
    public function byCategory()
    {
        $rows = Asset::select('category_id', DB::raw('count(*) as total'))
            ->where('status', '!=', 'disposed')
            ->groupBy('category_id')
            ->with('category:id,name,short_name')
            ->get()
            ->map(function ($row) {
                $row->stock_level = Asset::stockLevelFor($row->total);

                return $row;
            })
            ->sortByDesc('total')
            ->values();

        return response()->json($rows);
    }

    public function show(StockItem $stock_item)
    {
        return response()->json(
            $stock_item->load(['location', 'transactions' => fn ($q) => $q->with('recordedBy')->latest('transaction_date')->latest('id')])
        );
    }

    public function receive(Request $request, StockService $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'unit' => 'required|string|max:50',
            'quantity' => 'required|numeric|min:0.01',
            'location_id' => 'required|exists:locations,id',
            'min_threshold' => 'nullable|numeric|min:0',
            'max_threshold' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:255',
            'transaction_date' => 'nullable|date',
        ]);
        $validated['recorded_by'] = Auth::id();

        $item = $service->receive($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Stock In',
            'description' => "Received {$validated['quantity']} {$item->unit} of \"{$item->name}\" ({$item->stock_code}).",
        ]);

        return response()->json($item->load('location'), 201);
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

        ActivityLog::create([
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

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => "Deleted stock item: {$stock_item->name} ({$stock_item->stock_code}).",
        ]);

        return response()->json(['message' => 'Stock item deleted.']);
    }
}
