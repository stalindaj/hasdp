<?php

namespace App\Support;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * 6.C — NUMBER OF WORKING DAYS APPLIED FOR, computed from the inclusive
 * dates. Weekends and the holidays table are skipped, except for leave types
 * counted in calendar days (leave_types.day_basis = 'calendar', e.g.
 * maternity's 105 days under R.A. 11210).
 */
class WorkingDays
{
    /** Count the days from $from to $to inclusive, on the given basis. */
    public static function count(CarbonInterface $from, CarbonInterface $to, string $basis = 'working'): int
    {
        $from = CarbonImmutable::parse($from->toDateString());
        $to   = CarbonImmutable::parse($to->toDateString());

        if ($to->lessThan($from)) {
            return 0;
        }

        if ($basis === 'calendar') {
            return (int) $from->diffInDays($to) + 1;
        }

        $holidays = self::holidayDates($from, $to);

        $days = 0;
        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            if (! $day->isWeekend() && ! isset($holidays[$day->toDateString()])) {
                $days++;
            }
        }

        return $days;
    }

    /** ['Y-m-d' => holiday name] within the range — for messages and the UI. */
    public static function holidayDates(CarbonInterface $from, CarbonInterface $to): array
    {
        // whereDate on both bounds — robust to the cast's storage format
        // (a plain BETWEEN can miss a holiday that lands exactly on $to).
        return Holiday::whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name])
            ->all();
    }
}
