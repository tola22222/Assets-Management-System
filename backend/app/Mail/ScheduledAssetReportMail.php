<?php

namespace App\Mail;

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetReturn;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ScheduledAssetReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $summary, public string $periodLabel)
    {
    }

    /**
     * Shared by the scheduled `app:send-scheduled-asset-report` command and
     * the manual "email this report" action on the Reports page, so both
     * send the exact same set of numbers.
     */
    public static function buildSummary(?Carbon $since = null): array
    {
        return [
            'total_assets' => Asset::count(),
            'active_assets' => Asset::where('status', 'active')->count(),
            'disposed_assets' => Asset::where('status', 'disposed')->count(),
            'lost_assets' => Asset::where('condition', 'lost')->count(),
            'broken_assets' => Asset::where('condition', 'broken')->count(),
            'pending_disposals' => AssetDisposal::pending()->count(),
            'pending_transfers' => AssetTransfer::where('status', 'pending')->count(),
            'pending_returns' => AssetReturn::where('status', 'pending')->count(),
            'verifications_since_last' => $since
                ? AssetVerification::where('verified_at', '>=', $since)->count()
                : AssetVerification::count(),
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Asset Count Report Due — {$this->periodLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.scheduled-report',
            with: ['summary' => $this->summary, 'periodLabel' => $this->periodLabel],
        );
    }
}
