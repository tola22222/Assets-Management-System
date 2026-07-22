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

    public function handle(AssetNotificationService $notifications): int
    {
        $cutoff = now()->subDays(self::GRACE_DAYS);

        $assets = Asset::where('status', 'active')
            ->where('created_at', '<=', $cutoff)
            ->where(function ($q) {
                $q->whereNull('purchase_price')
                    ->orWhereNull('purchase_date')
                    ->orWhereNull('serial_number');
            })
            ->get();

        if ($assets->isEmpty()) {
            $this->info('No assets past the grace period are missing fields.');

            return self::SUCCESS;
        }

        $note = $assets->map(function (Asset $asset) {
            $missing = collect([
                'Price' => $asset->purchase_price,
                'Purchase Date' => $asset->purchase_date,
                'Serial Number' => $asset->serial_number,
            ])->filter(fn ($value) => blank($value))->keys()->implode(', ');

            return "{$asset->asset_code} — missing {$missing}";
        })->implode("\n");

        $notifications->send('MISSING_FIELDS', [
            'note' => $note,
            'url' => route('reports.data-completeness'),
            'extraData' => ['count' => $assets->count()],
        ]);

        $this->info($assets->count().' asset(s) flagged for missing fields.');

        return self::SUCCESS;
    }
}
