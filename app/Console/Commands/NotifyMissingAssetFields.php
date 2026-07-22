<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\AssetNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class NotifyMissingAssetFields extends Command
{
    protected $signature = 'app:notify-missing-fields';

    protected $description = 'Notify Operations & HR Manager about assets missing price/date/serial for more than 7 days';

    public function handle(AssetNotificationService $notifications): int
    {
        $assets = Asset::where('created_at', '<=', now()->subDays(7))
            ->where(function ($query) {
                $query->whereNull('purchase_price')
                    ->orWhereNull('purchase_date')
                    ->orWhereNull('serial_number');
            })
            ->get();

        if ($assets->isEmpty()) {
            $this->info('No assets missing required fields for more than 7 days.');

            return self::SUCCESS;
        }

        $missing = $assets->map(function (Asset $asset) {
            $fields = collect([
                'price' => $asset->purchase_price,
                'purchase date' => $asset->purchase_date,
                'serial number' => $asset->serial_number,
            ])->filter(fn ($value) => blank($value))->keys()->implode(', ');

            return "{$asset->asset_code} — missing {$fields}";
        })->implode("\n");

        $notifications->send('MISSING_FIELDS', [
            'note' => $missing,
            'extraData' => ['count' => $assets->count()],
            'url' => URL::to('/app/assets'),
        ]);

        $this->info("Notified about {$assets->count()} asset(s) missing required fields.");

        return self::SUCCESS;
    }
}
