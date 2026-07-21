<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Carbon;

/**
 * Vacation- and sick-leave accrual under the CSC Omnibus Rules: a full-time
 * government employee earns 1.25 days of vacation leave and 1.25 days of sick
 * leave for every month of service (15 + 15 days a year).
 *
 * IMPORTANT: this returns the *gross* credits earned over length of service.
 * It does not subtract leave already taken, monetised, or lost to leave
 * without pay — there is no leave ledger yet. Treat the result as a starting
 * estimate the HR officer edits down to the true balance before signing.
 */
class LeaveCredits
{
    public const MONTHLY_ACCRUAL = 1.25;

    /** Completed months of service between the start date and the "as of" date. */
    public static function monthsOfService(?string $start, ?string $asOf = null): int
    {
        if (! $start) {
            return 0;
        }

        $start = Carbon::parse($start)->startOfDay();
        $asOf  = $asOf ? Carbon::parse($asOf)->startOfDay() : Carbon::now()->startOfDay();

        if ($asOf->lessThanOrEqualTo($start)) {
            return 0;
        }

        return (int) $start->diffInMonths($asOf);
    }

    /** Gross days earned (VL or SL — the accrual rate is the same for both). */
    public static function earned(?string $start, ?string $asOf = null): float
    {
        return round(self::monthsOfService($start, $asOf) * self::MONTHLY_ACCRUAL, 3);
    }

    /**
     * The best available service-start date for an employee: their original
     * appointment, falling back to assumption to duty.
     */
    public static function serviceStart(?Employee $employee): ?string
    {
        if (! $employee) {
            return null;
        }

        $date = $employee->date_orig_appt ?? $employee->date_assumption;

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    /**
     * A prefilled 7.A certification for an application: gross earned credits,
     * with the days applied for deducted from whichever pool the leave draws
     * on. Everything here is editable before it prints.
     */
    public static function certificationFor(?Employee $employee, string $leaveCode, float $workingDays, ?string $asOf = null): array
    {
        $start   = self::serviceStart($employee);
        $asOf  ??= Carbon::now()->toDateString();
        $earned  = self::earned($start, $asOf);

        $fromVl = self::drawsFromVacation($leaveCode);
        $fromSl = $leaveCode === 'sick';

        $vlLess = $fromVl ? $workingDays : 0.0;
        $slLess = $fromSl ? $workingDays : 0.0;

        return [
            'cert_as_of' => $asOf,
            'vl_earned'  => $earned,
            'vl_less'    => $vlLess,
            'vl_balance' => round($earned - $vlLess, 3),
            'sl_earned'  => $earned,
            'sl_less'    => $slLess,
            'sl_balance' => round($earned - $slLess, 3),
        ];
    }

    /** Leave types that are charged against vacation-leave credits. */
    private static function drawsFromVacation(string $leaveCode): bool
    {
        return in_array($leaveCode, ['vacation', 'forced', 'special_privilege'], true);
    }
}
