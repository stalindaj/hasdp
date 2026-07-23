<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'employee_id' => Employee::create([
                'emp_no' => '5112', 'first_name' => 'Marie Cris', 'last_name' => 'Uri',
            ])->id,
        ]);
        $user->roles()->sync(Role::whereIn('name', ['admin'])->pluck('id'));

        return $user->fresh();
    }

    private function employee(string $empNo = '5807'): User
    {
        $user = User::factory()->create([
            'username' => $empNo,
            'employee_id' => Employee::create([
                'emp_no' => $empNo, 'first_name' => 'Justin', 'last_name' => 'Bercades',
            ])->id,
        ]);
        $user->roles()->sync(Role::where('name', 'employee')->pluck('id'));

        return $user->fresh();
    }

    public function test_an_admin_sees_the_all_employee_dashboard(): void
    {
        $admin = $this->admin();
        $this->employee();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('mode', 'admin')
                ->has('rows', 2)          // Marie + Justin
                ->has('boxes.ipcr')
                ->has('pendingLeaves'));
    }

    public function test_an_employee_sees_only_their_own_dashboard(): void
    {
        $this->actingAs($this->employee())->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('mode', 'employee')
                ->has('me'));
    }

    public function test_the_view_switch_flips_an_admin_to_the_employee_experience(): void
    {
        $admin = $this->admin();

        // Switch to employee view → dashboard becomes personal, admin area 403s.
        $this->actingAs($admin)->post(route('view-mode.toggle'))->assertRedirect();
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('mode', 'employee'));
        $this->actingAs($admin)->get(route('admin.users.index'))->assertForbidden();

        // Switch back → admin dashboard + access restored.
        $this->actingAs($admin)->post(route('view-mode.toggle'))->assertRedirect();
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('mode', 'admin'));
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_an_admin_can_toggle_ipcr_semesters(): void
    {
        $admin = $this->admin();
        $emp = Employee::create(['emp_no' => '5803', 'first_name' => 'Raynold', 'last_name' => 'Calagos']);

        $this->actingAs($admin)->patch(route('dashboard.ipcr', $emp), ['sem' => 1])->assertRedirect();
        $this->assertTrue($emp->ipcrRecords()->where('year', now()->year)->first()->sem1_done);

        // Toggling again unticks it.
        $this->actingAs($admin)->patch(route('dashboard.ipcr', $emp), ['sem' => 1]);
        $this->assertFalse($emp->ipcrRecords()->where('year', now()->year)->first()->sem1_done);
    }

    public function test_an_admin_can_log_ld_hours(): void
    {
        $admin = $this->admin();
        $emp = Employee::create(['emp_no' => '5797', 'first_name' => 'Cyric', 'last_name' => 'Bulanan']);

        $this->actingAs($admin)->post(route('dashboard.ld', $emp), [
            'title' => 'Records Management Seminar',
            'hours' => 8,
            'date'  => now()->toDateString(),
        ])->assertRedirect();

        $this->assertEquals(8.0, (float) $emp->ldEntries()->sum('hours'));
    }

    public function test_a_regular_employee_cannot_edit_ipcr_or_ld(): void
    {
        $employee = $this->employee();
        $emp = Employee::create(['emp_no' => '5764', 'first_name' => 'Meldith', 'last_name' => 'De La Peña']);

        $this->actingAs($employee)->patch(route('dashboard.ipcr', $emp), ['sem' => 1])->assertForbidden();
        $this->actingAs($employee)->post(route('dashboard.ld', $emp), [
            'title' => 'x', 'hours' => 1, 'date' => now()->toDateString(),
        ])->assertForbidden();
    }
}
