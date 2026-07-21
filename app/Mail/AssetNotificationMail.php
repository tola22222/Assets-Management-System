<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One Mailable for all five AssetNotificationService event types — the
 * template is picked by eventType so adding a new trigger only means adding
 * a case to AssetNotificationService and a matching Blade view, not a new
 * Mailable class.
 */
class AssetNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $eventType,
        public array $payload,
        public string $subjectLine,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.asset-notifications.'.strtolower($this->eventType),
            with: ['payload' => $this->payload],
        );
    }
}
