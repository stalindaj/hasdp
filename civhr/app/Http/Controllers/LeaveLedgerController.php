<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveCreditEntry;
use App\Support\CreditLedger;
use App\Support\LeaveWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The employee's VL/SL leave card — the CSC-style ledger the office keeps by
 * hand (Name / Division / 1st Day of Service, then Vacation and Sick leave
 * side by side with Earned · Absent w/pay · Balance · Absent w/o pay, a
 * running total, and the Prepared / Certified / Noted signatures).
 *
 * It is built straight from the credit ledger, so the printed balances always
 * equal the app's live balances. Printed and signed by pen, like CS Form 6.
 */
class LeaveLedgerController extends Controller
{
    public function print(Request $request, Employee $employee)
    {
        abort_unless(LeaveWorkflow::isAdmin($request->user()), 403);

        // Make sure every monthly accrual up to now is posted first.
        CreditLedger::balances($employee);

        $rows = $this->rows($employee);

        // The certifiers, from the same roles that sign CS Form 6.
        $certifier = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'hr_officer'))
            ->with('employee')->where('is_active', true)->orderBy('name')->first();
        $approver = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'approver'))
            ->with('employee')->where('is_active', true)->orderBy('name')->first();

        return view('leave.ledger', [
            'employee'  => $employee,
            'office'    => $employee->office_department,
            'firstDay'  => $employee->date_orig_appt
                ? Carbon::parse($employee->date_orig_appt)->format('F j, Y')
                : optional($employee->credits_accrual_start)->format('F j, Y'),
            'rows'      => $rows,
            'vlBalance' => end($rows) ? $rows[array_key_last($rows)]['vl_bal'] : 0,
            'slBalance' => end($rows) ? $rows[array_key_last($rows)]['sl_bal'] : 0,
            'certifier' => $certifier,
            'approver'  => $approver,
            'agencyUnit'=> config('agency.unit'),
        ]);
    }

    /**
     * Every ledger line in chronological order, carrying running VL and SL
     * balances. Accruals for a year collapse into one "earned" row; each
     * approved leave and each manual adjustment is its own row.
     */
    private function rows(Employee $employee): array
    {
        $entries = LeaveCreditEntry::where('employee_id', $employee->id)
            ->whereIn('kind', ['vl', 'sl'])
            ->orderBy('id')
            ->get();

        // 1) Yearly accrual totals (the monthly +1.25 rows, summed per year).
        $accruals = [];   // [year => ['vl' => x, 'sl' => y, 'months' => [..]]]
        foreach ($entries as $e) {
            if ($e->period === null) {
                continue;
            }
            $year = (int) substr($e->period, 0, 4);
            $accruals[$year] ??= ['vl' => 0.0, 'sl' => 0.0, 'months' => []];
            $accruals[$year][$e->kind] += (float) $e->amount;
            $accruals[$year]['months'][substr($e->period, 5, 2)] = true;
        }

        $events = [];
        foreach ($accruals as $year => $a) {
            $months = array_keys($a['months']);
            sort($months);
            $first = Carbon::createFromFormat('Y-m', $year.'-'.$months[0])->startOfMonth();
            $last  = Carbon::createFromFormat('Y-m', $year.'-'.end($months))->endOfMonth();
            $events[] = [
                'sort'      => $first->timestamp,
                'period'    => $this->periodLabel($first, $last),
                'particulars' => '',
                'vl_earned' => round($a['vl'], 3),
                'sl_earned' => round($a['sl'], 3),
                'vl_wpay'   => null, 'vl_wopay' => null,
                'sl_wpay'   => null, 'sl_wopay' => null,
            ];
        }

        // 2) Approved leaves — the absences, on whichever side they draw from.
        $leaves = LeaveApplication::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveWorkflow::APPROVED)
            ->get();

        foreach ($leaves as $l) {
            $kind = $l->leaveType?->credit_kind;
            if (! in_array($kind, ['vl', 'sl'], true)) {
                continue;   // wellness/SPL/etc. don't post to the VL/SL card
            }
            $from = $l->date_from ? Carbon::parse($l->date_from) : $l->created_at;
            $events[] = [
                'sort'      => $from->timestamp,
                'period'    => '',
                'particulars' => trim($l->inclusive_dates_text.' ('.strtoupper($kind).')'),
                'vl_earned' => null, 'sl_earned' => null,
                'vl_wpay'   => $kind === 'vl' ? (float) $l->days_with_pay : null,
                'vl_wopay'  => $kind === 'vl' ? (float) $l->days_without_pay : null,
                'sl_wpay'   => $kind === 'sl' ? (float) $l->days_with_pay : null,
                'sl_wopay'  => $kind === 'sl' ? (float) $l->days_without_pay : null,
            ];
        }

        // 3) Manual adjustments (opening balances, corrections, forfeitures).
        foreach ($entries as $e) {
            if ($e->period !== null || $e->leave_application_id !== null) {
                continue;
            }
            $amt = (float) $e->amount;
            $events[] = [
                'sort'        => $e->created_at->timestamp,
                'period'      => '',
                'particulars' => $this->cleanNote($e->note),
                'vl_earned'   => $e->kind === 'vl' && $amt > 0 ? $amt : null,
                'sl_earned'   => $e->kind === 'sl' && $amt > 0 ? $amt : null,
                'vl_wpay'     => $e->kind === 'vl' && $amt < 0 ? abs($amt) : null,
                'vl_wopay'    => null,
                'sl_wpay'     => $e->kind === 'sl' && $amt < 0 ? abs($amt) : null,
                'sl_wopay'    => null,
            ];
        }

        usort($events, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        // Walk the timeline, carrying running balances (Balance = prior ± this).
        $vl = 0.0; $sl = 0.0; $rows = [];
        foreach ($events as $ev) {
            $vl += ($ev['vl_earned'] ?? 0) - ($ev['vl_wpay'] ?? 0);
            $sl += ($ev['sl_earned'] ?? 0) - ($ev['sl_wpay'] ?? 0);
            $rows[] = $ev + [
                'vl_bal' => round($vl, 3),
                'sl_bal' => round($sl, 3),
                'total'  => round($vl + $sl, 3),
            ];
        }

        return $rows;
    }

    private function periodLabel(Carbon $from, Carbon $to): string
    {
        // A full calendar year prints the familiar "01 Jan - 31 Dec YYYY".
        if ($from->month === 1 && $to->month === 12) {
            return '01 Jan - 31 Dec '.$from->year;
        }

        return $from->format('d M').' - '.$to->format('d M Y');
    }

    /** Ledger notes carry a "— by user #7" tag that the card doesn't need. */
    private function cleanNote(?string $note): string
    {
        return trim(preg_replace('/\s*—?\s*by user #\d+\s*$/u', '', (string) $note));
    }
}
