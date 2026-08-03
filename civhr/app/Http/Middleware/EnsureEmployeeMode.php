<?php

namespace App\Http\Middleware;

use App\Support\LeaveWorkflow;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the employee-only acts (filing a leave). An admin wearing the admin
 * hat is bounced to the requests queue with a nudge to switch hats, rather
 * than shown a bare 403 — switching is one click away in the top bar.
 */
class EnsureEmployeeMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! LeaveWorkflow::canFile($request->user())) {
            return redirect()->route('leave.requests')->with(
                'error',
                'You are in admin mode. Switch to employee mode (top right) to file your own leave.'
            );
        }

        return $next($request);
    }
}
