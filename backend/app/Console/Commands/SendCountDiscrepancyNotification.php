<?php

namespace App\Console\Commands;

use App\Models\AssetVerification;
use App\Models\Setting;
use App\Services\AssetNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Weekly digest of physical-count verifications that don't match the
 * register: condition recorded as broken/lost, or quantity_verified != 1
 * (each Asset row is exactly one physical unit, so anything else means the
 * count didn't match what's on record). Idempotent via a Setting marker
 * (`last_count_discrepancy_check_at`) rather than a rolling window, so a
 * late/early cron run never double-reports or skips a verification.
 */
class SendCountDiscrepancyNotification extends Command
{
    protected $signature = 'notifications:count-discrepancy';

    protected $description = 'Email a digest of asset verifications that recorded a discrepancy against the register';

    public function handle(AssetNotificationService $notifications): int
    {
        $lastChecked = Setting::where('key', 'last_count_discrepancy_check_at')->value('value');
        $since = $lastChecked ? Carbon::parse($lastChecked) : now()->subWeek();
        $now = now();

        $discrepancies = AssetVerification::with('asset')
            ->where('created_at', '>', $since)
            ->where(function ($q) {
                $q->whereIn('condition', ['broken', 'lost'])
                    ->orWhere('quantity_verified', '!=', 1);
            })
            ->get();

        Setting::updateOrCreate(['key' => 'last_count_discrepancy_check_at'], ['value' => $now->toDateTimeString()]);

        if ($discrepancies->isEmpty()) {
            $this->info('No count discrepancies since the last check.');

            return self::SUCCESS;
        }

        $note = $discrepancies->map(function (AssetVerification $verification) {
            $code = $verification->asset->asset_code ?? "asset #{$verification->asset_id}";

            return "{$code} — verified {$verification->condition}, qty {$verification->quantity_verified}";
        })->implode("\n");

        $notifications->send('COUNT_DISCREPANCY', [
            'note' => $note,
            'url' => route('reports.inventory'),
            'extraData' => ['count' => $discrepancies->count()],
        ]);

        $this->info($discrepancies->count().' discrepant verification(s) reported.');

        return self::SUCCESS;
    }
}
