<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Bulk consumables (toner, cables, paper, ...) — one StockItem row per SKU
 * per site, with a running balance kept in sync by an append-only
 * StockTransaction log. The log is the source of truth; balance is a
 * cached value derived from it (see issue()).
 *
 * There is deliberately no receive()/Stock-In counterpart: the Receive Stock
 * form was removed, so balances only ever move downwards from whatever is
 * already on record. 'in' transactions predating that removal still exist in
 * the log and still replay correctly.
 */
class StockService
{
    /**
     * Issue Stock (Stock-Out). Blocks with a clear error — no transaction
     * is recorded and the balance is untouched — if the requested quantity
     * exceeds what's on hand. Locks the row for the duration of the check
     * + decrement so two simultaneous issues can't both pass the balance
     * check against the same stale balance.
     */
    public function issue(StockItem $item, array $data): StockItem
    {
        $result = DB::transaction(function () use ($item, $data) {
            $locked = StockItem::where('id', $item->id)->lockForUpdate()->first();

            if ($data['quantity'] > $locked->balance) {
                throw new InvalidArgumentException(
                    "Cannot issue {$data['quantity']} {$locked->unit} of \"{$locked->name}\" — only {$locked->balance} {$locked->unit} in stock."
                );
            }

            $wasLow = $locked->status === 'low';

            StockTransaction::create([
                'stock_item_id' => $locked->id,
                'type' => 'out',
                'quantity' => $data['quantity'],
                'reason' => $data['reason'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'recorded_by' => $data['recorded_by'] ?? null,
            ]);

            $locked->decrement('balance', $data['quantity']);
            $fresh = $locked->fresh();

            return [$fresh, ! $wasLow && $fresh->status === 'low'];
        });

        [$fresh, $justWentLow] = $result;

        // Notify only on the crossing (normal/high -> low), not on every
        // subsequent issue while it stays low, so OPM isn't re-alerted for
        // every single unit issued out of an already-known-short item.
        if ($justWentLow) {
            (new AssetNotificationService)->send('LOW_STOCK', [
                'description' => $fresh->name,
                'location' => $fresh->location->name ?? null,
                'note' => "\"{$fresh->name}\" ({$fresh->stock_code}) dropped to {$fresh->balance} {$fresh->unit}, at or below the minimum threshold of {$fresh->min_threshold} {$fresh->unit}.",
                'url' => url('/app/stock'),
                'extraData' => [
                    'balance' => $fresh->balance,
                    'unit' => $fresh->unit,
                ],
            ]);
        }

        return $fresh;
    }
}
