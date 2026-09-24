<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Throwable;

/**
 * Fans an in-app (bell) notification out to the people who oversee the
 * register — Operations & HR Manager and Finance — plus whoever performed the
 * action, so the actor also sees it land in their bell.
 *
 * This is for the everyday register changes (create / edit / delete / import
 * of assets, staff, users, programs, categories, locations, suppliers, stock,
 * settings). The workflow modules — transfers, assignments, disposals, returns,
 * QR scans — already notify the specific people involved and don't use this.
 */
class InAppNotifier
{
    public const ADMIN_ROLES = ['operations_hr_manager', 'finance_manager'];

    // API path segment => SPA page the notification should open.
    private const PAGES = [
        'assets' => '/app/assets',
        'asset-verifications' => '/app/asset-verifications',
        'categories' => '/app/categories',
        'locations' => '/app/locations',
        'staff' => '/app/staff',
        'programs' => '/app/programs',
        'suppliers' => '/app/suppliers',
        'users' => '/app/users',
        'roles' => '/app/users',
        'stock-items' => '/app/stock',
        'settings' => '/app/settings',
    ];

    public static function admins(string $message, string $type, ?string $url = null, ?int $actorId = null): void
    {
        // A notification is a side effect: it must never fail the action that
        // triggered it, so any error is reported and swallowed.
        try {
            $ids = User::whereIn('role', self::ADMIN_ROLES)
                ->where('is_active', true)
                ->where('is_locked', false)
                ->pluck('id');
            if ($actorId) {
                $ids->push($actorId);
            }

            $now = now();
            $rows = $ids->unique()->values()->map(fn ($id) => [
                'user_id' => $id,
                'type' => $type,
                'message' => $message,
                'url' => $url ?? self::urlForRequest(),
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($rows) {
                Notification::insert($rows);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    // The page for the resource the current API request touched, e.g.
    // POST /api/staff -> /app/staff. Null when there is no matching page.
    public static function urlForRequest(): ?string
    {
        return self::PAGES[request()->segment(2)] ?? null;
    }
}
