<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\LeaveCredits;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Employees/Index', [
            'employees' => Employee::orderBy('last_name')->orderBy('first_name')->get()
                ->map(fn ($e) => [
                    'id'              => $e->id,
                    'name'            => trim($e->last_name.', '.$e->first_name),
                    'rank'            => $e->rank,
                    'position'        => $e->position,
                    'designation'     => $e->designation,
                    'date_orig_appt'  => optional($e->date_orig_appt)->format('Y-m-d'),
                    'date_assumption' => optional($e->date_assumption)->format('Y-m-d'),
                    // A live preview of the accrual the leave form will offer.
                    'credit_estimate' => LeaveCredits::earned(LeaveCredits::serviceStart($e)),
                ]),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'rank'            => ['nullable', 'string', 'max:30'],
            'position'        => ['nullable', 'string', 'max:255'],
            'designation'     => ['nullable', 'string', 'max:255'],
            'date_orig_appt'  => ['nullable', 'date'],
            'date_assumption' => ['nullable', 'date'],
        ]);

        $employee->update($data);

        return back()->with('success', 'Employee updated.');
    }
}
