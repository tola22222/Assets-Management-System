<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * How often the scheduled asset report goes out: every N days, months or
 * years (Settings → report_interval + report_interval_unit), optionally from
 * a chosen first send date (report_start_date).
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

    /**
     * Settings → "First send on" (report_start_date), or null when unset. The
     * first automatic report goes out that day, then every interval after it.
     */
    public static function startDate(): ?Carbon
    {
        $value = Setting::where('key', 'report_start_date')->value('value');
        if (blank($value)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * When the next automatic report is due, given when the last one went.
     *
     * With a start date: the start date itself until a report has gone out on
     * or after it, then the next date in its series — start, start + 1
     * interval, start + 2 … — so it stays on the chosen day (the 15th, say)
     * however late the daily check runs. Months and years never overflow
     * (31 Jan + 1 month is 28/29 Feb, and the series goes back to the 31st
     * after).
     *
     * Without one: one interval after the last report, or null when none has
     * gone yet (the command then sets its baseline).
     */
    public static function nextDue(?Carbon $lastSentAt, ?array $interval = null, ?Carbon $start = null): ?Carbon
    {
        $interval ??= self::interval();
        $start ??= self::startDate();

        if ($start === null) {
            return $lastSentAt ? self::after($lastSentAt, $interval) : null;
        }
        if ($lastSentAt === null || $lastSentAt->lt($start)) {
            return $start->copy();
        }

        [$count, $unit] = $interval;
        for ($k = 1; $k <= 100000; $k++) {
            $steps = $count * $k;
            $due = match ($unit) {
                'day' => $start->copy()->addDays($steps),
                'year' => $start->copy()->addYearsNoOverflow($steps),
                default => $start->copy()->addMonthsNoOverflow($steps),
            };
            if ($due->gt($lastSentAt)) {
                return $due;
            }
        }

        return self::after($lastSentAt, $interval);
    }

    /** "6 month(s)", for command output. */
    public static function describe(?array $interval = null): string
    {
        [$count, $unit] = $interval ?? self::interval();

        return "{$count} {$unit}(s)";
    }
}
