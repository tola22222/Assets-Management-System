<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\AssetNotificationService;
use Illuminate\Console\Command;

/**
 * Weekly nudge for assets that have sat on the register for 7+ days without
 * a required/recommended field (price, purchase date, serial number) filled
 * in — the same fields the "Needs attention" dashboard panel flags.
 */
class SendMissingFieldsNotification extends Command
{
    protected $signature = 'notifications:missing-fields';

    protected $description = 'Email Operations & HR a digest of assets missing required fields for 7+ days';

    private const GRACE_DAYS = 7;

    public function handle(): int
    {
        $cutoff = now()->subDays(self::GRACE_DAYS);

        $assets = Asset::where('status', 'active')
            ->where('created_at', '<=', $cutoff)
            ->where(function ($q) {
                $q->whereNull('purchase_price')
                    ->orWhereNull('purchase_date')
                    ->orWhereNull('serial_number');
            })
            ->with('category')
            ->get();

        if ($assets->isEmpty()) {
            $this->info('No assets past the grace period are missing fields.');

            return self::SUCCESS;
        }

        $items = $assets->map(function ($asset) {
            $missing = [];
            if (is_null($asset->purchase_price)) {
                $missing[] = 'Price';
            }
            if (is_null($asset->purchase_date)) {
                $missing[] = 'Purchase Date';
            }
            if (blank($asset->serial_number)) {
                $missing[] = 'Serial Number';
            }

            return [
                'assetId' => $asset->asset_code,
                'description' => $asset->name,
                'missingFields' => implode(', ', $missing),
            ];
        })->all();

        AssetNotificationService::send(AssetNotificationService::MISSING_FIELDS, [
            'recipients' => ['operations_hr_manager'],
            'extraData' => [
                'items' => $items,
                'count' => count($items),
                'graceDays' => self::GRACE_DAYS,
                'link' => route('reports.data-completeness'),
            ],
        ]);

        $this->info(count($items).' asset(s) flagged for missing fields.');

        return self::SUCCESS;
    }
}
