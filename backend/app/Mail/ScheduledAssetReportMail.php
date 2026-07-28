<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledAssetReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $summary, public string $periodLabel)
    {
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
