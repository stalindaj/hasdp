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
 */
class CreditLedger
{
    public const MONTHLY_ACCRUAL = 1.25;

    /** Kinds that live fully in the ledger (accrue + carry over). */
    public const ACCRUING = ['vl', 'sl'];

    /** Post any missing monthly VL/SL accruals up to the current month. */
    public static function ensureUpToDate(Employee $employee): void
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
                - self::approvedDaysThisYear($employee, 'wellness', $year),
                2
            ),
            'spl' => round(
                (float) config('agency.spl_days')
                + self::adjustmentsThisYear($employee, 'spl')
                - self::approvedDaysThisYear($employee, 'spl', $year),
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

    private static function approvedDaysThisYear(Employee $employee, string $kind, int $year): float
    {
        return (float) LeaveApplication::where('employee_id', $employee->id)
            ->where('status', LeaveWorkflow::APPROVED)
            ->whereHas('leaveType', fn ($q) => $q->where('credit_kind', $kind))
            ->whereYear('date_from', $year)
            ->get()
            ->sum(fn ($a) => (float) ($a->days_with_pay ?? $a->working_days));
    }
}
