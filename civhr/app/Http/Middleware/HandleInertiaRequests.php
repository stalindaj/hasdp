<?php

namespace App\Http\Middleware;

use App\Support\ViewMode;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $roles = $user ? $user->roles()->pluck('name') : collect();
        $hasAdminRole = $roles->contains('admin') || $roles->contains('superadmin');
        // An admin wears one hat at a time: in employee mode their admin
        // access is off, on screen and server-side alike (EnsureAdmin and
        // LeaveWorkflow::isAdmin enforce the same rule).
        $asEmployee = $hasAdminRole && ViewMode::isEmployee();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'roles' => $roles,
                'isAdmin' => $hasAdminRole && ! $asEmployee,
                // The audit trail is superadmin-only, so the nav link is too.
                'isSuperadmin' => $roles->contains('superadmin') && ! $asEmployee,
                'canSwitchView' => $hasAdminRole,
                'viewMode' => $asEmployee ? ViewMode::EMPLOYEE : ViewMode::ADMIN,
            ],
            // added: one-off flash messages (used by the create-user form)
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }
}