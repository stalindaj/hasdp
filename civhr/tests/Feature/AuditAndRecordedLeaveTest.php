<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\LeaveWorkflow;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-recorded leaves (paper / pre-go-live) and the superadmin audit trail.
 */
class AuditAndRecordedLeaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
    }

    private function userWithRoles(array $roles, ?array $employee = null): User
    {
        $user = User::factory()->create(
            $employee ? ['employee_id' => Employee::create($employee)->id] : []
        );
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user->fresh();
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= $this->userWithRoles(['admin'], [
            'emp_no' => '5112', 'first_name' => 'Marie Cris', 'last_name' => 'Uri',
        ]);
    }

    private function staff(): Employee
    {
        return $this->userWithRoles(['employee'], [
            'emp_no' => '5111', 'first_name' => 'Justin', 'last_name' => 'Bercades',
            'credits_accrual_start' => now()->startOfMonth(),
        ])->employee;
    }

    // ── Recording a leave already taken ───────────────────────────────

    public function test_an_admin_records_a_past_leave_and_it_deducts_credits(): void
    {
        $employee = $this->staff();
        $admin = $this->admin();
        CreditLedger::adjust($employee, 'vl', 10, 'opening', $admin->id);

        $this->actingAs($admin)->post(route('dashboard.record-leave', $employee), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'date_from'     => '2026-03-09',   // Mon
            'date_to'       => '2026-03-11',   // Wed
            'working_days'  => 3,
            'remarks'       => 'Paper CS Form 6 dated 05 Mar 2026',
        ])->assertRedirect();

        $leave = LeaveApplication::firstOrFail();
        $this->assertSame(LeaveWorkflow::APPROVED, $leave->status);
        $this->assertSame($admin->id, $leave->recorded_by);
        $this->assertEquals(3.0, (float) $leave->days_with_pay);
        $this->assertSame('Bercades Justin', trim($leave->applicant_name));

        // 1.25 accrued + 10 opening − 3 taken.
        $this->assertEquals(8.25, CreditLedger::balances($employee->fresh())['vl']);

        // …and it is on the trail, with the admin's remark.
        $action = $leave->actions()->latest('id')->first();
        $this->assertSame('recorded by admin', $action->action);
        $this->assertSame('Paper CS Form 6 dated 05 Mar 2026', $action->remarks);
    }

    public function test_a_recorded_wellness_leave_reduces_the_annual_entitlement(): void
    {
        $employee = $this->staff();

        $this->actingAs($this->admin())->post(route('dashboard.record-leave', $employee), [
            'leave_type_id' => LeaveType::where('code', 'wellness')->value('id'),
            'date_from' => now()->startOfYear()->addDays(10)->toDateString(),
            'date_to'   => now()->startOfYear()->addDays(11)->toDateString(),
            'working_days' => 2,
        ])->assertRedirect();

        $this->assertEquals(3.0, CreditLedger::balances($employee->fresh())['wellness']);
    }

    public function test_recording_validates_the_dates_and_days(): void
    {
        $employee = $this->staff();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('dashboard.record-leave', $employee), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'date_from' => '2026-03-11', 'date_to' => '2026-03-09',  // backwards
            'working_days' => 3,
        ])->assertSessionHasErrors('date_to');

        $this->actingAs($admin)->post(route('dashboard.record-leave', $employee), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'date_from' => '2026-03-09', 'date_to' => '2026-03-11',
            'working_days' => 0,   // must be at least half a day
        ])->assertSessionHasErrors('working_days');

        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_a_regular_employee_cannot_record_leaves(): void
    {
        $employee = $this->staff();

        $this->actingAs($this->userWithRoles(['employee']))
            ->post(route('dashboard.record-leave', $employee), [
                'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
                'date_from' => '2026-03-09', 'date_to' => '2026-03-09', 'working_days' => 1,
            ])->assertForbidden();
    }

    // ── The audit trail ───────────────────────────────────────────────

    public function test_the_audit_trail_is_superadmin_only(): void
    {
        // A plain admin appears in the trail, so cannot read it.
        $this->actingAs($this->admin())->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.audit.export'))->assertForbidden();

        $this->actingAs($this->userWithRoles(['employee']))
            ->get(route('admin.audit.index'))->assertForbidden();

        $this->actingAs($this->userWithRoles(['superadmin']))
            ->get(route('admin.audit.index'))->assertOk();
    }

    public function test_the_trail_collects_leave_ld_and_credit_events(): void
    {
        $this->seed(HolidaySeeder::class);
        $employee = $this->staff();
        $admin = $this->admin();
        $super = $this->userWithRoles(['superadmin']);

        // A recorded leave and a manual credit adjustment.
        $this->actingAs($admin)->post(route('dashboard.record-leave', $employee), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'date_from' => '2026-03-09', 'date_to' => '2026-03-09', 'working_days' => 1,
            'remarks' => 'Paper form',
        ]);
        $this->actingAs($admin)->post(route('dashboard.credit', $employee), [
            'kind' => 'sl', 'amount' => 5, 'note' => 'Opening balance per 201 file',
        ]);

        $this->actingAs($super)->get(route('admin.audit.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Audit/Index')
                ->where('counts.leave', 1)
                ->where('counts.credit', 1)
                ->has('events', 2)
                // Newest first, and the actor is named.
                ->where('events.0.by', $admin->name));
    }

    public function test_the_csv_export_streams_the_trail(): void
    {
        $employee = $this->staff();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('dashboard.credit', $employee), [
            'kind' => 'vl', 'amount' => 2.5, 'note' => 'Correction',
        ]);

        $response = $this->actingAs($this->userWithRoles(['superadmin']))
            ->get(route('admin.audit.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Timestamp,Area,Action,By,Subject,Details', $csv);
        $this->assertStringContainsString('Credits', $csv);
        $this->assertStringContainsString('Correction', $csv);
        $this->assertStringContainsString($admin->name, $csv);
    }
}
