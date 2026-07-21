<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::with(['roles:id,name,label', 'employee:id,first_name,last_name'])
                ->orderBy('name')
                ->get()
                ->map(fn ($u) => [
                    'id'        => $u->id,
                    'name'      => $u->name,
                    'email'     => $u->email,
                    'is_active' => (bool) $u->is_active,
                    'roles'     => $u->roles->pluck('label'),
                    'role_ids'  => $u->roles->pluck('id'),
                    'employee_id' => $u->employee_id,
                    'employee'  => $u->employee
                        ? $u->employee->first_name.' '.$u->employee->last_name
                        : null,
                ]),
            'roles' => Role::orderBy('label')->get(['id', 'name', 'label']),
            // Employees not yet linked to a login, for linking on create/edit.
            'employees' => Employee::whereDoesntHave('user')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn ($e) => [
                    'id'   => $e->id,
                    'name' => trim($e->last_name.', '.$e->first_name),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'    => ['required', 'confirmed', Password::defaults()],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'roles'       => ['array'],
            'roles.*'     => ['exists:roles,id'],
        ]);

        $user = User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => Hash::make($data['password']),
            'employee_id' => $data['employee_id'] ?? null,
            'is_active'   => true,
        ]);

        $user->roles()->sync($data['roles'] ?? []);

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'roles'       => ['array'],
            'roles.*'     => ['exists:roles,id'],
        ]);

        $user->update([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'employee_id' => $data['employee_id'] ?? null,
        ]);

        $user->roles()->sync($data['roles'] ?? []);

        return back()->with('success', 'User updated.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', "Password reset for {$user->name}.");
    }

    public function toggleActive(Request $request, User $user)
    {
        // Never let an admin lock themselves out.
        abort_if($user->id === $request->user()->id, 403, 'You cannot deactivate your own account.');

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active
            ? "{$user->name} activated."
            : "{$user->name} deactivated.");
    }
}
