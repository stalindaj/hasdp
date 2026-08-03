<?php

namespace App\Http\Middleware;

use App\Support\ViewMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stricter than EnsureAdmin: the audit trail is for the system owner only,
 * so ordinary admins (who appear *in* the trail) cannot read it.
 */
class EnsureSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->hasRole('superadmin'), 403, 'You do not have access to this area.');

        if (ViewMode::isEmployee()) {
            abort(403, 'You are viewing as an employee. Switch back to admin to access this area.');
        }

        return $next($request);
    }
}
