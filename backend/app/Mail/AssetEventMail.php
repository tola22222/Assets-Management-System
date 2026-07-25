<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssetEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $eventType, public array $payload) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectFor($this->eventType, $this->payload));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.asset-event',
            with: ['eventType' => $this->eventType, 'payload' => $this->payload],
        );
    }

    private function subjectFor(string $eventType, array $payload): string
    {
        $assetId = $payload['assetId'] ?? '';
        $description = $payload['description'] ?? '';
        $location = $payload['location'] ?? '';
        $status = $payload['extraData']['status'] ?? '';

        return match ($eventType) {
            'DAMAGE_FLAGGED' => "[Asset Flag] {$assetId} — {$description} flagged {$status} at {$location}",
            'DISPOSAL_REQUEST' => "[Approval Needed] Disposal request — {$assetId}",
            'MISSING_FIELDS' => '[Data Check] '.($payload['extraData']['count'] ?? 0).' assets missing required fields',
            'COUNT_REMINDER' => '[Reminder] Asset count scheduled for '.($payload['extraData']['date'] ?? ''),
            'COUNT_DISCREPANCY' => '[Discrepancy] '.($payload['extraData']['count'] ?? 0).' items require reconciliation',
            'LOW_STOCK' => "[Low Stock] {$description} at {$location} — ".($payload['extraData']['balance'] ?? '?').'/'.($payload['extraData']['unit'] ?? '').' remaining',
            default => "[PEPY Asset Notice] {$assetId}",
        };
    }
}
