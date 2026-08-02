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
 * cached value derived from it (see receive()/issue()).
 */
class StockService
{
    /**
     * Receive Stock (Stock-In). Reuses an existing item (matched by
     * name + location, case-insensitively) rather than creating a
     * duplicate; only creates a new StockItem — with a fresh PEY-STK-####
     * code and a starting balance of 0 — the first time that name is
     * received at that site.
     */
    public function receive(array $data): StockItem
    {
        return DB::transaction(function () use ($data) {
            $item = StockItem::whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
                ->where('location_id', $data['location_id'])
                ->lockForUpdate()
                ->first();

            if (! $item) {
                $item = StockItem::create([
                    'stock_code' => AssetCodeService::nextStockCode(),
                    'name' => $data['name'],
                    'category' => $data['category'] ?? null,
                    'unit' => $data['unit'],
                    'balance' => 0,
                    'min_threshold' => $data['min_threshold'] ?? null,
                    'max_threshold' => $data['max_threshold'] ?? null,
                    'location_id' => $data['location_id'],
                ]);
            }

            StockTransaction::create([
                'stock_item_id' => $item->id,
                'type' => 'in',
                'quantity' => $data['quantity'],
                'reason' => $data['reason'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'recorded_by' => $data['recorded_by'] ?? null,
            ]);

            $item->increment('balance', $data['quantity']);

            return $item->fresh();
        });
    }

    /**
     * Issue Stock (Stock-Out). Blocks with a clear error — no transaction
     * is recorded and the balance is untouched — if the requested quantity
     * exceeds what's on hand. Locks the row for the duration of the check
     * + decrement so two simultaneous issues can't both pass the balance
     * check against the same stale balance.
     */
    public function issue(StockItem $item, array $data): StockItem
    {
        return DB::transaction(function () use ($item, $data) {
            $locked = StockItem::where('id', $item->id)->lockForUpdate()->first();

            if ($data['quantity'] > $locked->balance) {
                throw new InvalidArgumentException(
                    "Cannot issue {$data['quantity']} {$locked->unit} of \"{$locked->name}\" — only {$locked->balance} {$locked->unit} in stock."
                );
            }

            StockTransaction::create([
                'stock_item_id' => $locked->id,
                'type' => 'out',
                'quantity' => $data['quantity'],
                'reason' => $data['reason'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'recorded_by' => $data['recorded_by'] ?? null,
            ]);

            $locked->decrement('balance', $data['quantity']);

            return $locked->fresh();
        });
    }
}
