<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * How often the scheduled asset report goes out: every N days, months or
 * years (Settings → report_interval + report_interval_unit).
 *
 * Shared by the `app:send-scheduled-asset-report` command and the Settings
 * screen's "next report due", so the two can never disagree. Older installs
 * only have report_interval_months, which still reads as N months.
 */
class ReportSchedule
{
    public const UNITS = ['day', 'month', 'year'];

    public const DEFAULT = [6, 'month'];

    /** @return array{0: int, 1: string} [count, unit] */
    public static function interval(): array
    {
        $settings = Setting::whereIn('key', ['report_interval', 'report_interval_unit', 'report_interval_months'])
            ->pluck('value', 'key');

        if (filled($settings['report_interval'] ?? null)) {
            $unit = $settings['report_interval_unit'] ?? 'month';

            return [max(1, (int) $settings['report_interval']), in_array($unit, self::UNITS, true) ? $unit : 'month'];
        }

        if (filled($settings['report_interval_months'] ?? null)) {
            return [max(1, (int) $settings['report_interval_months']), 'month'];
        }

        return self::DEFAULT;
    }

    /** The moment one interval after $from. */
    public static function after(Carbon $from, ?array $interval = null): Carbon
    {
        [$count, $unit] = $interval ?? self::interval();

        return match ($unit) {
            'day' => $from->copy()->addDays($count),
            'year' => $from->copy()->addYears($count),
            default => $from->copy()->addMonths($count),
        };
    }

    /** The moment one interval before $from. */
    public static function before(Carbon $from, ?array $interval = null): Carbon
    {
        [$count, $unit] = $interval ?? self::interval();

        return match ($unit) {
            'day' => $from->copy()->subDays($count),
            'year' => $from->copy()->subYears($count),
            default => $from->copy()->subMonths($count),
        };
    }

    /** "6 month(s)", for command output. */
    public static function describe(?array $interval = null): string
    {
        [$count, $unit] = $interval ?? self::interval();

        return "{$count} {$unit}(s)";
    }
}
