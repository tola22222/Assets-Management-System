<?php

namespace Database\Seeders\Uat;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * In-app notifications, the email delivery audit trail, and the activity log.
 *
 * Two different things share the "Notification" name in this codebase and both
 * are covered here:
 *   - Notification     — the in-app bell (user_id / type / message / url / is_read)
 *   - NotificationLog  — the delivery audit trail written by AssetNotificationService
 *
 * Every `type` string the controllers emit is represented, plus all six
 * AssetNotificationService event types, so the bell, the notifications page and
 * the QR-scan report all have data.
 *
 * A `url` is set on the rows where the application sets one (the scheduled
 * digests point at /app/reports). Rows the controllers create with a null url
 * are seeded null, so the dataset reflects the real behaviour rather than a
 * tidied-up version of it.
 */
class UatNotificationSeeder extends Seeder
{
    public array $stats = ['notifications' => 0, 'notification_logs' => 0, 'activity_logs' => 0];

    /** [type, message, url, is_read, recipient email, days ago] */
    private const NOTIFICATIONS = [
        ['asset_registered', 'Asset registered: Truck Toyota Hilux Revo 2022 (PEY-SR-MOV-0085)', null, true, 'opm@pepy.test', 300],
        ['asset_flagged', 'Chan Narun flagged an issue on Fan - Ceiling (PEY-KL-FAF-0290): motor seized, will not start.', null, false, 'opm@pepy.test', 5],
        ['asset_flagged', 'Chan Narun flagged an issue on Fan - Ceiling (PEY-KL-FAF-0290): motor seized, will not start.', null, false, 'finance@pepy.test', 5],
        ['asset_flagged', 'Chan Narun flagged an issue on Fan - Ceiling (PEY-KL-FAF-0290): motor seized, will not start.', null, false, 'ed@pepy.test', 5],
        ['asset_verified', 'Verified asset: Motor Helmet - Black (lost)', null, false, 'opm@pepy.test', 27],
        ['asset_assigned', 'Camera Canon EOS 6D Mark II (PEY-SR-EQU-0161) has been assigned to you.', null, false, 'staff.sr@pepy.test', 300],
        ['asset_assigned', 'MotorBike (Honda) (PEY-KL-MOV-0044) has been assigned to you.', null, true, 'staff.kl@pepy.test', 400],
        ['return_request', 'Desktop Computer/UPS/Headset/Webcam has been returned and is awaiting your review.', null, false, 'opm@pepy.test', 20],
        ['return_approved', 'Your return of Camera Canon EOS 850D has been approved.', null, true, 'staff.sr@pepy.test', 18],
        ['transfer_request', 'New asset transfer request for Fan - Standing', null, false, 'opm@pepy.test', 6],
        ['transfer_request', 'New asset transfer request for Laptops', null, false, 'opm@pepy.test', 3],
        ['transfer_approved', 'Your transfer request has been approved.', null, true, 'opm2@pepy.test', 40],
        ['disposal_request', 'Disposal request submitted for Fan - Ceiling', null, false, 'ed@pepy.test', 4],
        ['disposal_request', 'Disposal request submitted for Air-Conditioner', null, false, 'ed@pepy.test', 3],
        ['disposal_approved', 'Your disposal request for Motor Helmet - White has been approved.', null, true, 'opm@pepy.test', 65],
        ['disposal_rejected', 'Your disposal request for Amazon Kindle has been rejected.', null, true, 'opm@pepy.test', 50],
        ['qr_scan', 'QR scanned: Desktop Computer (PEY-SR-COM-0326)', null, true, 'staff.sr@pepy.test', 12],
        ['qr_scan', 'QR scanned: Fan - Standing (PEY-KL-FAF-0015)', null, true, 'staff.kl@pepy.test', 9],
        ['qr_scan', 'QR scanned: Fan - Standing (PEY-SR-FAF-0009)', null, false, 'staff.ss@pepy.test', 8],
        ['qr_scan', 'QR scanned: Camera Canon EOS 6D Mark II (PEY-SR-EQU-0161)', null, false, 'opm@pepy.test', 2],
        ['qr_scan', 'QR scanned: Air-Conditioner (PEY-SR-EQU-0149)', null, false, 'staff.sr@pepy.test', 1],
        ['count_reminder', 'The August asset count is due on 1 August. Print the register and coordinate with Finance.', '/app/reports', true, 'opm@pepy.test', 32],
        ['count_discrepancy', '4 verification records since the last check show a damaged, lost or mismatched count.', '/app/reports', false, 'opm@pepy.test', 24],
        ['missing_fields', '35 assets active for 7+ days are still missing a price, purchase date or serial number.', '/app/reports', false, 'opm@pepy.test', 7],
        ['missing_fields', '35 assets active for 7+ days are still missing a price, purchase date or serial number.', '/app/reports', false, 'finance@pepy.test', 7],
        ['low_stock', '"Whiteboard Marker" (stock) has dropped to 0 box, at or below its minimum threshold.', '/app/stock', false, 'opm@pepy.test', 2],
        ['scheduled_report', 'The periodic asset register summary is ready.', '/app/reports', true, 'ed@pepy.test', 15],
    ];

    /** [event_type, asset_code|null, recipient email, status, error|null, days ago] */
    private const DELIVERY_LOG = [
        ['DAMAGE_FLAGGED', 'PEY-KL-FAF-0290', 'opm@pepy.test', 'sent', null, 5],
        ['DAMAGE_FLAGGED', 'PEY-KL-FAF-0290', 'ed@pepy.test', 'sent', null, 5],
        ['DISPOSAL_REQUEST', 'PEY-KL-FAF-0290', 'ed@pepy.test', 'sent', null, 4],
        ['DISPOSAL_REQUEST', 'PEY-SR-EQU-0008', 'ed@pepy.test', 'failed', 'Connection could not be established with host "smtp.gmail.com": stream_socket_client(): Connection timed out', 3],
        ['MISSING_FIELDS', null, 'opm@pepy.test', 'sent', null, 7],
        ['COUNT_REMINDER', null, 'opm@pepy.test', 'sent', null, 32],
        ['COUNT_REMINDER', null, 'finance@pepy.test', 'sent', null, 32],
        ['COUNT_DISCREPANCY', null, 'opm@pepy.test', 'sent', null, 24],
        ['COUNT_DISCREPANCY', null, 'finance@pepy.test', 'sent', null, 24],
        ['LOW_STOCK', null, 'opm@pepy.test', 'sent', null, 2],
    ];

    /** [action, description, actor email, days ago] */
    private const ACTIVITY = [
        ['Login', 'Oem Manin signed into the system.', 'opm@pepy.test', 1],
        ['Login', 'Chhin Chhunly signed into the system.', 'finance@pepy.test', 1],
        ['Login', 'Sok Chamreun signed into the system.', 'ed@pepy.test', 2],
        ['Login', 'Meas Sokvoeun signed into the system.', 'staff.sr@pepy.test', 2],
        ['Create', 'Registered asset: Desktop Computer (PEY-SR-COM-0326)', 'opm@pepy.test', 120],
        ['Create', 'Registered asset: Air-Conditioner (PEY-SR-EQU-0192)', 'opm@pepy.test', 45],
        ['Update', 'Updated asset: Honda CR-V', 'finance@pepy.test', 30],
        ['Update', 'Updated asset: Truck Toyota Hilux Revo 2022', 'opm@pepy.test', 88],
        ['Flag', 'Flagged issue on asset: Fan - Ceiling (PEY-KL-FAF-0290) — motor seized, will not start.', 'staff.ss@pepy.test', 5],
        ['Verification', 'Verified asset: Truck Toyota Hilux Revo 2022', 'finance@pepy.test', 210],
        ['Verification', 'Verified asset: Safe Box', 'finance@pepy.test', 210],
        ['Verification', 'Verified asset: Motor Helmet - Black', 'opm@pepy.test', 27],
        ['QR Verification', 'Verified asset via QR scan: Desktop Computer (PEY-SR-COM-0326)', 'staff.sr@pepy.test', 12],
        ['QR Verification', 'Verified asset via QR scan: Fan - Standing (PEY-KL-FAF-0015)', 'staff.kl@pepy.test', 9],
        ['Complete Verification', 'Completed verification', 'opm@pepy.test', 26],
        ['Create', 'Requested transfer of asset', 'opm2@pepy.test', 6],
        ['Create', 'Requested transfer of asset', 'finance@pepy.test', 3],
        ['Approve', 'Approved asset transfer', 'opm@pepy.test', 40],
        ['Reject', 'Rejected asset transfer', 'opm@pepy.test', 25],
        ['Create', 'Requested disposal for asset Fan - Ceiling', 'opm@pepy.test', 4],
        ['Create', 'Requested repair for asset Air-Conditioner', 'opm@pepy.test', 3],
        ['Approve', 'Approved disposal for asset Motor Helmet - White', 'ed@pepy.test', 65],
        ['Reject', 'Rejected disposal for asset Amazon Kindle', 'ed@pepy.test', 50],
        ['Stock Out', 'Issued 5 ream of "A4 Paper (80gsm)" (stock) — Issued to the office.', 'opm@pepy.test', 75],
        ['Stock Out', 'Issued 4 box of "Whiteboard Marker" (stock) — Issued to the Dream Program.', 'finance@pepy.test', 60],
        ['Delete', 'Deleted stock item: obsolete test row', 'opm@pepy.test', 90],
        ['Profile Update', 'Chhin Chhunly updated their profile.', 'finance@pepy.test', 18],
        ['Password Change', 'Meas Sokvoeun changed their password.', 'staff.sr@pepy.test', 22],
        ['Create', 'Created user account for Chan Narun', 'opm@pepy.test', 140],
        ['Update', 'Locked user account: Kim Solin', 'opm@pepy.test', 11],
        ['Import', 'Bulk imported the fixed-asset register from PEPY_Asset_Inventory_Cleaned.md', 'opm@pepy.test', 360],
        ['Logout', 'Sok Chamreun signed out of the system.', 'ed@pepy.test', 2],
        ['Logout', 'Chhin Chhunly signed out of the system.', 'finance@pepy.test', 1],
        ['Login', 'Long Pisey signed into the system.', 'opm2@pepy.test', 3],
        ['Update', 'Updated supplier: Angkor IT Supply', 'finance@pepy.test', 35],
        ['Create', 'Created program: Bright Future Labs', 'opm@pepy.test', 200],
        ['Update', 'Updated settings', 'opm@pepy.test', 15],
        ['Create', 'Created backup of the database', 'opm@pepy.test', 14],
        ['Delete', 'Deleted asset: duplicate register row', 'opm@pepy.test', 150],
        ['Login', 'Chan Narun signed into the system.', 'staff.ss@pepy.test', 8],
    ];

    public function run(): void
    {
        $users = User::pluck('id', 'email');
        $assets = Asset::pluck('id', 'asset_code');

        foreach (self::NOTIFICATIONS as [$type, $message, $url, $isRead, $email, $daysAgo]) {
            $userId = $users[$email] ?? null;
            if (! $userId) {
                continue;
            }

            $existing = Notification::where('user_id', $userId)->where('message', $message)->first();
            if ($existing) {
                continue;
            }

            $notification = Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'message' => $message,
                'url' => $url,
                'is_read' => $isRead,
            ]);
            $notification->created_at = now()->subDays($daysAgo);
            $notification->updated_at = $notification->created_at;
            $notification->save();

            $this->stats['notifications']++;
        }

        foreach (self::DELIVERY_LOG as [$eventType, $assetCode, $email, $status, $error, $daysAgo]) {
            $userId = $users[$email] ?? null;
            $assetId = $assetCode ? ($assets[$assetCode] ?? null) : null;

            $exists = NotificationLog::where('event_type', $eventType)
                ->where('recipient_user_id', $userId)
                ->where('status', $status)
                ->exists();

            if ($exists) {
                continue;
            }

            $log = NotificationLog::create([
                'event_type' => $eventType,
                'asset_id' => $assetId,
                'recipient_user_id' => $userId,
                'status' => $status,
                'error' => $error,
            ]);
            $log->created_at = now()->subDays($daysAgo);
            $log->updated_at = $log->created_at;
            $log->save();

            $this->stats['notification_logs']++;
        }

        foreach (self::ACTIVITY as [$action, $description, $email, $daysAgo]) {
            $userId = $users[$email] ?? null;
            if (! $userId) {
                continue;
            }

            if (ActivityLog::where('user_id', $userId)->where('description', $description)->exists()) {
                continue;
            }

            $log = ActivityLog::create([
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
            ]);
            $log->created_at = now()->subDays($daysAgo);
            $log->updated_at = $log->created_at;
            $log->save();

            $this->stats['activity_logs']++;
        }
    }
}
