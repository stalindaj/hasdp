<?php

namespace App\Support;

/**
 * A rating period is a semester: January–June or July–December. Each employee
 * files exactly one IWOT and one IPCR per semester — two of each a year, never
 * more (enforced by a unique index on user_id + year + semester, and checked
 * again on save so the message is a sentence rather than a SQL error).
 */
class RatingPeriod
{
    public const SEMESTERS = [
        1 => 'January - June',
        2 => 'July - December',
    ];

    /** The printed period, e.g. "January - June 2026". */
    public static function label(?int $year, ?int $semester): string
    {
        if (! $year || ! isset(self::SEMESTERS[$semester])) {
            return '';
        }

        return self::SEMESTERS[$semester].' '.$year;
    }

    /** Short form for lists, e.g. "2026 · 1st sem". */
    public static function short(?int $year, ?int $semester): string
    {
        if (! $year || ! $semester) {
            return '—';
        }

        return $year.' · '.($semester === 1 ? '1st' : '2nd').' sem';
    }

    /** The years worth offering in the picker: last year through next. */
    public static function years(): array
    {
        $now = (int) now()->year;

        return range($now - 2, $now + 1);
    }

    /** Which semester today falls in. */
    public static function currentSemester(): int
    {
        return (int) now()->month <= 6 ? 1 : 2;
    }
}
