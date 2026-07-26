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

/**
 * Civilian signature blocks (no rank, no branch) and the 7.A certification
 * appearing as soon as a draft is saved.
 */
class CivilianAndDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
        config(['agency.branch_suffix' => 'PAF']);
    }

    private function marie(): User
    {
        $u = User::factory()->create(['employee_id' => Employee::create([
            'emp_no' => '5112', 'first_name' => 'Marie Cris', 'middle_name' => 'Agbayani',
            'last_name' => 'Uri', 'rank' => 'Civ HR', 'is_civilian' => true,
            'position' => 'Admin Officer IV (HRMO II)', 'designation' => 'Wing Civilian Supervisor',
        ])->id]);
        $u->roles()->sync(Role::whereIn('name', ['admin', 'hr_officer'])->pluck('id'));

        return $u->fresh();
    }

    private function fileAs(User $applicant): LeaveApplication
    {
        $this->actingAs($applicant)->post(route('leave.store'), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bulanan',
            'applicant_first_name' => 'Cyric Richard', 'applicant_middle_name' => 'N',
            'date_filing' => '2026-07-03', 'position' => 'Clerk',
            'detail_vacation' => 'within_philippines',
            'date_from' => '2026-07-21', 'date_to' => '2026-07-22',
            'commutation' => 'requested',
        ])->assertRedirect();

        return LeaveApplication::firstOrFail();
    }

    private function civilianApplicant(): User
    {
        return User::factory()->create(['employee_id' => Employee::create([
            'emp_no' => '5797', 'first_name' => 'Cyric Richard', 'middle_name' => 'N',
            'last_name' => 'Bulanan', 'rank' => 'Civ HR', 'is_civilian' => true,
            'credits_accrual_start' => '2026-01-01',
        ])->id]);
    }

    public function test_a_civilian_prints_no_rank_and_no_branch_on_7a(): void
    {
        $marie = $this->marie();

        $this->assertSame([
            'rank'        => '',
            'name'        => 'MARIE CRIS AGBAYANI URI',
            'branch'      => '',
            'position'    => 'Admin Officer IV (HRMO II)',
            'designation' => 'Wing Civilian Supervisor',
        ], $marie->signatoryBlock());
    }

    public function test_a_civilian_applicant_prints_no_rank_on_6d(): void
    {
        $this->marie();
        $leave = $this->fileAs($this->civilianApplicant());

        $this->assertSame('CYRIC RICHARD N BULANAN', $leave->applicant_sig['name']);
        $this->assertSame('', $leave->applicant_sig['rank']);
        $this->assertSame('', $leave->applicant_sig['branch']);
    }

    public function test_a_military_signatory_still_prints_rank_and_branch(): void
    {
        $u = User::factory()->create(['employee_id' => Employee::create([
            'first_name' => 'Adrian Lee', 'middle_name' => 'G', 'last_name' => 'Mission',
            'rank' => 'LTC', 'is_civilian' => false, 'designation' => 'Director for Personnel',
        ])->id]);

        $block = $u->signatoryBlock();
        $this->assertSame('LTC', $block['rank']);
        $this->assertSame('PAF', $block['branch']);
    }

    public function test_saving_a_draft_fills_7a_on_the_printed_form(): void
    {
        $marie = $this->marie();
        $leave = $this->fileAs($this->civilianApplicant());

        // Save a draft with nothing typed — 7.A must still be certified.
        $this->actingAs($marie)->patch(route('leave.save', $leave))->assertRedirect();

        $leave->refresh();
        $this->assertNotNull($leave->cert_as_of);
        $this->assertNotNull($leave->vl_earned);
        // A vacation leave draws on VL only: SL has no "less" figure, and its
        // balance carries the total earned straight down.
        $this->assertEquals(2.0, (float) $leave->vl_less);
        $this->assertNull($leave->sl_less);
        $this->assertEquals((float) $leave->sl_earned, (float) $leave->sl_balance);

        // Still pending, and 7.C stays empty until a decision is made.
        $this->assertSame(LeaveWorkflow::PENDING, $leave->status);
        $this->assertNull($leave->decision);

        $html = $this->actingAs($marie)->get(route('leave.print', $leave))->getContent();
        $this->assertStringContainsString('—', $html);   // the SL dash
    }

    public function test_the_admin_can_switch_an_employee_between_civilian_and_military(): void
    {
        $marie = $this->marie();
        $employee = $marie->employee;

        $this->actingAs($marie)->patch(route('admin.employees.update', $employee), [
            'is_civilian' => false, 'rank' => 'MAJ',
        ])->assertRedirect();

        $this->assertFalse($employee->fresh()->is_civilian);
        $this->assertSame('MAJ', $marie->fresh()->signatoryBlock()['rank']);
        $this->assertSame('PAF', $marie->fresh()->signatoryBlock()['branch']);
    }

    public function test_the_migration_strips_civ_ranks_from_frozen_signature_blocks(): void
    {
        $marie = $this->marie();
        $leave = $this->fileAs($this->civilianApplicant());

        // Simulate a form filed before the fix: "Civ HR / PAF" baked in.
        $leave->update([
            'applicant_sig' => ['rank' => 'Civ HR', 'name' => 'CYRIC RICHARD N BULANAN', 'branch' => 'PAF', 'position' => '', 'designation' => ''],
            'hr_officer_sig' => ['rank' => 'Civ HR', 'name' => 'MARIE CRIS AGBAYANI URI', 'branch' => 'PAF', 'position' => 'Admin Officer IV (HRMO II)', 'designation' => 'Wing Civilian Supervisor'],
            'approver_sig' => ['rank' => 'LTC', 'name' => 'ADRIAN LEE G MISSION', 'branch' => 'PAF', 'position' => '', 'designation' => 'Director for Personnel'],
        ]);

        // Re-run the cleanup the migration performs.
        $migration = require database_path('migrations/2026_07_25_010000_add_is_civilian_to_employees.php');
        \Illuminate\Support\Facades\Schema::table('employees', fn ($t) => $t->dropColumn('is_civilian'));
        $migration->up();

        $leave->refresh();
        // Civilians lose the bogus rank and the branch...
        $this->assertSame('', $leave->applicant_sig['rank']);
        $this->assertSame('', $leave->applicant_sig['branch']);
        $this->assertSame('', $leave->hr_officer_sig['rank']);
        $this->assertSame('Wing Civilian Supervisor', $leave->hr_officer_sig['designation']);
        // ...while a genuine military block is untouched.
        $this->assertSame('LTC', $leave->approver_sig['rank']);
        $this->assertSame('PAF', $leave->approver_sig['branch']);
    }
}
