<?php

namespace App\Console\Commands;

use App\Mail\ScheduledAssetReportMail;
use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetReturn;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendScheduledAssetReport extends Command
{
    protected $signature = 'app:send-scheduled-asset-report';

    protected $description = 'Notify Finance Manager/Executive Director/Admin when the asset counting cycle is due';

    public function handle(): int
    {
        $intervalMonths = (int) (Setting::where('key', 'report_interval_months')->value('value') ?? 6);
        $lastSentAt = Setting::where('key', 'last_scheduled_report_at')->value('value');

        if (!$lastSentAt) {
            Setting::updateOrCreate(['key' => 'last_scheduled_report_at'], ['value' => now()->toDateTimeString()]);
            $this->info('No prior report on record — baseline set to now. First report will send in ' . $intervalMonths . ' month(s).');
            return self::SUCCESS;
        }

        $lastSentAt = Carbon::parse($lastSentAt);
        if (now()->lessThan($lastSentAt->copy()->addMonths($intervalMonths))) {
            $this->info('Not due yet. Next due: ' . $lastSentAt->copy()->addMonths($intervalMonths)->toDateString());
            return self::SUCCESS;
        }

        $summary = [
            'total_assets' => Asset::count(),
            'active_assets' => Asset::where('status', 'active')->count(),
            'disposed_assets' => Asset::where('status', 'disposed')->count(),
            'lost_assets' => Asset::where('condition', 'lost')->count(),
            'broken_assets' => Asset::where('condition', 'broken')->count(),
            'pending_disposals' => AssetDisposal::pending()->count(),
            'pending_transfers' => AssetTransfer::where('status', 'pending')->count(),
            'pending_returns' => AssetReturn::where('status', 'pending')->count(),
            'verifications_since_last' => AssetVerification::where('verified_at', '>=', $lastSentAt)->count(),
        ];

        $periodLabel = now()->format('F Y');
        $recipients = User::whereIn('role', ['finance_manager', 'executive_director', 'admin'])->get();

        foreach ($recipients as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'type' => 'scheduled_report',
                'message' => "The {$periodLabel} asset counting cycle is due. {$summary['total_assets']} assets on register.",
                'url' => route('reports.index'),
            ]);

            if ($recipient->email) {
                try {
                    Mail::to($recipient->email)->send(new ScheduledAssetReportMail($summary, $periodLabel));
                } catch (\Throwable $e) {
                    Log::warning('Scheduled asset report email failed for ' . $recipient->email . ': ' . $e->getMessage());
                }
            }
        }

        Setting::updateOrCreate(['key' => 'last_scheduled_report_at'], ['value' => now()->toDateTimeString()]);

        $this->info('Scheduled asset report sent to ' . $recipients->count() . ' recipient(s).');
        return self::SUCCESS;
    }
}
