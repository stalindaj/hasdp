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
        1 => '01 January to 30 June',
        2 => '01 July to 31 December',
    ];

    /** The printed period, e.g. "01 January to 30 June 2026". */
    public static function label(?int $year, ?int $semester): string
    {
        if (! $year || ! isset(self::SEMESTERS[$semester])) {
            return '';
        }

        return self::SEMESTERS[$semester].' '.$year;
    }

    /**
     * What a saved form's period should read as. Wording someone typed is
     * kept; anything this class generated itself — including the older
     * "July - December 2026" phrasing — is re-labelled from the semester, so
     * forms filed before the dates were spelled out still print them.
     */
    public static function display(?string $stored, ?int $year, ?int $semester): string
    {
        $stored = trim((string) $stored);
        $generated = $stored === '' || preg_match('/^(January - June|July - December)\s+\d{4}$/', $stored) === 1;

        return $generated ? (self::label($year, $semester) ?: $stored) : $stored;
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
