<?php

namespace Database\Seeders\Uat;

use App\Models\Location;
use App\Models\StockItem;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\AssetCodeService;
use Illuminate\Database\Seeder;

/**
 * Consumables. These are NOT part of the fixed-asset register and are not in
 * PEPY_Asset_Inventory_Cleaned.md — the register covers tagged fixed assets
 * only. They are the minimum rows needed to exercise the Stock screen.
 *
 * Seeding is the only way this table can ever be populated: the API exposes
 * index / show / by-location / issue / destroy and no create or receive route,
 * so nothing in the running application can bring a stock item into existence.
 *
 * `status` is a computed accessor (balance vs thresholds), never stored, so the
 * three states are produced by choosing balances:
 *   balance <= min_threshold            -> low   (zero stock is the extreme case)
 *   balance >= max_threshold            -> high
 *   otherwise                           -> normal
 */
class UatStockSeeder extends Seeder
{
    public array $stats = ['items' => 0, 'transactions' => 0];

    /**
     * [name, category, unit, balance, min, max, site code, expected status, opening qty received]
     * Opening qty null => no transaction history at all, so the delete path
     * (which refuses items that have history) stays testable.
     */
    private const ITEMS = [
        ['A4 Paper (80gsm)', 'Office Supplies', 'ream', 45, 20, 100, 'SR', 'normal', 60],
        ['Printer Toner HP 12A', 'Office Supplies', 'cartridge', 3, 5, 20, 'SR', 'low', 12],
        ['Whiteboard Marker', 'Classroom', 'box', 0, 4, 30, 'SR', 'low (zero stock)', 10],
        ['HDMI Cable 2m', 'ICT', 'piece', 34, 5, 30, 'SR', 'high', 40],
        ['Extension Cord 5m', 'ICT', 'piece', 12, 4, 25, 'KL', 'normal', 15],
        ['First Aid Refill Kit', 'Facilities', 'kit', 2, 3, 10, 'SS', 'low', 6],
        ['Chalk (white)', 'Classroom', 'box', 0, 2, 24, 'VR', 'low (zero stock)', 8],
        ['Cleaning Detergent', 'Facilities', 'bottle', 18, 6, 40, 'SR', 'normal', 24],
        ['Laptop Charger 65W', 'ICT', 'piece', 9, 3, 15, 'SR', 'normal', null],
        ['Projector Lamp', 'ICT', 'piece', 1, 2, 8, 'BS', 'low', null],
        ['Notebook A5', 'Classroom', 'pack', 26, 5, 25, 'KD', 'high', 30],
        ['Motor Oil 1L', 'Vehicle', 'bottle', 7, 3, 20, 'SR', 'normal', 10],
    ];

    public function run(): void
    {
        $sites = Location::whereNotNull('code')->pluck('id', 'code');
        $opm = User::where('email', 'opm@pepy.test')->value('id');
        $finance = User::where('email', 'finance@pepy.test')->value('id');

        foreach (self::ITEMS as [$name, $category, $unit, $balance, $min, $max, $siteCode, $_status, $opening]) {
            $locationId = $sites[$siteCode] ?? null;

            if (! $locationId) {
                continue;
            }

            $item = StockItem::where('name', $name)->where('location_id', $locationId)->first();

            if (! $item) {
                $item = StockItem::create([
                    'stock_code' => AssetCodeService::nextStockCode(),
                    'name' => $name,
                    'category' => $category,
                    'unit' => $unit,
                    'balance' => $balance,
                    'min_threshold' => $min,
                    'max_threshold' => $max,
                    'location_id' => $locationId,
                ]);
                $this->stats['items']++;
            }

            if ($opening === null || $item->transactions()->exists()) {
                continue;
            }

            // An opening receipt, then the issues that brought the balance down
            // to where it now sits — so the ledger reconciles to the balance.
            StockTransaction::create([
                'stock_item_id' => $item->id,
                'type' => 'in',
                'quantity' => $opening,
                'reason' => 'Opening stock recorded at the February count',
                'transaction_date' => now()->subDays(180)->toDateString(),
                'recorded_by' => $opm,
            ]);
            $this->stats['transactions']++;

            $issued = $opening - $balance;

            if ($issued > 0) {
                $chunks = $issued >= 4 ? 2 : 1;
                $per = intdiv($issued, $chunks);
                $remainder = $issued - ($per * $chunks);

                for ($i = 0; $i < $chunks; $i++) {
                    $qty = $per + ($i === 0 ? $remainder : 0);
                    if ($qty <= 0) {
                        continue;
                    }

                    StockTransaction::create([
                        'stock_item_id' => $item->id,
                        'type' => 'out',
                        'quantity' => $qty,
                        'reason' => $i === 0 ? 'Issued to the office' : 'Issued to the Dream Program',
                        'transaction_date' => now()->subDays(120 - ($i * 45))->toDateString(),
                        'recorded_by' => $i === 0 ? $opm : $finance,
                    ]);
                    $this->stats['transactions']++;
                }
            }
        }
    }
}
