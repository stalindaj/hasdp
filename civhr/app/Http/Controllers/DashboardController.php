<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\IpcrRecord;
use App\Models\LeaveApplication;
use App\Support\LeaveCredits;
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

        $employees = Employee::with([
            'user:id,name,employee_id',
            'ipcrRecords' => fn ($q) => $q->where('year', $year),
            'ldEntries' => fn ($q) => $q->whereYear('date', $year),
        ])
            ->whereNotNull('emp_no')
            ->where('emp_no', '!=', 'mission')   // signatory, not plantilla staff
            ->orderBy('last_name')
            ->get();

        $rows = $employees->map(function ($e) use ($year, $target) {
            $ipcr = $e->ipcrRecords->first();
            $ldHours = (float) $e->ldEntries->sum('hours');

            [$leaveUsed, $leavePending, $leaveBalance] = $this->leaveNumbers($e, $year);

            return [
                'id'         => $e->id,
                'emp_no'     => $e->emp_no,
                'name'       => trim($e->last_name.', '.$e->first_name),
                'sem1'       => (bool) ($ipcr?->sem1_done),
                'sem2'       => (bool) ($ipcr?->sem2_done),
                'leave_used'    => $leaveUsed,
                'leave_pending' => $leavePending,
                'leave_balance' => $leaveBalance,
                'ld_hours'      => $ldHours,
                'ld_pending'    => max(0, $target - $ldHours),
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

        [$leaveUsed, $leavePending, $leaveBalance] = $e
            ? $this->leaveNumbers($e, $year)
            : [0.0, 0, null];

        return Inertia::render('Dashboard', [
            'mode' => 'employee',
            'year' => $year,
            'ldTarget' => $target,
            'me' => [
                'name'   => $user->name,
                'emp_no' => $e?->emp_no,
                'sem1'   => (bool) ($ipcr?->sem1_done),
                'sem2'   => (bool) ($ipcr?->sem2_done),
                'leave_used'    => $leaveUsed,
                'leave_pending' => $leavePending,
                'leave_balance' => $leaveBalance,
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

    /** [approved days used this year, pending count, VL balance estimate|null] */
    private function leaveNumbers(Employee $e, int $year): array
    {
        $apps = LeaveApplication::where('employee_id', $e->id)->get();

        $used = (float) $apps
            ->where('status', LeaveWorkflow::APPROVED)
            ->filter(fn ($a) => optional($a->date_from)->year === $year)
            ->sum('days_with_pay');

        $pending = $apps->where('status', LeaveWorkflow::PENDING)->count();

        // Gross service accrual minus days used — an estimate, only when the
        // appointment date is on record.
        $start = LeaveCredits::serviceStart($e);
        $balance = $start ? round(LeaveCredits::earned($start) - $used, 2) : null;

        return [$used, $pending, $balance];
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
}
