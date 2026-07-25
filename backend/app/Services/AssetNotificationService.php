<?php

namespace App\Services;

use App\Mail\AssetEventMail;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Central email dispatcher for asset/stock lifecycle events (Part B of the PEPY spec).
 * Recipients are always looked up by role — callers may narrow via payload['recipients']
 * but must never pass a hardcoded individual address.
 */
class AssetNotificationService
{
    private const VALID_ROLES = ['operations_hr_manager', 'finance_manager', 'executive_director', 'staff'];

    private const RECIPIENT_ROLES = [
        'DAMAGE_FLAGGED' => ['operations_hr_manager'],
        'DISPOSAL_REQUEST' => ['executive_director'],
        'MISSING_FIELDS' => ['operations_hr_manager'],
        'COUNT_REMINDER' => ['operations_hr_manager', 'finance_manager'],
        'COUNT_DISCREPANCY' => ['operations_hr_manager'],
        'LOW_STOCK' => ['operations_hr_manager'],
    ];

    private const CC_ROLES = [
        'DISPOSAL_REQUEST' => ['finance_manager'],
        'COUNT_DISCREPANCY' => ['finance_manager'],
    ];

    public function send(string $eventType, array $payload): void
    {
        if (! array_key_exists($eventType, self::RECIPIENT_ROLES)) {
            throw new InvalidArgumentException("Unknown asset notification event type: {$eventType}");
        }

        if (array_key_exists('flaggedBy', $payload)) {
            $this->assertValidRole($payload['flaggedBy']);
        }

        $recipients = $payload['recipients'] ?? $this->usersWithRoles(self::RECIPIENT_ROLES[$eventType]);
        $ccRecipients = $payload['ccRecipients'] ?? $this->usersWithRoles(self::CC_ROLES[$eventType] ?? []);
        $ccEmails = $ccRecipients->pluck('email')->filter()->values()->all();

        foreach ($recipients as $recipient) {
            $this->deliver($eventType, $payload, $recipient, $ccEmails);
        }
    }

    private function usersWithRoles(array $roles): Collection
    {
        if (empty($roles)) {
            return collect();
        }

        return User::whereIn('role', $roles)->get();
    }

    private function assertValidRole(mixed $flaggedBy): void
    {
        $role = $flaggedBy instanceof User ? $flaggedBy->role : $flaggedBy;

        if (! is_string($role) || ! in_array($role, self::VALID_ROLES, true)) {
            throw new InvalidArgumentException('Invalid or unknown role for flaggedBy.');
        }
    }

    private function deliver(string $eventType, array $payload, User $recipient, array $ccEmails): void
    {
        if (! $recipient->email) {
            return;
        }

        $mailable = new AssetEventMail($eventType, $payload);
        if (! empty($ccEmails)) {
            $mailable->cc($ccEmails);
        }

        $error = null;
        $sent = false;

        for ($attempt = 1; $attempt <= 2 && ! $sent; $attempt++) {
            try {
                Mail::to($recipient->email)->send($mailable);
                $sent = true;
            } catch (\Throwable $e) {
                $error = $e;
            }
        }

        NotificationLog::create([
            'event_type' => $eventType,
            // payload['assetId'] is the human-readable asset_code for the email body/subject;
            // the FK here needs the numeric PK, passed separately to avoid overloading that key.
            'asset_id' => $payload['assetDbId'] ?? null,
            'recipient_user_id' => $recipient->id,
            'status' => $sent ? 'sent' : 'failed',
            'error' => $sent ? null : $error?->getMessage(),
        ]);

        if (! $sent) {
            Log::warning("AssetNotificationService: failed to deliver {$eventType} to {$recipient->email}: ".$error?->getMessage());
        }
    }
}
