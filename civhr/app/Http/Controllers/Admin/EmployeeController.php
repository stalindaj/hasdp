<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\LeaveCredits;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        // Deactivated staff are archived out of the list; a toggle brings them
        // back so records are never lost.
        $showArchived = $request->boolean('archived');

        return Inertia::render('Admin/Employees/Index', [
            'showArchived' => $showArchived,
            'archivedCount' => Employee::archived()->count(),
            'employees' => Employee::with('user:id,employee_id,is_active')
                ->when(! $showArchived, fn ($q) => $q->active())
                ->orderBy('last_name')->orderBy('first_name')->get()
                ->map(fn ($e) => [
                    'id'              => $e->id,
                    'name'            => trim($e->last_name.', '.$e->first_name),
                    'archived'        => $e->archived,
                    // The whole plantilla (PSIPOP) record is editable here —
                    // My Profile shows these read-only and points at HR.
                    'emp_no'           => $e->emp_no,
                    'item_no'          => $e->item_no,
                    'psipop_placement' => $e->psipop_placement,
                    'last_name'        => $e->last_name,
                    'first_name'       => $e->first_name,
                    'middle_name'      => $e->middle_name,
                    'suffix'           => $e->suffix,
                    'sex'              => $e->sex,
                    'level'            => $e->level,
                    'salary_grade'     => $e->salary_grade,
                    'step_increment'   => $e->step_increment,
                    'rank'             => $e->rank,
                    'is_civilian'      => $e->is_civilian ? '1' : '0',
                    'position'         => $e->position,
                    'designation'      => $e->designation,
                    'office_department'=> $e->office_department,
                    'email'            => $e->email,
                    'contact_no'       => $e->contact_no,
                    'date_of_birth'    => optional($e->date_of_birth)->format('Y-m-d'),
                    'date_orig_appt'   => optional($e->date_orig_appt)->format('Y-m-d'),
                    'date_assumption'  => optional($e->date_assumption)->format('Y-m-d'),
                    'date_of_promotion'=> optional($e->date_of_promotion)->format('Y-m-d'),
                    // A live preview of the accrual the leave form will offer.
                    'credit_estimate' => LeaveCredits::earned(LeaveCredits::serviceStart($e)),
                ]),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            // Identity / plantilla
            'emp_no'           => ['nullable', 'string', 'max:50', Rule::unique('employees', 'emp_no')->ignore($employee->id)],
            'item_no'          => ['nullable', 'string', 'max:255'],
            'psipop_placement' => ['nullable', 'string', 'max:255'],
            'last_name'        => ['nullable', 'string', 'max:255'],
            'first_name'       => ['nullable', 'string', 'max:255'],
            'middle_name'      => ['nullable', 'string', 'max:255'],
            'suffix'           => ['nullable', 'string', 'max:50'],
            'sex'              => ['nullable', Rule::in(['Male', 'Female'])],
            'date_of_birth'    => ['nullable', 'date', 'before:today'],

            // Appointment
            'level'          => ['nullable', 'string', 'max:255'],
            'salary_grade'   => ['nullable', 'integer', 'min:1', 'max:33'],
            'step_increment' => ['nullable', 'integer', 'min:1', 'max:8'],
            'rank'           => ['nullable', 'string', 'max:30'],
            // Optional so a partial update never silently flips someone's
            // personnel type; the edit form always sends it.
            'is_civilian'    => ['sometimes', 'boolean'],
            'position'       => ['nullable', 'string', 'max:255'],
            'designation'    => ['nullable', 'string', 'max:255'],
            'office_department' => ['nullable', 'string', 'max:255'],
            'date_orig_appt'    => ['nullable', 'date'],
            'date_assumption'   => ['nullable', 'date'],
            'date_of_promotion' => ['nullable', 'date'],

            // Contact
            'email'      => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee->id)],
            'contact_no' => ['nullable', 'string', 'max:50'],
        ]);

        $employee->update($data);

        // The employee number doubles as the login username, so a correction
        // here must follow through to the account or the person is locked out.
        if ($employee->user && ! empty($data['emp_no'])) {
            $employee->user->update(['username' => $data['emp_no']]);
        }

        return back()->with('success', 'Employee record updated.');
    }
}
