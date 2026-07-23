<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\IpcrRecord;
use App\Models\LeaveApplication;
use App\Support\CreditLedger;
use App\Support\LeaveWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $year = now()->year;

        // Admins see everyone — unless they've switched to employee view.
        $actsAsAdmin = LeaveWorkflow::isAdmin($user)
            && $request->session()->get('view_mode') !== 'employee';

        return $actsAsAdmin
            ? $this->adminDashboard($year)
            : $this->employeeDashboard($user, $year);
    }

    /** Admin: every employee's IPCR / leave / L&D status at a glance. */
    private function adminDashboard(int $year)
    {
        $target = (float) config('agency.ld_target_hours');

        $employees = $this->plantilla()
            ->with([
                'ipcrRecords' => fn ($q) => $q->where('year', $year),
                'ldEntries' => fn ($q) => $q->whereYear('date', $year),
            ])
            ->orderBy('last_name')
            ->get();

        $rows = $employees->map(function ($e) use ($year, $target) {
            $ipcr = $e->ipcrRecords->first();
            $ldHours = (float) $e->ldEntries->sum('hours');

            // Ledger stays up to date even before anyone opens the card.
            CreditLedger::ensureUpToDate($e);

            $apps = LeaveApplication::where('employee_id', $e->id)->get();
            $used = (float) $apps
                ->where('status', LeaveWorkflow::APPROVED)
                ->filter(fn ($a) => optional($a->date_from)->year === $year)
                ->sum(fn ($a) => (float) ($a->days_with_pay ?? $a->working_days));

            return [
                'id'         => $e->id,
                'emp_no'     => $e->emp_no,
                'name'       => trim($e->last_name.', '.$e->first_name),
                'sem1'       => (bool) ($ipcr?->sem1_done),
                'sem2'       => (bool) ($ipcr?->sem2_done),
                'leave_used'    => $used,
                'leave_pending' => $apps->where('status', LeaveWorkflow::PENDING)->count(),
                'ld_hours'   => $ldHours,
                'ld_pending' => max(0, $target - $ldHours),
            ];
        });

        $pendingLeaves = LeaveApplication::with(['user:id,name', 'leaveType:id,name,code'])
            ->where('status', LeaveWorkflow::PENDING)
            ->oldest()
            ->get()
            ->map(fn ($a) => [
                'id'        => $a->id,
                'applicant' => $a->applicant_name ?: $a->user->name,
                'type'      => $a->leaveType->name,
                'inclusive' => $a->inclusive_dates_text,
                'days'      => (float) $a->working_days,
            ]);

        return Inertia::render('Dashboard', [
            'mode' => 'admin',
            'year' => $year,
            'ldTarget' => $target,
            'rows' => $rows,
            'boxes' => [
                'ipcr' => [
                    'sem1_done' => $rows->where('sem1', true)->count(),
                    'sem2_done' => $rows->where('sem2', true)->count(),
                    'total'     => $rows->count(),
                ],
                'leave' => [
                    'pending'   => $pendingLeaves->count(),
                    'used_days' => round($rows->sum('leave_used'), 1),
                ],
                'ld' => [
                    'total_hours' => round($rows->sum('ld_hours'), 1),
                    'behind'      => $rows->where('ld_pending', '>', 0)->count(),
                ],
            ],
            'pendingLeaves' => $pendingLeaves,
        ]);
    }

    /** Employee (or an admin previewing as one): their own status. */
    private function employeeDashboard($user, int $year)
    {
        $target = (float) config('agency.ld_target_hours');
        $e = $user->employee;

        $ipcr = $e?->ipcrRecords()->where('year', $year)->first();
        $ldEntries = $e
            ? $e->ldEntries()->whereYear('date', $year)->orderByDesc('date')->get()
            : collect();
        $ldHours = (float) $ldEntries->sum('hours');

        $balances = $e ? CreditLedger::balances($e) : null;
        $pending = $e
            ? LeaveApplication::where('employee_id', $e->id)->where('status', LeaveWorkflow::PENDING)->count()
            : 0;

        return Inertia::render('Dashboard', [
            'mode' => 'employee',
            'year' => $year,
            'ldTarget' => $target,
            'me' => [
                'name'   => $user->name,
                'emp_no' => $e?->emp_no,
                'sem1'   => (bool) ($ipcr?->sem1_done),
                'sem2'   => (bool) ($ipcr?->sem2_done),
                'balances'      => $balances,
                'leave_pending' => $pending,
                'ld_hours'      => $ldHours,
                'ld_pending'    => max(0, $target - $ldHours),
                'ld_entries'    => $ldEntries->map(fn ($l) => [
                    'title' => $l->title,
                    'hours' => (float) $l->hours,
                    'date'  => $l->date->format('M j, Y'),
                ]),
            ],
        ]);
    }

    /** Admin: one employee's full card — IPCR, balances + ledger, L&D, leaves. */
    public function showEmployee(Request $request, Employee $employee)
    {
        abort_unless(
            LeaveWorkflow::isAdmin($request->user())
            && $request->session()->get('view_mode') !== 'employee',
            403
        );

        $year = now()->year;
        $target = (float) config('agency.ld_target_hours');

        $ldEntries = $employee->ldEntries()->whereYear('date', $year)->orderByDesc('date')->get();

        return Inertia::render('Dashboard/Employee', [
            'year' => $year,
            'ldTarget' => $target,
            'employee' => [
                'id'      => $employee->id,
                'emp_no'  => $employee->emp_no,
                'name'    => trim($employee->first_name.' '.$employee->last_name),
                'position'=> $employee->position,
                'contact' => $employee->contact_no,
                'birthday'=> optional($employee->date_of_birth)->format('M j, Y'),
                'last_ape'=> optional($employee->last_ape_date)->format('M j, Y'),
            ],
            // Every year on record, so past compliance stays visible.
            'ipcr' => $employee->ipcrRecords()->orderByDesc('year')->get()
                ->map(fn ($r) => [
                    'year' => $r->year,
                    'sem1' => (bool) $r->sem1_done,
                    'sem2' => (bool) $r->sem2_done,
                ]),
            'balances' => CreditLedger::balances($employee),
            'ledger'   => CreditLedger::history($employee),
            'ld' => [
                'hours'   => (float) $ldEntries->sum('hours'),
                'pending' => max(0, $target - (float) $ldEntries->sum('hours')),
                'entries' => $ldEntries->map(fn ($l) => [
                    'id'    => $l->id,
                    'title' => $l->title,
                    'hours' => (float) $l->hours,
                    'date'  => $l->date->format('M j, Y'),
                ]),
            ],
            'leaves' => LeaveApplication::with('leaveType:id,name')
                ->where('employee_id', $employee->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn ($a) => [
                    'id'     => $a->id,
                    'type'   => $a->leaveType->name,
                    'days'   => (float) $a->working_days,
                    'when'   => $a->inclusive_dates_text,
                    'status' => $a->status,
                    'status_label' => LeaveWorkflow::label($a->status),
                ]),
        ]);
    }

    /** Admin edits a balance by posting an adjustment to the ledger. */
    public function adjustCredit(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'kind'   => ['required', Rule::in(['vl', 'sl', 'wellness', 'spl'])],
            'amount' => ['required', 'numeric', 'not_in:0', 'min:-999', 'max:999'],
            'note'   => ['nullable', 'string', 'max:255'],
        ]);

        CreditLedger::adjust(
            $employee,
            $data['kind'],
            (float) $data['amount'],
            $data['note'] ?? null,
            $request->user()->id
        );

        return back()->with('success', 'Balance adjusted.');
    }

    /** Admin ticks/unticks an IPCR semester for an employee. */
    public function toggleIpcr(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'sem' => ['required', Rule::in([1, 2, '1', '2'])],
        ]);

        $record = IpcrRecord::firstOrCreate([
            'employee_id' => $employee->id,
            'year'        => now()->year,
        ]);

        $field = 'sem'.$data['sem'].'_done';
        $record->update([$field => ! $record->{$field}]);

        return back();
    }

    /** Admin logs an L&D training for an employee. */
    public function storeLd(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:999'],
            'date'  => ['required', 'date'],
        ]);

        $employee->ldEntries()->create($data);

        return back()->with('success', "L&D logged for {$employee->first_name} {$employee->last_name}.");
    }

    /** Plantilla staff only — the 7.C/D signatory record is excluded. */
    private function plantilla()
    {
        return Employee::query()
            ->whereNotNull('emp_no')
            ->where('emp_no', '!=', 'mission');
    }
}
