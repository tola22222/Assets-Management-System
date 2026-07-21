<?php

namespace App\Services;

use App\Mail\AssetNotificationMail;
use App\Models\AssetNotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * sendAssetNotification(eventType, payload) — the single entry point for
 * every asset-related email in the system (damage/loss flags, disposal
 * approvals, data-completeness nudges, count reminders, count discrepancies).
 *
 * payload shape (all keys optional except noted):
 *   assetId       string  the printed asset code (PEY-SITE-CATEGORY-####), not the DB id
 *   description   string  asset name/description
 *   location      string  location name
 *   category      string  category name
 *   flaggedBy     string  who triggered the event
 *   note          string  free-text note/remark/reason
 *   recipients    string[]  REQUIRED — role keys (e.g. 'executive_director'), not email
 *                           addresses or names, so this survives staff turnover
 *   ccRecipients  string[]  role keys, cc'd on every recipient's email
 *   extraData     array   event-specific extra fields (see each template)
 *
 * Every field is optional beyond `recipients` — a blank serial number, price,
 * etc. must never crash template rendering (the data model already treats
 * those as optional/recommended, not required).
 */
class AssetNotificationService
{
    public const DAMAGE_FLAGGED = 'DAMAGE_FLAGGED';

    public const DISPOSAL_REQUEST = 'DISPOSAL_REQUEST';

    public const MISSING_FIELDS = 'MISSING_FIELDS';

    public const COUNT_REMINDER = 'COUNT_REMINDER';

    public const COUNT_DISCREPANCY = 'COUNT_DISCREPANCY';

    private const EVENT_TYPES = [
        self::DAMAGE_FLAGGED,
        self::DISPOSAL_REQUEST,
        self::MISSING_FIELDS,
        self::COUNT_REMINDER,
        self::COUNT_DISCREPANCY,
    ];

    public static function send(string $eventType, array $payload): void
    {
        if (! in_array($eventType, self::EVENT_TYPES, true)) {
            throw new InvalidArgumentException("Unknown asset notification event type \"{$eventType}\".");
        }

        $recipients = self::emailsForRoles($payload['recipients'] ?? []);
        if (empty($recipients)) {
            // Nobody currently holds that role — nothing to send, and not an
            // error: role assignment can change (e.g. no ED configured yet).
            return;
        }

        $cc = self::emailsForRoles($payload['ccRecipients'] ?? []);
        $subject = self::subjectFor($eventType, $payload);

        foreach ($recipients as $to) {
            self::deliver($eventType, $payload, $to, $cc, $subject);
        }
    }

    /** Role keys → current email addresses. Never hardcode a person's email. */
    public static function emailsForRoles(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        return User::whereIn('role', $roles)
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->values()
            ->all();
    }

    /** Send with one retry on failure; always logs the outcome for audit / HR visibility. */
    private static function deliver(string $eventType, array $payload, string $to, array $cc, string $subject): void
    {
        $error = null;
        $attempts = 0;

        while ($attempts < 2) {
            $attempts++;
            try {
                $mailer = Mail::to($to);
                if ($cc) {
                    $mailer->cc($cc);
                }
                $mailer->send(new AssetNotificationMail($eventType, $payload, $subject));
                $error = null;
                break;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                Log::warning("AssetNotificationService: [{$eventType}] to {$to} failed on attempt {$attempts}: {$error}");
            }
        }

        AssetNotificationLog::create([
            'event_type' => $eventType,
            'asset_code' => $payload['assetId'] ?? null,
            'recipient' => $to,
            'subject' => $subject,
            'status' => $error ? 'failed' : 'sent',
            'error' => $error,
            'attempts' => $attempts,
        ]);
    }

    private static function subjectFor(string $eventType, array $payload): string
    {
        $assetId = $payload['assetId'] ?? 'Unknown asset';
        $description = $payload['description'] ?? 'Asset';
        $location = $payload['location'] ?? 'unknown location';
        $extra = $payload['extraData'] ?? [];

        return match ($eventType) {
            self::DAMAGE_FLAGGED => sprintf(
                '[Asset Flag] %s — %s flagged %s at %s',
                $assetId, $description, $extra['status'] ?? 'an issue', $location
            ),
            self::DISPOSAL_REQUEST => "[Approval Needed] Disposal request — {$assetId}",
            self::MISSING_FIELDS => '[Data Check] '.self::countOf($extra).' assets missing required fields',
            self::COUNT_REMINDER => '[Reminder] Asset count scheduled for '.($extra['date'] ?? 'soon'),
            self::COUNT_DISCREPANCY => '[Discrepancy] '.self::countOf($extra).' items require reconciliation',
        };
    }

    private static function countOf(array $extra): int
    {
        return $extra['count'] ?? count($extra['items'] ?? []);
    }
}
