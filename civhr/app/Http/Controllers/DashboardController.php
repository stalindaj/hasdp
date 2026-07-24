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
                // Only approved trainings count toward the target.
                'ldEntries' => fn ($q) => $q->approved()->whereYear('date', $year),
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

        // L&D submissions waiting for a decision, with their proof images.
        $pendingLd = \App\Models\LdEntry::with('employee:id,first_name,last_name')
            ->where('status', \App\Models\LdEntry::PENDING)
            ->oldest()
            ->get()
            ->map(fn ($l) => [
                'id'       => $l->id,
                'employee' => trim($l->employee->first_name.' '.$l->employee->last_name),
                'title'    => $l->title,
                'hours'    => (float) $l->hours,
                'date'     => $l->date->format('M j, Y'),
                'certificate' => $l->certificate_path ? route('ld.file', [$l, 'certificate']) : null,
                'photo'       => $l->photo_path ? route('ld.file', [$l, 'photo']) : null,
            ]);

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
                    'pending'     => $pendingLd->count(),
                ],
            ],
            'pendingLeaves' => $pendingLeaves,
            'pendingLd'     => $pendingLd,
        ]);
    }

    /** Employee (or an admin previewing as one): their own status. */
    private function employeeDashboard($user, int $year)
    {
        $target = (float) config('agency.ld_target_hours');
        $e = $user->employee;

        $ipcr = $e?->ipcrRecords()->where('year', $year)->first();
        // The employee sees everything they submitted (with its status);
        // only approved hours count toward the target.
        $ldEntries = $e
            ? $e->ldEntries()->whereYear('date', $year)->orderByDesc('date')->get()
            : collect();
        $ldHours = (float) $ldEntries->where('status', \App\Models\LdEntry::APPROVED)->sum('hours');

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
                    'title'   => $l->title,
                    'hours'   => (float) $l->hours,
                    'date'    => $l->date->format('M j, Y'),
                    'status'  => $l->status,
                    'remarks' => $l->remarks,
                    'certificate' => $l->certificate_path ? route('ld.file', [$l, 'certificate']) : null,
                    'photo'       => $l->photo_path ? route('ld.file', [$l, 'photo']) : null,
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
        $ldApproved = $ldEntries->where('status', \App\Models\LdEntry::APPROVED);

        return Inertia::render('Dashboard/Employee', [
            'year' => $year,
            'ldTarget' => $target,
            // For recording a leave the employee already took.
            'leaveTypes' => \App\Models\LeaveType::active()->get(['id', 'name', 'day_basis']),
            'holidays' => \App\Models\Holiday::orderBy('date')->get()
                ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name]),
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
            'forced'   => CreditLedger::forcedLeaveStatus($employee),
            'ledger'   => CreditLedger::history($employee),
            'ld' => [
                'hours'   => (float) $ldApproved->sum('hours'),
                'pending' => max(0, $target - (float) $ldApproved->sum('hours')),
                'entries' => $ldEntries->map(fn ($l) => [
                    'id'      => $l->id,
                    'title'   => $l->title,
                    'hours'   => (float) $l->hours,
                    'date'    => $l->date->format('M j, Y'),
                    'status'  => $l->status,
                    'remarks' => $l->remarks,
                    'certificate' => $l->certificate_path ? route('ld.file', [$l, 'certificate']) : null,
                    'photo'       => $l->photo_path ? route('ld.file', [$l, 'photo']) : null,
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
                    // Keyed in by an admin rather than filed by the employee.
                    'recorded' => (bool) $a->recorded_by,
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

    /**
     * Admin records a leave the employee already took — a paper CS Form 6, or
     * anything from before go-live. It is stored as an approved application
     * (flagged as admin-recorded), so it deducts credits through the ledger
     * and shows up in the employee's leave log, days-used total and forced-
     * leave tracking exactly like a leave filed in the system.
     */
    public function recordLeave(Request $request, Employee $employee)
    {
        $user = $request->user();

        $type = \App\Models\LeaveType::find($request->input('leave_type_id'));

        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'date_from'     => ['required', 'date'],
            'date_to'       => ['required', 'date', 'after_or_equal:date_from'],
            // Prefilled from the dates, but the paper record wins if it
            // differs (half-days, exigency cancellations, and the like).
            'working_days'  => ['required', 'numeric', 'min:0.5', 'max:365'],
            'remarks'       => ['nullable', 'string', 'max:255'],
        ]);

        $application = LeaveApplication::create([
            'user_id'     => $employee->user?->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $data['leave_type_id'],

            'office_department'     => $employee->office_department,
            'applicant_last_name'   => $employee->last_name,
            'applicant_first_name'  => $employee->first_name,
            'applicant_middle_name' => $employee->middle_name,
            'position'              => $employee->position,
            'date_filing'           => $data['date_from'],

            'working_days' => $data['working_days'],
            'date_from'    => $data['date_from'],
            'date_to'      => $data['date_to'],
            'commutation'  => 'not_requested',

            // Already taken, so it lands approved and fully paid.
            'status'        => LeaveWorkflow::APPROVED,
            'decision'      => 'approved',
            'days_with_pay' => $data['working_days'],
            'decided_at'    => now(),
            'recorded_by'   => $user->id,
        ]);

        // The type and day count are already shown alongside the entry, so
        // the remark carries only what the admin typed.
        LeaveWorkflow::log(
            $application,
            $user,
            'recorded by admin',
            null,
            LeaveWorkflow::APPROVED,
            $data['remarks'] ?? null
        );

        CreditLedger::applyForApplication($application->fresh(['leaveType', 'employee']));

        return back()->with('success', sprintf(
            'Recorded %s day/s of %s for %s.',
            $data['working_days'],
            $type?->name,
            $employee->first_name
        ));
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
