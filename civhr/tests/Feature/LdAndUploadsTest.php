<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LdEntry;
use App\Models\LeaveApplication;
use App\Models\LeaveCreditEntry;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Support\LeaveWorkflow;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * L&D submissions with photo proof, the signed-form upload, and the admin
 * balance grid.
 */
class LdAndUploadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
        Storage::fake('local');
    }

    private function userWithRoles(array $roles, ?array $employee = null): User
    {
        $user = User::factory()->create(
            $employee ? ['employee_id' => Employee::create($employee)->id] : []
        );
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user->fresh();
    }

    private function employeeUser(): User
    {
        return $this->userWithRoles(['employee'], [
            'emp_no' => '5807', 'first_name' => 'Justin', 'last_name' => 'Bercades',
        ]);
    }

    private ?User $admin = null;

    private function adminUser(): User
    {
        return $this->admin ??= $this->userWithRoles(['admin', 'hr_officer'], [
            'emp_no' => '5112', 'first_name' => 'Marie Cris', 'last_name' => 'Uri',
        ]);
    }

    // ── L&D submissions ───────────────────────────────────────────────

    public function test_an_employee_submits_ld_with_proof_and_it_waits_pending(): void
    {
        $user = $this->employeeUser();

        $this->actingAs($user)->post(route('ld.store'), [
            'title' => 'Records Management Seminar',
            'hours' => 8,
            'date'  => '2026-07-10',
            'certificate' => UploadedFile::fake()->image('cert.jpg'),
        ])->assertRedirect();

        $entry = LdEntry::firstOrFail();
        $this->assertSame(LdEntry::PENDING, $entry->status);
        $this->assertSame($user->id, $entry->submitted_by);
        Storage::assertExists($entry->certificate_path);
        $this->assertNull($entry->photo_path);
    }

    public function test_ld_submission_requires_at_least_one_image(): void
    {
        $this->actingAs($this->employeeUser())->post(route('ld.store'), [
            'title' => 'Seminar', 'hours' => 4, 'date' => '2026-07-10',
        ])->assertSessionHasErrors(['certificate', 'photo']);

        $this->assertSame(0, LdEntry::count());
    }

    public function test_pending_hours_do_not_count_until_approved(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;

        $this->actingAs($user)->post(route('ld.store'), [
            'title' => 'Seminar', 'hours' => 8, 'date' => '2026-07-10',
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $this->assertEquals(0.0, (float) $employee->ldEntries()->approved()->sum('hours'));

        $this->actingAs($this->adminUser())
            ->patch(route('ld.decide', LdEntry::firstOrFail()), ['decision' => 'approved'])
            ->assertRedirect();

        $this->assertEquals(8.0, (float) $employee->ldEntries()->approved()->sum('hours'));
    }

    public function test_rejection_requires_remarks_and_records_them(): void
    {
        $user = $this->employeeUser();
        $this->actingAs($user)->post(route('ld.store'), [
            'title' => 'Seminar', 'hours' => 8, 'date' => '2026-07-10',
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ]);
        $entry = LdEntry::firstOrFail();
        $admin = $this->adminUser();

        $this->actingAs($admin)->patch(route('ld.decide', $entry), [
            'decision' => 'rejected',
        ])->assertSessionHasErrors('remarks');

        $this->actingAs($admin)->patch(route('ld.decide', $entry), [
            'decision' => 'rejected', 'remarks' => 'Certificate unreadable — please rescan.',
        ])->assertRedirect();

        $this->assertSame(LdEntry::REJECTED, $entry->fresh()->status);
        $this->assertSame('Certificate unreadable — please rescan.', $entry->fresh()->remarks);
    }

    public function test_a_regular_employee_cannot_decide_ld(): void
    {
        $this->actingAs($this->employeeUser())->post(route('ld.store'), [
            'title' => 'Seminar', 'hours' => 8, 'date' => '2026-07-10',
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $this->actingAs($this->userWithRoles(['employee']))
            ->patch(route('ld.decide', LdEntry::firstOrFail()), ['decision' => 'approved'])
            ->assertForbidden();
    }

    public function test_proof_files_are_private_to_the_owner_and_admins(): void
    {
        $owner = $this->employeeUser();
        $this->actingAs($owner)->post(route('ld.store'), [
            'title' => 'Seminar', 'hours' => 8, 'date' => '2026-07-10',
            'certificate' => UploadedFile::fake()->image('cert.jpg'),
        ]);
        $entry = LdEntry::firstOrFail();

        $this->actingAs($owner)->get(route('ld.file', [$entry, 'certificate']))->assertOk();
        $this->actingAs($this->adminUser())->get(route('ld.file', [$entry, 'certificate']))->assertOk();
        // A different employee gets nothing.
        $this->actingAs($this->userWithRoles(['employee']))
            ->get(route('ld.file', [$entry, 'certificate']))->assertForbidden();
        // No photo was uploaded → 404, not an error page.
        $this->actingAs($owner)->get(route('ld.file', [$entry, 'photo']))->assertNotFound();
    }

    public function test_admin_logged_ld_is_approved_immediately(): void
    {
        $admin = $this->adminUser();
        $employee = $this->employeeUser()->employee;

        $this->actingAs($admin)->post(route('dashboard.ld', $employee), [
            'title' => 'GAD Seminar', 'hours' => 4, 'date' => '2026-07-01',
        ])->assertRedirect();

        $this->assertSame(LdEntry::APPROVED, LdEntry::firstOrFail()->status);
    }

    // ── The signed CS Form 6 upload ───────────────────────────────────

    private function approvedLeave(): array
    {
        $applicant = $this->employeeUser();

        $this->actingAs($applicant)->post(route('leave.store'), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => '2026-07-03',
            'position' => 'Clerk', 'detail_vacation' => 'within_philippines',
            'date_from' => '2026-07-21', 'date_to' => '2026-07-22',
            'commutation' => 'not_requested',
        ]);
        $leave = LeaveApplication::firstOrFail();

        $this->actingAs($this->adminUser())->post(route('leave.decide', $leave), [
            'decision' => 'approved', 'days_with_pay' => 2,
        ]);

        return [$applicant, $leave->fresh()];
    }

    public function test_the_applicant_uploads_the_signed_form_after_approval(): void
    {
        [$applicant, $leave] = $this->approvedLeave();

        $this->actingAs($applicant)->post(route('leave.signed-form.store', $leave), [
            'signed_form' => UploadedFile::fake()->image('signed.jpg'),
        ])->assertRedirect();

        $leave->refresh();
        $this->assertNotNull($leave->signed_form_path);
        Storage::assertExists($leave->signed_form_path);

        // Owner and admin can view it; a stranger cannot.
        $this->actingAs($applicant)->get(route('leave.signed-form', $leave))->assertOk();
        $this->actingAs($this->adminUser())->get(route('leave.signed-form', $leave))->assertOk();
        $this->actingAs($this->userWithRoles(['employee']))
            ->get(route('leave.signed-form', $leave))->assertForbidden();
    }

    public function test_the_signed_form_cannot_be_uploaded_before_approval(): void
    {
        $applicant = $this->employeeUser();
        $this->actingAs($applicant)->post(route('leave.store'), [
            'leave_type_id' => LeaveType::where('code', 'vacation')->value('id'),
            'office_department' => 'DP', 'applicant_last_name' => 'Bercades',
            'applicant_first_name' => 'Justin', 'date_filing' => '2026-07-03',
            'position' => 'Clerk', 'detail_vacation' => 'within_philippines',
            'date_from' => '2026-07-21', 'date_to' => '2026-07-22',
            'commutation' => 'not_requested',
        ]);

        $this->actingAs($applicant)
            ->post(route('leave.signed-form.store', LeaveApplication::firstOrFail()), [
                'signed_form' => UploadedFile::fake()->image('signed.jpg'),
            ])->assertForbidden();
    }

    public function test_only_the_applicant_may_upload_the_signed_form(): void
    {
        [, $leave] = $this->approvedLeave();

        $this->actingAs($this->userWithRoles(['employee']))
            ->post(route('leave.signed-form.store', $leave), [
                'signed_form' => UploadedFile::fake()->image('signed.jpg'),
            ])->assertForbidden();
    }

    // ── The admin balance grid ────────────────────────────────────────

    public function test_the_balance_grid_sets_a_balance_through_the_ledger(): void
    {
        $admin = $this->adminUser();
        $employee = $this->employeeUser()->employee;

        $this->actingAs($admin)->get(route('admin.balances.index'))->assertOk();

        // Fresh employee: 1.25 VL accrued this month. Set it to 15.
        $this->actingAs($admin)->patch(route('admin.balances.update', $employee), [
            'kind' => 'vl', 'value' => 15, 'note' => 'Opening balance per 201 file',
        ])->assertRedirect();

        $this->assertEquals(15.0, \App\Support\CreditLedger::balances($employee->fresh())['vl']);
        // …and the adjustment (the delta, not the target) is in the ledger.
        $this->assertEquals(
            13.75,
            (float) LeaveCreditEntry::where('employee_id', $employee->id)
                ->whereNull('period')->value('amount')
        );
    }

    public function test_the_balance_grid_is_admin_only(): void
    {
        $employee = $this->employeeUser();

        $this->actingAs($employee)->get(route('admin.balances.index'))->assertForbidden();
        $this->actingAs($employee)
            ->patch(route('admin.balances.update', $employee->employee), [
                'kind' => 'vl', 'value' => 99,
            ])->assertForbidden();
    }
}
