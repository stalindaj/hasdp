<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Support\LeaveWorkflow;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTest extends TestCase
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

    /** Marie: the admin who processes leaves AND signs 7.A (HR officer). */
    private function marie(): User
    {
        return $this->userWithRoles(['admin', 'hr_officer'], [
            'first_name' => 'Marie Cris', 'middle_name' => 'A', 'last_name' => 'Uri',
            'rank' => null, 'position' => 'Admin Officer IV (HRMO II)',
            'designation' => 'Wing Civilian Supervisor',
            'date_orig_appt' => '2016-01-01',
        ]);
    }

    /** Mission: the fixed 7.C/7.D approving official (Director for Personnel). */
    private function mission(): User
    {
        return $this->userWithRoles(['approver'], [
            'first_name' => 'Adrian', 'middle_name' => 'Lee', 'last_name' => 'Mission',
            'rank' => 'LTC', 'is_civilian' => false, 'designation' => 'Director for Personnel',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        // Mon Jul 20 – Wed Jul 22, 2026: 3 working days, no holidays — 6.C is
        // computed from the dates, never posted.
        return array_merge([
            'leave_type_id'         => LeaveType::where('code', 'vacation')->value('id'),
            'office_department'     => 'Directorate for Personnel',
            'applicant_last_name'   => 'Montejo',
            'applicant_first_name'  => 'Philip RJ',
            'applicant_middle_name' => 'A',
            'date_filing'           => '2026-07-03',
            'position'              => 'Admin Aide IV',
            'salary'                => '04',
            'detail_vacation'       => 'within_philippines',
            'detail_vacation_location' => 'Baguio City',
            'date_from'             => '2026-07-20',
            'date_to'               => '2026-07-22',
            'commutation'           => 'not_requested',
        ], $overrides);
    }

    private function fileAsEmployee(): array
    {
        $applicant = $this->userWithRoles(['employee']);
        $this->actingAs($applicant)->post(route('leave.store'), $this->payload())->assertRedirect();

        return [$applicant, LeaveApplication::firstOrFail()];
    }

    // ── Pages render ──────────────────────────────────────────────────

    public function test_leave_pages_render(): void
    {
        $employee = $this->userWithRoles(['employee']);
        $this->actingAs($employee)->get(route('leave.index'))->assertOk();
        $this->actingAs($employee)->get(route('leave.create'))->assertOk();

        // The admin queue is admin-only.
        $this->actingAs($employee)->get(route('leave.requests'))->assertForbidden();
        $this->actingAs($this->marie())->get(route('leave.requests'))->assertOk();
    }

    public function test_my_profile_renders_without_an_employee_record(): void
    {
        $user = $this->userWithRoles(['employee']);
        $this->actingAs($user)->get(route('my-profile.edit'))->assertOk();
    }

    // ── Filing ────────────────────────────────────────────────────────

    public function test_an_employee_files_only_the_application_and_it_waits_as_pending(): void
    {
        // The fixed 7.A / 7.C-D officers auto-fill from their roles at filing.
        $this->marie();
        $this->mission();

        [, $leave] = $this->fileAsEmployee();

        $this->assertSame(LeaveWorkflow::PENDING, $leave->status);
        $this->assertSame('MARIE CRIS A URI', $leave->hr_officer_sig['name']);
        $this->assertSame('ADRIAN LEE MISSION', $leave->approver_sig['name']);
        // Credits are only certified by the admin later.
        $this->assertNull($leave->vl_earned);
        // The applicant's own 6.D block is captured.
        $this->assertSame('PHILIP RJ A MONTEJO', $leave->applicant_sig['name']);
    }

    public function test_filing_without_a_leave_type_is_a_validation_error(): void
    {
        $applicant = $this->userWithRoles(['employee']);
        $payload = $this->payload();
        unset($payload['leave_type_id']);

        $this->actingAs($applicant)->post(route('leave.store'), $payload)
            ->assertSessionHasErrors('leave_type_id');
    }

    public function test_sick_leave_requires_its_detail_block(): void
    {
        $applicant = $this->userWithRoles(['employee']);

        $this->actingAs($applicant)->post(route('leave.store'), $this->payload([
            'leave_type_id'   => LeaveType::where('code', 'sick')->value('id'),
            'detail_vacation' => null,
        ]))->assertSessionHasErrors('detail_sick');
    }

    public function test_detail_fields_outside_the_chosen_type_are_not_stored(): void
    {
        $applicant = $this->userWithRoles(['employee']);

        $this->actingAs($applicant)->post(route('leave.store'), $this->payload([
            'leave_type_id'            => LeaveType::where('code', 'sick')->value('id'),
            'detail_sick'              => 'out_patient',
            'detail_sick_illness'      => 'Influenza',
            'detail_vacation'          => 'abroad',
            'detail_vacation_location' => 'Singapore',
        ]))->assertRedirect();

        $leave = LeaveApplication::firstOrFail();
        $this->assertSame('out_patient', $leave->detail_sick);
        $this->assertNull($leave->detail_vacation);
    }

    // ── 6.C is computed, weekends + holidays excluded ─────────────────

    public function test_6c_is_computed_from_the_inclusive_dates_and_ignores_the_client(): void
    {
        $applicant = $this->userWithRoles(['employee']);

        // Mon Jul 20 – Fri Jul 24, 2026 → 5 working days. A tampered
        // working_days in the request must not matter.
        $this->actingAs($applicant)->post(route('leave.store'), $this->payload([
            'date_from' => '2026-07-20', 'date_to' => '2026-07-24', 'working_days' => 99,
        ]))->assertRedirect();

        $this->assertEquals(5.0, (float) LeaveApplication::firstOrFail()->working_days);
    }

    public function test_6c_spanning_a_weekend_counts_only_the_weekdays(): void
    {
        $applicant = $this->userWithRoles(['employee']);

        // Fri Jul 17 – Mon Jul 20, 2026: Sat 18 + Sun 19 don't count.
        $this->actingAs($applicant)->post(route('leave.store'), $this->payload([
            'date_from' => '2026-07-17', 'date_to' => '2026-07-20',
        ]))->assertRedirect();

        $this->assertEquals(2.0, (float) LeaveApplication::firstOrFail()->working_days);
    }

    public function test_6c_skips_holidays(): void
    {
        $this->seed(\Database\Seeders\HolidaySeeder::class);
        $applicant = $this->userWithRoles(['employee']);

        // Thu Aug 20 – Mon Aug 24, 2026: Fri 21 is Ninoy Aquino Day and
        // 22–23 is the weekend, so only Thu + Mon count.
        $this->actingAs($applicant)->post(route('leave.store'), $this->payload([
            'date_from' => '2026-08-20', 'date_to' => '2026-08-24',
        ]))->assertRedirect();

        $this->assertEquals(2.0, (float) LeaveApplication::firstOrFail()->working_days);
    }

    public function test_a_range_with_no_working_days_is_rejected(): void
    {
        $applicant = $this->userWithRoles(['employee']);

        // Sat Jul 25 – Sun Jul 26, 2026 — nothing to apply for.
        $this->actingAs($applicant)->post(route('leave.store'), $this->payload([
            'date_from' => '2026-07-25', 'date_to' => '2026-07-26',
        ]))->assertSessionHasErrors('date_from');

        $this->assertSame(0, LeaveApplication::count());
    }

    public function test_maternity_counts_calendar_days(): void
    {
        $this->seed(\Database\Seeders\HolidaySeeder::class);
        $applicant = $this->userWithRoles(['employee']);

        // R.A. 11210: 105 CALENDAR days — weekends and holidays included.
        $this->actingAs($applicant)->post(route('leave.store'), $this->payload([
            'leave_type_id' => LeaveType::where('code', 'maternity')->value('id'),
            'date_from'     => '2026-08-01',
            'date_to'       => '2026-11-13',
        ]))->assertRedirect();

        $this->assertEquals(105.0, (float) LeaveApplication::firstOrFail()->working_days);
    }

    // ── Approval ──────────────────────────────────────────────────────

    public function test_approval_puts_marie_on_7a_mission_on_7c_and_keeps_the_typed_7b(): void
    {
        config(['agency.branch_suffix' => 'PAF']);

        $this->marie();
        $this->mission();

        [$applicant, $leave] = $this->fileAsEmployee();
        $admin = $this->marie();   // Marie (admin) processes

        // The admin types the 7.B recommending officer…
        $this->actingAs($admin)->patch(route('leave.signatory', $leave), [
            'slot' => 'recommender', 'type' => 'military',
            'name' => 'Julie Ann T Pedrosa', 'rank' => '1LT', 'office' => 'MPMBR',
        ])->assertRedirect();

        // …then approves; deciding must not wipe what was typed.
        $this->actingAs($admin)->post(route('leave.decide', $leave->fresh()), [
            'decision'       => 'approved',
            'cert_as_of'     => '2026-06-30',
            'vl_earned'      => 3.13,
            'vl_less'        => 3,
            'vl_balance'     => 0.13,
            'days_with_pay'  => 3,
        ])->assertRedirect();

        $leave->refresh();
        $this->assertSame(LeaveWorkflow::APPROVED, $leave->status);
        $this->assertEquals(3.0, (float) $leave->days_with_pay);

        // 7.A = Marie, 7.C/7.D = Mission, 7.B = as typed (rank left, PAF
        // right, office on the title line — the "rank office" layout).
        $this->assertSame('MARIE CRIS A URI', $leave->hr_officer_sig['name']);
        $this->assertSame('ADRIAN LEE MISSION', $leave->approver_sig['name']);
        $this->assertSame('Director for Personnel', $leave->approver_sig['designation']);
        $this->assertSame([
            'rank'        => '1LT',
            'name'        => 'JULIE ANN T PEDROSA',
            'branch'      => 'PAF',
            'position'    => '',
            'designation' => 'MPMBR',
        ], $leave->recommender_sig);
    }

    public function test_an_admin_can_change_the_recommender_after_approval(): void
    {
        $this->marie();
        $this->mission();

        [, $leave] = $this->fileAsEmployee();
        $admin = $this->marie();

        $this->actingAs($admin)->patch(route('leave.signatory', $leave), [
            'slot' => 'recommender', 'type' => 'military',
            'name' => 'Julie Ann T Pedrosa', 'rank' => '1LT',
        ]);
        $this->actingAs($admin)->post(route('leave.decide', $leave->fresh()), [
            'decision' => 'approved', 'days_with_pay' => 3,
        ]);
        $this->assertSame('JULIE ANN T PEDROSA', $leave->fresh()->recommender_sig['name']);

        // Switch 7.B on the already-approved leave.
        $this->actingAs($admin)->patch(route('leave.signatory', $leave->fresh()), [
            'slot' => 'recommender', 'type' => 'military',
            'name' => 'Carlo Reyes', 'rank' => 'CPT',
        ])->assertRedirect();

        $leave->refresh();
        $this->assertSame(LeaveWorkflow::APPROVED, $leave->status);
        $this->assertSame('CARLO REYES', $leave->recommender_sig['name']);
    }

    public function test_an_admin_can_save_credits_as_a_draft_without_deciding(): void
    {
        [, $leave] = $this->fileAsEmployee();
        $admin = $this->marie();

        $this->actingAs($admin)->patch(route('leave.save', $leave), [
            'cert_as_of' => '2026-06-30',
            'vl_earned'  => 12.5,
            'vl_less'    => 3,
            'vl_balance' => 9.5,
        ])->assertRedirect();

        $leave->refresh();
        // The figures are stored…
        $this->assertEquals(12.5, (float) $leave->vl_earned);
        // …but the leave is still pending (not yet approved).
        $this->assertSame(LeaveWorkflow::PENDING, $leave->status);
        $this->assertNull($leave->decision);
    }

    public function test_an_admin_can_set_the_recommender_on_its_own_endpoint(): void
    {
        $this->marie();
        $this->mission();

        [, $leave] = $this->fileAsEmployee();
        $admin = $this->marie();

        config(['agency.branch_suffix' => 'PAF']);
        $this->actingAs($admin)->patch(route('leave.signatory', $leave), [
            'slot' => 'recommender', 'type' => 'military',
            'name' => 'Julie Ann T Pedrosa', 'rank' => '1LT', 'office' => 'MPMBR',
        ])->assertRedirect();

        $this->assertSame('JULIE ANN T PEDROSA', $leave->fresh()->recommender_sig['name']);
        $this->assertSame('PAF', $leave->fresh()->recommender_sig['branch']);

        // …and a blank name clears 7.B again.
        $this->actingAs($admin)->patch(route('leave.signatory', $leave), [
            'slot' => 'recommender', 'type' => 'military', 'name' => '',
        ])->assertRedirect();
        $this->assertNull($leave->fresh()->recommender_sig);
    }

    public function test_a_civilian_signatory_prints_without_rank_or_branch(): void
    {
        config(['agency.branch_suffix' => 'PAF']);
        $this->marie();
        $this->mission();
        [, $leave] = $this->fileAsEmployee();

        // Marie herself recommending: civilian, two title lines, no branch.
        $this->actingAs($this->marie())->patch(route('leave.signatory', $leave), [
            'slot'     => 'recommender',
            'type'     => 'civilian',
            'name'     => 'Marie Cris A Uri',
            'rank'     => 'MAJ',   // ignored for a civilian
            'position' => 'Admin Officer IV (HRMO II)',
            'office'   => 'Wing Civilian Supervisor',
        ])->assertRedirect();

        $this->assertSame([
            'rank'        => '',
            'name'        => 'MARIE CRIS A URI',
            'branch'      => '',
            'position'    => 'Admin Officer IV (HRMO II)',
            'designation' => 'Wing Civilian Supervisor',
        ], $leave->fresh()->recommender_sig);
    }

    public function test_an_admin_can_stand_in_for_7a_and_7cd_and_the_decision_keeps_it(): void
    {
        config(['agency.branch_suffix' => 'PAF']);
        $this->marie();
        $this->mission();
        [, $leave] = $this->fileAsEmployee();
        $admin = $this->marie();

        // Someone else signs 7.C/7.D this time.
        $this->actingAs($admin)->patch(route('leave.signatory', $leave), [
            'slot' => 'approver', 'type' => 'military',
            'name' => 'Pedro D Santos', 'rank' => 'COL', 'office' => 'Acting Director for Personnel',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('leave.decide', $leave->fresh()), [
            'decision' => 'approved', 'days_with_pay' => 3,
        ])->assertRedirect();

        // Deciding must not restore the role holder over the stand-in.
        $leave->refresh();
        $this->assertSame('PEDRO D SANTOS', $leave->approver_sig['name']);
        $this->assertSame('COL', $leave->approver_sig['rank']);
        $this->assertSame('PAF', $leave->approver_sig['branch']);
    }

    public function test_a_non_admin_cannot_set_a_signatory(): void
    {
        [, $leave] = $this->fileAsEmployee();

        $this->actingAs($this->userWithRoles(['employee']))
            ->patch(route('leave.signatory', $leave), [
                'slot' => 'recommender', 'type' => 'military', 'name' => 'Julie Pedrosa',
            ])->assertForbidden();
    }

    public function test_office_department_is_editable_on_the_profile(): void
    {
        $user = $this->userWithRoles(['employee'], [
            'first_name' => 'Stalin', 'last_name' => 'Baguio',
        ]);

        $this->actingAs($user)->patch(route('my-profile.update'), [
            'office_department' => 'Directorate for Personnel',
        ])->assertRedirect();

        $this->assertSame('Directorate for Personnel', $user->employee->fresh()->office_department);
    }

    public function test_the_recommender_may_be_left_blank(): void
    {
        $this->marie();
        $this->mission();

        [, $leave] = $this->fileAsEmployee();

        $this->actingAs($this->marie())->post(route('leave.decide', $leave), [
            'decision' => 'approved', 'days_with_pay' => 3,   // 7.B never typed in
        ])->assertRedirect();

        $this->assertNull($leave->fresh()->recommender_sig);
    }

    public function test_disapproval_requires_a_reason(): void
    {
        [, $leave] = $this->fileAsEmployee();
        $marie = $this->marie();

        $this->actingAs($marie)->post(route('leave.decide', $leave), [
            'decision' => 'disapproved',
        ])->assertSessionHasErrors('disapproval_reason');

        $this->actingAs($marie)->post(route('leave.decide', $leave), [
            'decision'           => 'disapproved',
            'disapproval_reason' => 'Exigency of the service.',
            'days_with_pay'      => 3,
        ])->assertRedirect();

        $leave->refresh();
        $this->assertSame(LeaveWorkflow::DISAPPROVED, $leave->status);
        // 7.C stays empty when 7.D is used.
        $this->assertNull($leave->days_with_pay);
    }

    public function test_a_regular_employee_cannot_process_a_leave(): void
    {
        [, $leave] = $this->fileAsEmployee();
        $employee = $this->userWithRoles(['employee']);

        $this->actingAs($employee)->post(route('leave.decide', $leave), [
            'decision' => 'approved',
        ])->assertForbidden();
    }

    // ── Print gating ──────────────────────────────────────────────────

    public function test_the_form_prints_only_after_approval_for_the_employee(): void
    {
        $this->marie();
        $this->mission();
        [$applicant, $leave] = $this->fileAsEmployee();
        $marie = $this->marie();

        // Pending → employee cannot print yet.
        $this->actingAs($applicant)->get(route('leave.print', $leave))->assertForbidden();
        // …but an admin may preview.
        $this->actingAs($marie)->get(route('leave.print', $leave))->assertOk();

        $this->actingAs($marie)->post(route('leave.decide', $leave), [
            'decision' => 'approved', 'days_with_pay' => 3,
        ]);

        // Approved → employee can print; Marie on 7.A and Mission on 7.C/7.D.
        $this->actingAs($applicant->fresh())->get(route('leave.print', $leave->fresh()))
            ->assertOk()
            ->assertSee('APPLICATION FOR LEAVE')
            // Box 2 carries the name; 6.D is just the signature over the line.
            ->assertSee('Montejo')
            ->assertSee('MARIE CRIS A URI')
            ->assertSee('ADRIAN LEE MISSION')
            ->assertSee('PHILIPPINE AIR FORCE');
    }

    public function test_the_print_no_longer_includes_the_instructions_page(): void
    {
        [, $leave] = $this->fileAsEmployee();
        $marie = $this->marie();
        $this->actingAs($marie)->post(route('leave.decide', $leave), [
            'decision' => 'approved', 'days_with_pay' => 3,
        ]);

        $this->actingAs($marie)->get(route('leave.print', $leave->fresh()))
            ->assertOk()
            ->assertDontSee('INSTRUCTIONS AND REQUIREMENTS');
    }

    // ── Access + cancel ───────────────────────────────────────────────

    public function test_an_unrelated_user_cannot_view_someone_elses_application(): void
    {
        [, $leave] = $this->fileAsEmployee();
        $nosy = $this->userWithRoles(['employee']);

        $this->actingAs($nosy)->get(route('leave.show', $leave))->assertForbidden();
        $this->actingAs($this->marie())->get(route('leave.show', $leave))->assertOk();
    }

    public function test_the_applicant_may_cancel_while_pending(): void
    {
        [$applicant, $leave] = $this->fileAsEmployee();

        $this->actingAs($applicant)->post(route('leave.cancel', $leave))->assertRedirect();
        $this->assertSame(LeaveWorkflow::CANCELLED, $leave->fresh()->status);
        $this->actingAs($applicant->fresh())->get(route('leave.print', $leave->fresh()))->assertForbidden();
    }

    // ── Units ─────────────────────────────────────────────────────────

    public function test_leave_credits_accrue_at_1_25_days_per_month(): void
    {
        $this->assertSame(30.0, \App\Support\LeaveCredits::earned('2024-07-16', '2026-07-16'));
        $this->assertSame(0.0, \App\Support\LeaveCredits::earned('2026-07-01', '2026-07-16'));
        $this->assertSame(0.0, \App\Support\LeaveCredits::earned('2030-01-01', '2026-07-16'));
    }

    public function test_a_ranked_officer_signs_with_rank_and_branch(): void
    {
        config(['agency.branch_suffix' => 'PAF']);
        $employee = Employee::create([
            'first_name' => 'Adrian', 'middle_name' => 'Lee', 'last_name' => 'Mission',
            'rank' => 'LTC', 'is_civilian' => false, 'designation' => 'Director for Personnel',
        ]);
        $user = User::factory()->create(['name' => 'Adrian Mission', 'employee_id' => $employee->id]);

        $this->assertSame(
            ['rank' => 'LTC', 'name' => 'ADRIAN LEE MISSION', 'branch' => 'PAF', 'position' => '', 'designation' => 'Director for Personnel'],
            $user->signatoryBlock()
        );
    }

    public function test_a_civilian_signs_a_plain_name_without_branch(): void
    {
        $employee = Employee::create(['first_name' => 'Maria', 'last_name' => 'Cruz', 'rank' => null]);
        $user = User::factory()->create(['name' => 'Maria Cruz', 'employee_id' => $employee->id]);

        $this->assertSame('', $user->signatoryBlock()['branch']);
        $this->assertSame('MARIA CRUZ', $user->signatoryBlock()['name']);
    }

    public function test_admin_can_set_signatory_print_details(): void
    {
        $admin = $this->userWithRoles(['superadmin']);
        $employee = Employee::create(['first_name' => 'Adrian', 'last_name' => 'Mission']);

        $this->actingAs($admin)->patch(route('admin.employees.update', $employee), [
            'rank' => 'LTC', 'designation' => 'Director for Personnel', 'date_orig_appt' => '2010-01-01',
        ])->assertRedirect();

        $employee->refresh();
        $this->assertSame('LTC', $employee->rank);
        $this->assertSame('Director for Personnel', $employee->designation);
    }

    public function test_an_admin_can_edit_the_whole_official_record(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $employee = Employee::create([
            'emp_no' => '5867', 'first_name' => 'Stalin Joseph', 'last_name' => 'Baguio',
        ]);
        // The employee number doubles as the login username.
        $account = User::factory()->create(['employee_id' => $employee->id, 'username' => '5867']);

        $this->actingAs($admin)->patch(route('admin.employees.update', $employee), [
            'emp_no' => '5868',
            'item_no' => 'ADAS1-30-2013',
            'psipop_placement' => 'Wing HQ',
            'last_name' => 'Baguio', 'first_name' => 'Stalin Joseph', 'middle_name' => 'G',
            'sex' => 'Male',
            'date_of_birth' => '2002-02-01',
            'level' => 'First Level',
            'salary_grade' => 11, 'step_increment' => 2,
            'position' => 'Computer Operator',
            'office_department' => 'Directorate for Personnel',
            'date_orig_appt' => '2026-04-10',
            'date_assumption' => '2026-04-10',
            'contact_no' => '09171234567',
        ])->assertRedirect();

        $employee->refresh();
        $this->assertSame('ADAS1-30-2013', $employee->item_no);
        $this->assertSame('Male', $employee->sex);
        $this->assertSame(11, $employee->salary_grade);
        $this->assertSame('Computer Operator', $employee->position);
        $this->assertSame('Directorate for Personnel', $employee->office_department);
        // Correcting the employee number must follow through to the login.
        $this->assertSame('5868', $account->fresh()->username);
    }

    public function test_the_employee_number_must_stay_unique(): void
    {
        $admin = $this->userWithRoles(['admin']);
        Employee::create(['emp_no' => '5807', 'first_name' => 'Justin', 'last_name' => 'Bercades']);
        $other = Employee::create(['emp_no' => '5797', 'first_name' => 'Cyric', 'last_name' => 'Bulanan']);

        $this->actingAs($admin)->patch(route('admin.employees.update', $other), [
            'emp_no' => '5807',
        ])->assertSessionHasErrors('emp_no');

        $this->assertSame('5797', $other->fresh()->emp_no);
    }

    public function test_a_non_admin_cannot_edit_employees(): void
    {
        $user = $this->userWithRoles(['employee']);
        $employee = Employee::create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $this->actingAs($user)->patch(route('admin.employees.update', $employee), ['rank' => 'GEN'])
            ->assertForbidden();
    }

    public function test_the_seeders_are_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(LeaveTypeSeeder::class);

        $this->assertSame(15, LeaveType::count());   // 14 CSC types + Wellness
        $this->assertSame(5, Role::count());
    }
}
