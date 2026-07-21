<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeProfileController extends Controller
{
    public function edit(Request $request)
    {
        $employee = $request->user()->employee;

        return Inertia::render('Profile/MyProfile', [
            'employee' => $employee ? [
                'emp_no'           => $employee->emp_no,
                'item_no'          => $employee->item_no,
                'psipop_placement' => $employee->psipop_placement,
                'first_name'       => $employee->first_name,
                'middle_name'      => $employee->middle_name,
                'last_name'        => $employee->last_name,
                'rank'             => $employee->rank,
                'sex'              => $employee->sex,
                'salary_grade'     => $employee->salary_grade,
                'step_increment'   => $employee->step_increment,
                'level'            => $employee->level,
                'position'         => $employee->position,
                'office_department'=> $employee->office_department,
                'date_orig_appt'   => optional($employee->date_orig_appt)->format('Y-m-d'),
                'date_assumption'  => optional($employee->date_assumption)->format('Y-m-d'),
                'age'              => $employee->age,
                'date_of_birth'    => optional($employee->date_of_birth)->format('Y-m-d'),
                'contact_no'       => $employee->contact_no,
                'tin_no'           => $employee->tin_no,
                'philhealth_no'    => $employee->philhealth_no,
                'pagibig_mid'      => $employee->pagibig_mid,
            ] : null,
        ]);
    }

    public function update(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 404, 'No employee record is linked to your account.');

        $data = $request->validate([
            'office_department' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'contact_no'    => ['nullable', 'string', 'max:50'],
            'tin_no'        => ['nullable', 'string', 'max:50'],
            'philhealth_no' => ['nullable', 'string', 'max:50'],
            'pagibig_mid'   => ['nullable', 'string', 'max:50'],
        ]);

        $employee->update($data);

        return back()->with('success', 'Profile updated.');
    }
}