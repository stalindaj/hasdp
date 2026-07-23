<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\LeaveWorkflow;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
    }

    private function employee(array $attrs = []): Employee
    {
        return Employee::create(array_merge([
            'emp_no' => '5807', 'first_name' => 'Justin', 'last_name' => 'Bercades',
        ], $attrs));
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync(Role::where('name', 'admin')->pluck('id'));

        return $u->fresh();
    }

    public function test_vl_and_sl_accrue_1_25_per_month_and_catch_up(): void
    {
        // Accrual began four months ago → 5 monthly postings inclusive.
        $e = $this->employee(['credits_accrual_start' => now()->subMonths(4)->startOfMonth()]);

        $balances = CreditLedger::balances($e);

        $this->assertEquals(6.25, $balances['vl']);   // 5 × 1.25
        $this->assertEquals(6.25, $balances['sl']);

        // Reading again must not double-post.
        $this->assertEquals(6.25, CreditLedger::balances($e)['vl']);
    }

    public function test_wellness_and_spl_are_annual_entitlements(): void
    {
        $e = $this->employee();
        $balances = CreditLedger::balances($e);

        $this->assertEquals(5.0, $balances['wellness']);
        $this->assertEquals(3.0, $balances['spl']);
    }

    public function test_an_admin_adjustment_changes_the_balance_and_is_recorded(): void
    {
        $e = $this->employee(['credits_accrual_start' => now()->startOfMonth()]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('dashboard.credit', $e), [
            'kind' => 'vl', 'amount' => 10.5, 'note' => 'Opening balance per 201 file',
        ])->assertRedirect();

        // 1.25 (this month's accrual) + 10.5 adjustment.
        $this->assertEquals(11.75, CreditLedger::balances($e)['vl']);
        $this->assertStringContainsString(
            'Opening balance',
            $e->creditEntries()->whereNull('period')->first()->note
        );
    }

    public function test_an_approved_vacation_leave_deducts_from_vl(): void
    {
        $e = $this->employee(['credits_accrual_start' => now()->startOfMonth()]);
        $applicant = User::factory()->create(['employee_id' => $e->id]);
        $applicant->roles()->sync(Role::where('name', 'employee')->pluck('id'));
        $admin = $this->admin();

        $this->actingAs($applicant)->post(route('leave.store'), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => now()->toDateString(),
            'position' => 'Clerk', 'detail_vacation' => 'within_philippines',
            'working_days' => 2, 'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDays(2)->toDateString(), 'commutation' => 'not_requested',
        ])->assertRedirect();

        $leave = LeaveApplication::firstOrFail();
        $this->actingAs($admin)->post(route('leave.decide', $leave), [
            'decision' => 'approved', 'days_with_pay' => 2,
        ]);

        // 1.25 accrued − 2 approved.
        $this->assertEquals(-0.75, CreditLedger::balances($e)['vl']);

        // Re-deciding with different days replaces (not stacks) the deduction.
        $this->actingAs($admin)->post(route('leave.decide', $leave->fresh()), [
            'decision' => 'approved', 'days_with_pay' => 1,
        ]);
        $this->assertEquals(0.25, CreditLedger::balances($e)['vl']);

        // Disapproving refunds it entirely.
        $this->actingAs($admin)->post(route('leave.decide', $leave->fresh()), [
            'decision' => 'disapproved', 'disapproval_reason' => 'exigency',
        ]);
        $this->assertEquals(1.25, CreditLedger::balances($e)['vl']);
    }

    public function test_an_approved_wellness_leave_reduces_the_annual_entitlement(): void
    {
        $e = $this->employee(['credits_accrual_start' => now()->startOfMonth()]);
        $applicant = User::factory()->create(['employee_id' => $e->id]);
        $applicant->roles()->sync(Role::where('name', 'employee')->pluck('id'));
        $admin = $this->admin();

        $this->actingAs($applicant)->post(route('leave.store'), [
            'leave_type_id' => LeaveType::where('code', 'wellness')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => now()->toDateString(),
            'position' => 'Clerk',
            'working_days' => 2, 'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDays(2)->toDateString(), 'commutation' => 'not_requested',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('leave.decide', LeaveApplication::firstOrFail()), [
            'decision' => 'approved', 'days_with_pay' => 2,
        ]);

        $balances = CreditLedger::balances($e);
        $this->assertEquals(3.0, $balances['wellness']);   // 5 − 2
        $this->assertEquals(1.25, $balances['vl']);        // VL untouched
    }

    public function test_forced_leave_usage_is_tracked_and_vl_counts_toward_it(): void
    {
        $e = $this->employee(['credits_accrual_start' => now()->startOfMonth()]);
        $applicant = User::factory()->create(['employee_id' => $e->id]);
        $applicant->roles()->sync(Role::where('name', 'employee')->pluck('id'));
        $admin = $this->admin();

        // Give enough VL to cover the leave, then approve a 2-day vacation.
        CreditLedger::adjust($e, 'vl', 10, 'opening', $admin->id);

        $this->actingAs($applicant)->post(route('leave.store'), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => now()->toDateString(),
            'position' => 'Clerk', 'detail_vacation' => 'within_philippines',
            'working_days' => 2, 'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDays(2)->toDateString(), 'commutation' => 'not_requested',
        ]);
        $this->actingAs($admin)->post(route('leave.decide', LeaveApplication::firstOrFail()), [
            'decision' => 'approved', 'days_with_pay' => 2,
        ]);

        // Any VL availment counts toward the 5-day mandatory/forced leave.
        $forced = CreditLedger::forcedLeaveStatus($e->fresh());
        $this->assertEquals(2.0, $forced['used']);
        $this->assertEquals(3.0, $forced['remaining']);

        // …and the employee card exposes it to the admin.
        $this->actingAs($admin)->get(route('dashboard.employee', $e))
            ->assertInertia(fn ($page) => $page
                ->where('forced.used', fn ($v) => (float) $v === 2.0)
                ->where('forced.remaining', fn ($v) => (float) $v === 3.0));
    }

    public function test_the_approval_screen_checks_the_balance_and_splits_pay(): void
    {
        // Only 1.25 VL accrued, but 5 days applied → 1.25 with pay / 3.75 without.
        $e = $this->employee(['credits_accrual_start' => now()->startOfMonth()]);
        $applicant = User::factory()->create(['employee_id' => $e->id]);
        $applicant->roles()->sync(Role::where('name', 'employee')->pluck('id'));
        $admin = $this->admin();

        $this->actingAs($applicant)->post(route('leave.store'), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => now()->toDateString(),
            'position' => 'Clerk', 'detail_vacation' => 'within_philippines',
            'working_days' => 5, 'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDays(5)->toDateString(), 'commutation' => 'not_requested',
        ]);

        $leave = LeaveApplication::firstOrFail();

        $this->actingAs($admin)->get(route('leave.show', $leave))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balanceCheck.kind', 'VL')
                ->where('balanceCheck.sufficient', false)
                ->where('balanceCheck.with_pay', fn ($v) => (float) $v === 1.25)
                ->where('balanceCheck.without_pay', fn ($v) => (float) $v === 3.75)
                ->where('balanceCheck.balances.wellness', fn ($v) => (float) $v === 5.0));
    }

    public function test_the_employee_card_shows_balances_and_is_admin_only(): void
    {
        $e = $this->employee(['credits_accrual_start' => now()->startOfMonth()]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('dashboard.employee', $e))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Employee')
                ->where('balances.vl', fn ($v) => (float) $v === 1.25)
                ->has('ledger'));

        $employeeUser = User::factory()->create();
        $employeeUser->roles()->sync(Role::where('name', 'employee')->pluck('id'));
        $this->actingAs($employeeUser)->get(route('dashboard.employee', $e))->assertForbidden();
    }

    public function test_a_wellness_leave_prints_under_others_on_the_form(): void
    {
        config(['agency.branch_suffix' => 'PAF']);
        $marie = User::factory()->create([
            'employee_id' => Employee::create([
                'emp_no' => '5112', 'first_name' => 'Marie Cris', 'last_name' => 'Uri',
            ])->id,
        ]);
        $marie->roles()->sync(Role::whereIn('name', ['admin', 'hr_officer'])->pluck('id'));

        $e = $this->employee();
        $applicant = User::factory()->create(['employee_id' => $e->id]);
        $applicant->roles()->sync(Role::where('name', 'employee')->pluck('id'));

        $this->actingAs($applicant)->post(route('leave.store'), [
            'leave_type_id' => LeaveType::where('code', 'wellness')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => now()->toDateString(),
            'position' => 'Clerk',
            'working_days' => 1, 'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(), 'commutation' => 'not_requested',
        ]);

        $leave = LeaveApplication::firstOrFail();
        $this->actingAs($marie->fresh())->post(route('leave.decide', $leave), [
            'decision' => 'approved', 'days_with_pay' => 1,
        ]);

        $this->actingAs($marie->fresh())->get(route('leave.print', $leave->fresh()))
            ->assertOk()
            ->assertSee('Wellness Leave');   // printed on the Others line
    }
}
