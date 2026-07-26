<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deactivating a login archives the employee out of the active rosters
 * (dashboard, balances, employees) while keeping their records.
 */
class ArchiveEmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync(Role::whereIn('name', ['admin', 'superadmin'])->pluck('id'));

        return $u->fresh();
    }

    private function staff(string $emp, string $first, bool $active = true): Employee
    {
        $e = Employee::create(['emp_no' => $emp, 'first_name' => $first, 'last_name' => 'Dela Cruz']);
        User::factory()->create(['employee_id' => $e->id, 'is_active' => $active]);

        return $e;
    }

    public function test_the_active_scope_excludes_deactivated_staff(): void
    {
        $active = $this->staff('5001', 'Ana');
        $archived = $this->staff('5002', 'Ben', active: false);
        // An employee with no login (e.g. the signatory) is never archived.
        $noAccount = Employee::create(['emp_no' => '5003', 'first_name' => 'Cy', 'last_name' => 'Reyes']);

        $activeIds = Employee::active()->pluck('id');
        $this->assertTrue($activeIds->contains($active->id));
        $this->assertTrue($activeIds->contains($noAccount->id));
        $this->assertFalse($activeIds->contains($archived->id));

        $this->assertTrue($archived->fresh()->archived);
        $this->assertFalse($active->fresh()->archived);
        $this->assertFalse($noAccount->fresh()->archived);
    }

    public function test_deactivated_staff_drop_off_the_dashboard_and_balances(): void
    {
        $admin = $this->admin();
        $this->staff('5001', 'Ana');
        $archived = $this->staff('5002', 'Ben');

        // Deactivate Ben's login.
        $this->actingAs($admin)->patch(route('admin.users.toggle', $archived->user))
            ->assertRedirect();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn ($p) => $p
                ->where('rows', fn ($rows) => collect($rows)->pluck('emp_no')->doesntContain('5002')));

        $this->actingAs($admin)->get(route('admin.balances.index'))
            ->assertInertia(fn ($p) => $p
                ->where('rows', fn ($rows) => collect($rows)->pluck('emp_no')->doesntContain('5002')));
    }

    public function test_the_employees_list_hides_archived_until_toggled(): void
    {
        $admin = $this->admin();
        $this->staff('5001', 'Ana');
        $archived = $this->staff('5002', 'Ben');
        $this->actingAs($admin)->patch(route('admin.users.toggle', $archived->user));

        // Default: archived hidden.
        $this->actingAs($admin)->get(route('admin.employees.index'))
            ->assertInertia(fn ($p) => $p
                ->where('archivedCount', 1)
                ->where('employees', fn ($e) => collect($e)->pluck('name')->doesntContain('Dela Cruz, Ben')));

        // With ?archived=1: both show, Ben flagged.
        $this->actingAs($admin)->get(route('admin.employees.index', ['archived' => 1]))
            ->assertInertia(fn ($p) => $p
                ->where('showArchived', true)
                ->where('employees', fn ($e) => collect($e)->firstWhere('name', 'Dela Cruz, Ben')['archived'] === true));
    }

    public function test_reactivating_brings_the_employee_back(): void
    {
        $admin = $this->admin();
        $e = $this->staff('5002', 'Ben', active: false);

        $this->assertFalse(Employee::active()->pluck('id')->contains($e->id));

        $this->actingAs($admin)->patch(route('admin.users.toggle', $e->user));

        $this->assertTrue(Employee::active()->pluck('id')->contains($e->id));
    }
}
