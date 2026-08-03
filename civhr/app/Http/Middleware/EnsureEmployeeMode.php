<?php

namespace App\Http\Middleware;

use App\Support\LeaveWorkflow;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the employee-only areas (filing a leave, the My Profile self-service
 * record). An admin wearing the admin hat is bounced to their dashboard with a
 * nudge to switch hats, rather than shown a bare 403 — switching is one click
 * away in the top bar.
 */
class EnsureEmployeeMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! LeaveWorkflow::canFile($request->user())) {
            return redirect()->route('dashboard')->with(
                'error',
                'You are in admin mode. Switch to employee mode (top right) to reach your own employee records.'
            );
        }

        return $next($request);
    }
}
