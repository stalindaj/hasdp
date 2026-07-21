<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! ($user->hasRole('admin') || $user->hasRole('superadmin'))) {
            abort(403, 'You do not have access to this area.');
        }

        // An admin previewing the employee experience loses admin access until
        // they switch back.
        if (session('view_mode') === 'employee') {
            abort(403, 'You are viewing as an employee. Switch back to admin to access this area.');
        }

        return $next($request);
    }
}