<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Support\LeaveWorkflow;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The applicant self-serves the whole CS Form 6 — boxes 1–6, all three
 * signature blocks, the 7.A credits and the 7.C/7.D decision — at every stage
 * except cancellation, so a mistake can always be corrected. An admin may step
 * in on the same fields, including on an already-approved leave.
 */
class ApplicantEditsLeaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
        config(['agency.branch_suffix' => 'PAF']);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['employee_id' => Employee::create([
            'emp_no' => '5112', 'first_name' => 'Marie Cris', 'last_name' => 'Uri',
        ])->id]);
        $u->roles()->sync(Role::whereIn('name', ['admin', 'hr_officer'])->pluck('id'));

        return $u->fresh();
    }

    private function applicant(): User
    {
        $u = User::factory()->create(['employee_id' => Employee::create([
            'emp_no' => '5807', 'first_name' => 'Justin', 'last_name' => 'Bercades',
            'credits_accrual_start' => '2026-01-01',
        ])->id]);
        $u->roles()->sync(Role::where('name', 'employee')->pluck('id'));

        return $u->fresh();
    }

    private function file(User $applicant): LeaveApplication
    {
        $this->actingAs($applicant)->post(route('leave.store'), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => '2026-07-03',
            'position' => 'Clerk', 'detail_vacation' => 'within_philippines',
            'date_from' => '2026-07-20', 'date_to' => '2026-07-22',
            'commutation' => 'not_requested',
        ])->assertRedirect();

        return LeaveApplication::firstOrFail();
    }

    public function test_the_applicant_can_revise_a_pending_application(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);
        $this->assertEquals(3.0, (float) $leave->working_days);

        $this->actingAs($applicant)->patch(route('leave.update', $leave), [
            'leave_type_id' => LeaveType::where('code', 'sick')->value('id'),
            'office_department' => 'Directorate for Personnel',
            'applicant_last_name' => 'Bercades', 'applicant_first_name' => 'Justin',
            'applicant_middle_name' => 'L',
            'date_filing' => '2026-07-03', 'position' => 'Admin Aide IV',
            'detail_sick' => 'out_patient', 'detail_sick_illness' => 'Influenza',
            'date_from' => '2026-07-20', 'date_to' => '2026-07-24',
            'commutation' => 'requested',
        ])->assertRedirect();

        $leave->refresh();
        $this->assertSame('Sick Leave', $leave->leaveType->name);
        $this->assertSame('out_patient', $leave->detail_sick);
        $this->assertSame('requested', $leave->commutation);
        // 6.C recomputes from the new dates…
        $this->assertEquals(5.0, (float) $leave->working_days);
        // …every 6.B block stays under the applicant's own hand: the vacation
        // block it was filed with is left alone unless they clear it…
        $this->assertSame('within_philippines', $leave->detail_vacation);
        // …and 6.D follows the corrected name.
        $this->assertSame('JUSTIN L BERCADES', $leave->applicant_sig['name']);
        // Still pending, and the edit is on the audit trail.
        $this->assertSame(LeaveWorkflow::PENDING, $leave->status);
        $this->assertNotNull($leave->actions()->where('action', 'updated')->first());
    }

    public function test_editing_still_skips_weekends_and_holidays(): void
    {
        $this->seed(HolidaySeeder::class);
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        // Thu Aug 20 – Mon Aug 24 2026: Fri 21 is Ninoy Aquino Day, 22–23 weekend.
        $this->actingAs($applicant)->patch(route('leave.update', $leave), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => '2026-07-03',
            'position' => 'Clerk', 'detail_vacation' => 'within_philippines',
            'date_from' => '2026-08-20', 'date_to' => '2026-08-24',
            'commutation' => 'not_requested',
        ])->assertRedirect();

        $this->assertEquals(2.0, (float) $leave->fresh()->working_days);
    }

    public function test_the_applicant_names_their_own_7b(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($applicant)->patch(route('leave.signatory', $leave), [
            'slot' => 'recommender', 'type' => 'military',
            'name' => 'Juan P Dela Cruz', 'rank' => 'MAJ', 'office' => 'Chief, MPMBR',
        ])->assertRedirect();

        $sig = $leave->fresh()->recommender_sig;
        $this->assertSame('JUAN P DELA CRUZ', $sig['name']);
        $this->assertSame('MAJ', $sig['rank']);
        $this->assertSame('PAF', $sig['branch']);
    }

    public function test_the_applicant_may_type_7a_and_7cd_themselves(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        // The applicant self-serves the whole form, so all three signature
        // blocks are theirs to type.
        foreach (['certifier' => 'hr_officer_sig', 'approver' => 'approver_sig'] as $slot => $column) {
            $this->actingAs($applicant)->patch(route('leave.signatory', $leave), [
                'slot' => $slot, 'type' => 'civilian', 'name' => "Self {$slot}",
            ])->assertRedirect();

            $this->assertSame(strtoupper("Self {$slot}"), $leave->fresh()->{$column}['name']);
        }
    }

    public function test_nobody_else_can_edit_someone_elses_application(): void
    {
        $leave = $this->file($this->applicant());
        $other = $this->applicant2();

        $this->actingAs($other)->patch(route('leave.update', $leave), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'X', 'applicant_last_name' => 'X',
            'applicant_first_name' => 'X', 'date_filing' => '2026-07-03',
            'position' => 'X', 'detail_vacation' => 'within_philippines',
            'date_from' => '2026-07-20', 'date_to' => '2026-07-22',
            'commutation' => 'not_requested',
        ])->assertForbidden();
    }

    private function applicant2(): User
    {
        $u = User::factory()->create(['employee_id' => Employee::create([
            'emp_no' => '5999', 'first_name' => 'Other', 'last_name' => 'Person',
        ])->id]);
        $u->roles()->sync(Role::where('name', 'employee')->pluck('id'));

        return $u->fresh();
    }

    public function test_editing_stays_open_after_a_decision(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($this->admin())->post(route('leave.decide', $leave), [
            'decision' => 'approved', 'days_with_pay' => 3,
        ])->assertRedirect();

        $payload = [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => '2026-07-03',
            'position' => 'Clerk', 'detail_vacation' => 'within_philippines',
            'date_from' => '2026-07-20', 'date_to' => '2026-07-24',
            'commutation' => 'not_requested',
        ];

        // A mistake can still be corrected on an already-approved leave.
        $this->actingAs($applicant)->patch(route('leave.update', $leave->fresh()), $payload)
            ->assertRedirect();
        $this->assertEquals(5.0, (float) $leave->fresh()->working_days);

        // …and the signatories remain editable too.
        $this->actingAs($applicant)->patch(route('leave.signatory', $leave->fresh()), [
            'slot' => 'recommender', 'type' => 'civilian', 'name' => 'Still Editable',
        ])->assertRedirect();
        $this->assertSame('STILL EDITABLE', $leave->fresh()->recommender_sig['name']);
    }

    public function test_correcting_an_approved_leaves_type_moves_the_credit_deduction(): void
    {
        $applicant = $this->applicant();
        $employee = $applicant->employee;
        $admin = $this->admin();

        $leave = $this->file($applicant);   // vacation, 3 working days
        $this->actingAs($admin)->post(route('leave.decide', $leave), [
            'decision' => 'approved', 'days_with_pay' => 3,
        ])->assertRedirect();

        // Approved vacation deducts 3 from VL; SL is untouched.
        $approved = \App\Support\CreditLedger::balances($employee->fresh());

        // The applicant corrects the type to Sick Leave after approval.
        $this->actingAs($applicant)->patch(route('leave.update', $leave->fresh()), [
            'leave_type_id' => LeaveType::where('code', 'sick')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => '2026-07-03',
            'position' => 'Clerk', 'detail_sick' => 'out_patient',
            'date_from' => '2026-07-20', 'date_to' => '2026-07-22',
            'commutation' => 'not_requested',
        ])->assertRedirect();

        // The 3-day deduction moves off VL (refunded) and onto SL.
        $corrected = \App\Support\CreditLedger::balances($employee->fresh());
        $this->assertEquals(3.0, round($corrected['vl'] - $approved['vl'], 2));   // VL back up 3
        $this->assertEquals(-3.0, round($corrected['sl'] - $approved['sl'], 2));  // SL down 3
    }

    public function test_editing_locks_only_once_the_leave_is_cancelled(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($applicant)->post(route('leave.cancel', $leave))->assertRedirect();

        $this->actingAs($applicant)->patch(route('leave.signatory', $leave->fresh()), [
            'slot' => 'recommender', 'type' => 'civilian', 'name' => 'Too Late',
        ])->assertForbidden();

        $this->actingAs($applicant)->patch(route('leave.update', $leave->fresh()), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => '2026-07-03',
            'position' => 'Clerk', 'detail_vacation' => 'within_philippines',
            'date_from' => '2026-07-20', 'date_to' => '2026-07-22',
            'commutation' => 'not_requested',
        ])->assertForbidden();
    }

    public function test_the_show_page_gives_the_applicant_the_edit_form(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($applicant)->get(route('leave.show', $leave))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('can.edit', true)
                ->has('leaveTypes')
                ->has('form')
                ->where('form.commutation', 'not_requested'));

        // An admin may also edit, to finalise rather than bounce it back.
        $this->actingAs($this->admin())->get(route('leave.show', $leave))
            ->assertInertia(fn ($p) => $p
                ->where('can.edit', true)
                ->where('can.process', true));
    }

    public function test_an_admin_can_correct_the_form_while_finalising(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($this->admin())->patch(route('leave.update', $leave), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'Directorate for Personnel',
            'applicant_last_name' => 'Bercades', 'applicant_first_name' => 'Justin',
            'date_filing' => '2026-07-03', 'position' => 'Admin Aide IV',
            'detail_vacation' => 'within_philippines',
            'date_from' => '2026-07-20', 'date_to' => '2026-07-21',
            'commutation' => 'not_requested',
        ])->assertRedirect();

        $this->assertEquals(2.0, (float) $leave->fresh()->working_days);
    }

    public function test_the_applicant_sees_the_signatories_and_their_balances(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($this->admin())->patch(route('leave.signatory', $leave), [
            'slot' => 'recommender', 'type' => 'military',
            'name' => 'Juan P Dela Cruz', 'rank' => 'MAJ', 'office' => 'Chief, MPMBR',
        ]);

        $this->actingAs($applicant)->get(route('leave.show', $leave))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                // The employee sees the same signatory panel the admin does…
                ->where('signatories.recommender.label', 'MAJ JUAN P DELA CRUZ PAF')
                ->has('signatories.certifier')
                ->has('signatories.approver')
                // …and their own credit balances.
                ->has('balanceCheck.balances')
                // …and can process the leave themselves.
                ->where('can.process', true));
    }

    public function test_signatory_changes_land_on_the_audit_trail(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($applicant)->patch(route('leave.signatory', $leave), [
            'slot' => 'recommender', 'type' => 'civilian',
            'name' => 'Dianne R Relato', 'position' => 'Admin Officer V',
            'office' => 'Supply Accountable Officer',
        ])->assertRedirect();

        $action = $leave->actions()->where('action', 'set 7.B')->first();
        $this->assertNotNull($action);
        $this->assertSame($applicant->id, $action->user_id);
        $this->assertStringContainsString('DIANNE R RELATO', $action->remarks);

        // The superadmin audit page picks it up.
        $super = User::factory()->create();
        $super->roles()->sync(Role::where('name', 'superadmin')->pluck('id'));

        $this->actingAs($super)->get(route('admin.audit.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('events', fn ($events) => collect($events)
                    ->pluck('action')->contains('set 7.B')));
    }
}
