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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * E-signatures: uploaded per person, printed over their name on CS Form 6
 * once that block's act has happened.
 */
class SignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
        Storage::fake('local');
        config(['agency.branch_suffix' => 'PAF']);
    }

    private function marie(): User
    {
        $u = User::factory()->create(['employee_id' => Employee::create([
            'emp_no' => '5112', 'first_name' => 'Marie Cris', 'last_name' => 'Uri',
            'is_civilian' => true, 'designation' => 'Wing Civilian Supervisor',
        ])->id]);
        $u->roles()->sync(Role::whereIn('name', ['admin', 'hr_officer'])->pluck('id'));

        return $u->fresh();
    }

    private function mission(): User
    {
        $u = User::factory()->create(['employee_id' => Employee::create([
            'first_name' => 'Adrian Lee', 'middle_name' => 'G', 'last_name' => 'Mission',
            'rank' => 'LTC', 'is_civilian' => false, 'designation' => 'Director for Personnel',
        ])->id]);
        $u->roles()->sync(Role::where('name', 'approver')->pluck('id'));

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

    public function test_a_user_uploads_and_removes_their_own_signature(): void
    {
        $user = $this->applicant();

        $this->actingAs($user)->post(route('signature.store', $user), [
            'signature' => UploadedFile::fake()->image('sig.png'),
        ])->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->signature_path);
        Storage::assertExists($user->signature_path);

        // Any signed-in user can fetch it — it prints on shared forms.
        $this->actingAs($this->marie())->get(route('signature.show', $user))->assertOk();

        $this->actingAs($user)->delete(route('signature.destroy', $user))->assertRedirect();
        $this->assertNull($user->fresh()->signature_path);
    }

    public function test_an_admin_can_set_a_signature_for_the_director(): void
    {
        $mission = $this->mission();

        $this->actingAs($this->marie())->post(route('signature.store', $mission), [
            'signature' => UploadedFile::fake()->image('mission.png'),
        ])->assertRedirect();

        $this->assertNotNull($mission->fresh()->signature_path);
    }

    public function test_an_employee_cannot_set_someone_elses_signature(): void
    {
        $other = $this->mission();

        $this->actingAs($this->applicant())->post(route('signature.store', $other), [
            'signature' => UploadedFile::fake()->image('nope.png'),
        ])->assertForbidden();

        $this->assertNull($other->fresh()->signature_path);
    }

    public function test_only_images_are_accepted(): void
    {
        $user = $this->applicant();

        $this->actingAs($user)->post(route('signature.store', $user), [
            'signature' => UploadedFile::fake()->create('sig.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('signature');
    }

    public function test_signatures_print_only_once_that_block_has_acted(): void
    {
        $marie = $this->marie();
        $mission = $this->mission();
        $applicant = $this->applicant();

        foreach ([$marie, $mission, $applicant] as $u) {
            $this->actingAs($u->id === $applicant->id ? $applicant : $marie)
                ->post(route('signature.store', $u), [
                    'signature' => UploadedFile::fake()->image('sig.png'),
                ]);
        }

        $leave = $this->file($applicant);

        // Signatures print with a root-relative src, so assert on the path.
        $sig = fn ($u) => parse_url(route('signature.show', $u), PHP_URL_PATH);

        // Freshly filed: the applicant has signed 6.D, nobody else has acted.
        $html = $this->actingAs($marie)->get(route('leave.print', $leave))->getContent();
        $this->assertStringContainsString($sig($applicant), $html);
        $this->assertStringNotContainsString($sig($marie), $html);
        $this->assertStringNotContainsString($sig($mission), $html);

        // Certifying 7.A brings Marie's signature in, but not the approver's.
        $this->actingAs($marie)->patch(route('leave.save', $leave))->assertRedirect();
        $html = $this->actingAs($marie)->get(route('leave.print', $leave->fresh()))->getContent();
        $this->assertStringContainsString($sig($marie), $html);
        $this->assertStringNotContainsString($sig($mission), $html);

        // Approving brings in 7.C/7.D.
        $this->actingAs($marie)->post(route('leave.decide', $leave->fresh()), [
            'decision' => 'approved', 'days_with_pay' => 3,
        ])->assertRedirect();
        $html = $this->actingAs($marie)->get(route('leave.print', $leave->fresh()))->getContent();
        $this->assertStringContainsString($sig($mission), $html);
    }

    public function test_a_form_prints_normally_when_nobody_has_a_signature(): void
    {
        $marie = $this->marie();
        $this->mission();
        $leave = $this->file($this->applicant());

        // The applicant's name prints in box 2 (NAME); 6.D is now just their
        // signature over the line, with no duplicated name.
        $this->actingAs($marie)->get(route('leave.print', $leave))
            ->assertOk()
            ->assertSee('Bercades');
    }
}
