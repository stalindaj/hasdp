<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveCreditEntry;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

/**
 * The leave-credit ledger.
 *
 * VL and SL accrue +1.25 on the 1st of every month, are adjusted by admins,
 * and are deducted when a leave is approved. Balances are simply the sum of
 * ledger rows — nothing is ever edited in place, so every number is auditable.
 *
 * Accrual is "lazy": whenever balances are read, any missing monthly rows
 * since the employee's accrual start are inserted (idempotent via the unique
 * [employee, kind, period] key). No cron needed — important on shell-less
 * shared hosting.
 *
 * Wellness Leave (5/yr) and Special Privilege Leave (3/yr) are annual
 * entitlements that reset each year: remaining = entitlement + this year's
 * adjustments − this year's approved days.
 *
 * At the turn of each year the unused part of the 5 mandatory/forced VL days
 * is forfeited, so a full year carries forward 10 VL and 15 SL rather than
 * 15 and 15. See {@see ensureForfeitures()}.
 */
class CreditLedger
{
    public const MONTHLY_ACCRUAL = 1.25;

    /** Kinds that live fully in the ledger (accrue + carry over). */
    public const ACCRUING = ['vl', 'sl'];

    /** Prefix of the event_key used for the year-end forfeiture rows. */
    private const FORFEIT_KEY = 'forfeit-fl-';

    /**
     * Bring the ledger up to today: post any missing monthly VL/SL accruals,
     * then forfeit unused mandatory leave for every year that has closed.
     */
    public static function ensureUpToDate(Employee $employee): void
    {
        self::ensureAccruals($employee);
        self::ensureForfeitures($employee);
    }

    /** Post any missing monthly VL/SL accruals up to the current month. */
    private static function ensureAccruals(Employee $employee): void
    {
        if (! $employee->credits_accrual_start) {
            // Accrual begins the month the employee first enters the ledger.
            $employee->credits_accrual_start = now()->startOfMonth();
            $employee->save();
        }

        $start = Carbon::parse($employee->credits_accrual_start)->startOfMonth();
        $end = now()->startOfMonth();

        if ($start->greaterThan($end)) {
            return;
        }

        $existing = LeaveCreditEntry::where('employee_id', $employee->id)
            ->whereIn('kind', self::ACCRUING)
            ->whereNotNull('period')
            ->pluck('period', 'id')
            ->flip();

        $rows = [];
        foreach (CarbonPeriod::create($start, '1 month', $end) as $month) {
            $period = $month->format('Y-m');
            foreach (self::ACCRUING as $kind) {
                $rows[] = [
                    'employee_id' => $employee->id,
                    'kind'        => $kind,
                    'amount'      => self::MONTHLY_ACCRUAL,
                    'period'      => $period,
                    'note'        => 'Monthly accrual '.$period,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }
        }

        // The unique key keeps this idempotent under re-runs.
        LeaveCreditEntry::insertOrIgnore($rows);
    }

    /**
     * Year-end forfeiture of unused mandatory/forced leave (CSC Sec. 25,
     * Rule XVI): five of the year's VL days must be availed within that year
     * and whatever is left of them does not carry over. So a full year that
     * saw no VL taken carries forward 10 VL (and 15 SL, which never forfeits).
     *
     * Any VL availed during the year counts toward the five, and a part year
     * can only forfeit what it actually earned — someone who joined in
     * November never had five days to lose.
     *
     * Only closed years are touched; the running year is still live. The row
     * is recomputed on every read rather than written once, so back-recording
     * a paper leave into a closed year correctly gives the days back.
     */
    private static function ensureForfeitures(Employee $employee): void
    {
        $required = (float) config('agency.forced_days');
        $thisYear = now()->year;

        if ($required <= 0) {
            return;
        }

        // VL actually accrued per year, from the monthly rows.
        $accrued = LeaveCreditEntry::where('employee_id', $employee->id)
            ->where('kind', 'vl')
            ->whereNotNull('period')
            ->get()
            ->groupBy(fn ($e) => (int) substr($e->period, 0, 4))
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        foreach ($accrued as $year => $earned) {
            if ((int) $year >= $thisYear) {
                continue;
            }

            $forfeit = round(max(0, min($required, $earned) - self::approvedDaysInYear($employee, 'vl', (int) $year)), 2);
            $key = self::FORFEIT_KEY.$year;
            $match = ['employee_id' => $employee->id, 'event_key' => $key];

            if ($forfeit <= 0) {
                // The mandatory days were fully availed — nothing to forfeit.
                LeaveCreditEntry::where($match)->delete();

                continue;
            }

            LeaveCreditEntry::updateOrCreate($match, [
                'kind'   => 'vl',
                'amount' => -$forfeit,
                'period' => null,
                'note'   => 'Mandatory leave not availed in '.$year.' — forfeited',
            ]);
        }
    }

    /** Current balances for all four kinds. */
    public static function balances(Employee $employee): array
    {
        self::ensureUpToDate($employee);

        $sums = LeaveCreditEntry::where('employee_id', $employee->id)
            ->selectRaw('kind, SUM(amount) as total')
            ->groupBy('kind')
            ->pluck('total', 'kind');

        $year = now()->year;

        return [
            'vl' => round((float) ($sums['vl'] ?? 0), 2),
            'sl' => round((float) ($sums['sl'] ?? 0), 2),
            'wellness' => round(
                (float) config('agency.wellness_days')
                + self::adjustmentsThisYear($employee, 'wellness')
                - self::approvedDaysInYear($employee, 'wellness', $year),
                2
            ),
            'spl' => round(
                (float) config('agency.spl_days')
                + self::adjustmentsThisYear($employee, 'spl')
                - self::approvedDaysInYear($employee, 'spl', $year),
                2
            ),
        ];
    }

    /** An admin correction, e.g. setting an opening balance. */
    public static function adjust(Employee $employee, string $kind, float $amount, ?string $note, int $byUserId): LeaveCreditEntry
    {
        self::ensureUpToDate($employee);

        return LeaveCreditEntry::create([
            'employee_id' => $employee->id,
            'kind'        => $kind,
            'amount'      => $amount,
            'note'        => trim(($note ?: 'Adjustment').' — by user #'.$byUserId),
        ]);
    }

    /**
     * Sync the ledger with a leave decision. Called every time a leave is
     * (re)processed: old deductions for the application are replaced, so
     * re-deciding or changing the day count never double-charges.
     */
    public static function applyForApplication(LeaveApplication $application): void
    {
        LeaveCreditEntry::where('leave_application_id', $application->id)->delete();

        $kind = $application->leaveType?->credit_kind;

        if ($application->status !== LeaveWorkflow::APPROVED || ! $kind) {
            return;
        }

        // Wellness/SPL usage is derived from the applications themselves.
        if (! in_array($kind, self::ACCRUING, true)) {
            return;
        }

        $employee = $application->employee;
        if (! $employee) {
            return;
        }

        self::ensureUpToDate($employee);

        $days = (float) ($application->days_with_pay ?? $application->working_days);
        if ($days <= 0) {
            return;
        }

        LeaveCreditEntry::create([
            'employee_id'          => $employee->id,
            'leave_application_id' => $application->id,
            'kind'                 => $kind,
            'amount'               => -$days,
            'note'                 => 'Approved leave #'.$application->id.' ('.$application->leaveType->name.')',
        ]);
    }

    /**
     * Mandatory/Forced Leave compliance (CSC Sec. 25, Rule XVI): 5 VL days
     * must be used within the year; any VL availment counts toward them.
     * Unused days are forfeited at year-end — the admin applies that as an
     * adjustment (kept manual because exigency cancellations are exempt).
     */
    public static function forcedLeaveStatus(Employee $employee): array
    {
        $required = (float) config('agency.forced_days');
        $used = self::approvedDaysInYear($employee, 'vl', now()->year);

        return [
            'required'  => $required,
            'used'      => round(min($used, $required), 2),
            'remaining' => round(max(0, $required - $used), 2),
        ];
    }

    /** Ledger history, newest first, for the employee page. */
    public static function history(Employee $employee, int $limit = 50)
    {
        return LeaveCreditEntry::where('employee_id', $employee->id)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn ($e) => [
                'id'     => $e->id,
                'kind'   => strtoupper($e->kind),
                'amount' => (float) $e->amount,
                'note'   => $e->note,
                'date'   => $e->created_at->format('M j, Y'),
            ]);
    }

    private static function adjustmentsThisYear(Employee $employee, string $kind): float
    {
        return (float) LeaveCreditEntry::where('employee_id', $employee->id)
            ->where('kind', $kind)
            ->whereNull('leave_application_id')
            ->whereYear('created_at', now()->year)
            ->sum('amount');
    }

    private static function approvedDaysInYear(Employee $employee, string $kind, int $year): float
    {
        return (float) LeaveApplication::where('employee_id', $employee->id)
            ->where('status', LeaveWorkflow::APPROVED)
            ->whereHas('leaveType', fn ($q) => $q->where('credit_kind', $kind))
            ->whereYear('date_from', $year)
            ->get()
            ->sum(fn ($a) => (float) ($a->days_with_pay ?? $a->working_days));
    }
}
