<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin and employee are two separate hats, never worn at once. An admin
 * processes other people's leave and cannot file their own; to file, they
 * switch to employee mode, which strips their admin powers for as long as it
 * is on.
 */
class AdminEmployeeSeparationTest extends TestCase
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
        $u = User::factory()->create(['employee_id' => Employee::create([
            'emp_no' => '5112', 'first_name' => 'Marie Cris', 'last_name' => 'Uri',
        ])->id]);
        $u->roles()->sync(Role::whereIn('name', ['admin', 'hr_officer'])->pluck('id'));

        return $u->fresh();
    }

    private function employee(): User
    {
        $u = User::factory()->create(['employee_id' => Employee::create([
            'emp_no' => '5807', 'first_name' => 'Justin', 'last_name' => 'Bercades',
        ])->id]);
        $u->roles()->sync(Role::where('name', 'employee')->pluck('id'));

        return $u->fresh();
    }

    private function payload(): array
    {
        return [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Uri',
            'applicant_first_name' => 'Marie', 'date_filing' => '2026-07-03',
            'position' => 'HRMO', 'detail_vacation' => 'within_philippines',
            'date_from' => '2026-07-20', 'date_to' => '2026-07-22',
            'commutation' => 'not_requested',
        ];
    }

    public function test_an_admin_cannot_reach_the_filing_screens(): void
    {
        $admin = $this->admin();

        // The create form and the store action both bounce to the dashboard.
        $this->actingAs($admin)->get(route('leave.create'))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($admin)->post(route('leave.store'), $this->payload())
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_an_admin_cannot_reach_the_employee_self_service_profile(): void
    {
        $admin = $this->admin();

        // My Profile is employee self-service; an admin is bounced until they
        // switch hats.
        $this->actingAs($admin)->get(route('my-profile.edit'))
            ->assertRedirect(route('dashboard'));

        // In employee mode the same account reaches it.
        $this->actingAs($admin)->post(route('view-mode.toggle'));
        $this->actingAs($admin)->get(route('my-profile.edit'))->assertOk();
    }

    public function test_leave_index_sends_an_admin_to_the_requests_queue(): void
    {
        $this->actingAs($this->admin())->get(route('leave.index'))
            ->assertRedirect(route('leave.requests'));
    }

    public function test_switching_to_employee_mode_lets_an_admin_file(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('view-mode.toggle'))->assertRedirect();

        // In employee mode the admin can now file for themselves…
        $this->actingAs($admin)->get(route('leave.create'))->assertOk();
        $this->actingAs($admin)->post(route('leave.store'), $this->payload())->assertRedirect();
        $this->assertSame(1, LeaveApplication::count());

        // …and has no admin access while the hat is on.
        $this->actingAs($admin)->get(route('leave.requests'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_an_admin_cannot_decide_their_own_filed_leave(): void
    {
        $admin = $this->admin();

        // File it as an employee…
        $this->actingAs($admin)->post(route('view-mode.toggle'));
        $this->actingAs($admin)->post(route('leave.store'), $this->payload());
        $leave = LeaveApplication::firstOrFail();

        // …still in employee mode, deciding is refused.
        $this->actingAs($admin)->post(route('leave.decide', $leave), [
            'decision' => 'approved', 'days_with_pay' => 3,
        ])->assertForbidden();

        // Switch back to admin, and a *different* admin-capable session decides.
        // (Here the same person switches hats — the app allows it, but the two
        //  acts are cleanly separated by mode.)
        $this->actingAs($admin)->post(route('view-mode.toggle'));
        $this->actingAs($admin)->post(route('leave.decide', $leave->fresh()), [
            'decision' => 'approved', 'days_with_pay' => 3,
        ])->assertRedirect();

        $this->assertSame('approved', $leave->fresh()->status);
    }
}
