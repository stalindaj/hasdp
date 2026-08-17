<?php

namespace App\Support;

use App\Models\Employee;

/**
 * The annual Learning & Development target, in hours, for an employee.
 *
 * It is driven by salary grade, per CSC/HRD policy: rank-and-file staff
 * (SG 1–14) must log 8 hours a year, supervisory and managerial staff
 * (SG 15 and up) 40. An employee with no salary grade on file is treated as
 * rank-and-file. The numbers and the threshold live in config/agency.php.
 */
class LdTarget
{
    public static function hoursFor(?Employee $employee): float
    {
        $sg = (int) ($employee?->salary_grade ?? 0);

        return $sg >= (int) config('agency.ld_supervisor_sg_min')
            ? (float) config('agency.ld_target_hours_supervisor')
            : (float) config('agency.ld_target_hours');
    }
}
