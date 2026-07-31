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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A signature image dropped straight onto the printed CS Form 6, per block —
 * including 7.B, which has no account. It prints over the name, ahead of any
 * account e-signature.
 */
class BlockSignatureTest extends TestCase
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

    private function applicant(): User
    {
        $u = User::factory()->create(['employee_id' => Employee::create([
            'emp_no' => '5807', 'first_name' => 'Justin', 'last_name' => 'Bercades',
        ])->id]);
        $u->roles()->sync(Role::where('name', 'employee')->pluck('id'));

        return $u->fresh();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync(Role::whereIn('name', ['admin', 'hr_officer'])->pluck('id'));

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

    public function test_the_applicant_uploads_a_signature_onto_any_block(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        // Even the 7.B recommender block — which has no linked account — takes
        // a signature this way.
        $this->actingAs($applicant)
            ->post(route('leave.block-signature.store', [$leave, 'recommender']), [
                'signature' => UploadedFile::fake()->image('sig.png'),
            ])->assertRedirect();

        $path = $leave->fresh()->signature_uploads['recommender'];
        $this->assertNotNull($path);
        Storage::assertExists($path);

        // …and it is served through the guarded route.
        $this->actingAs($applicant)
            ->get(route('leave.block-signature', [$leave, 'recommender']))
            ->assertOk();
    }

    public function test_uploading_replaces_the_previous_image(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($applicant)->post(
            route('leave.block-signature.store', [$leave, 'approver']),
            ['signature' => UploadedFile::fake()->image('first.png')]
        );
        $first = $leave->fresh()->signature_uploads['approver'];

        $this->actingAs($applicant)->post(
            route('leave.block-signature.store', [$leave, 'approver']),
            ['signature' => UploadedFile::fake()->image('second.png')]
        );
        $second = $leave->fresh()->signature_uploads['approver'];

        $this->assertNotSame($first, $second);
        Storage::assertMissing($first);
        Storage::assertExists($second);
    }

    public function test_it_prints_over_the_name_ahead_of_the_account_signature(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($applicant)->post(
            route('leave.block-signature.store', [$leave, 'applicant']),
            ['signature' => UploadedFile::fake()->image('sig.png')]
        );

        // An admin may open the printable form at any stage; the uploaded
        // image is referenced as an <img src> over the applicant's name, with a
        // root-relative path so it loads on any host/port.
        $path = parse_url(route('leave.block-signature', [$leave, 'applicant']), PHP_URL_PATH);
        $this->actingAs($this->admin())->get(route('leave.print', $leave))
            ->assertOk()
            ->assertSee($path, false);
    }

    /**
     * 7.A is the tightest cell on the form and has been re-tuned often. The ink
     * belongs in the gap between the leave-credit grid (which ends at 584pt)
     * and the printed name: over neither of them, and filling that gap.
     */
    public function test_the_7a_signature_sits_above_the_name_and_clears_the_grid(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($applicant)->post(
            route('leave.block-signature.store', [$leave, 'certifier']),
            ['signature' => UploadedFile::fake()->image('sig.png')]
        );

        $path = parse_url(route('leave.block-signature', [$leave, 'certifier']), PHP_URL_PATH);
        $html = $this->actingAs($this->admin())->get(route('leave.print', $leave))
            ->assertOk()->getContent();

        $img = substr($html, strpos($html, $path));
        $img = substr($img, 0, strpos($img, '>') + 1);

        preg_match('/top:\s*([\d.]+)pt/', $img, $top);
        preg_match('/height:\s*([\d.]+)pt/', $img, $height);

        $inkTop = (float) $top[1];
        $inkBottom = $inkTop + (float) $height[1];

        // Below the grid…
        $this->assertGreaterThan(584.0, $inkTop);
        // …above the printed name, never across it…
        $this->assertLessThanOrEqual(602.5, $inkBottom);
        // …and filling what is left, rather than a sliver of it.
        $this->assertGreaterThanOrEqual(16.0, (float) $height[1]);
    }

    public function test_removing_a_signature_clears_it(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($applicant)->post(
            route('leave.block-signature.store', [$leave, 'certifier']),
            ['signature' => UploadedFile::fake()->image('sig.png')]
        );
        $path = $leave->fresh()->signature_uploads['certifier'];

        $this->actingAs($applicant)
            ->delete(route('leave.block-signature.destroy', [$leave, 'certifier']))
            ->assertRedirect();

        Storage::assertMissing($path);
        $this->assertArrayNotHasKey('certifier', $leave->fresh()->signature_uploads ?? []);
    }

    public function test_an_admin_may_sign_any_block_too(): void
    {
        $leave = $this->file($this->applicant());

        $this->actingAs($this->admin())->post(
            route('leave.block-signature.store', [$leave, 'certifier']),
            ['signature' => UploadedFile::fake()->image('sig.png')]
        )->assertRedirect();

        $this->assertNotNull($leave->fresh()->signature_uploads['certifier']);
    }

    public function test_an_unrelated_user_cannot_upload_or_view(): void
    {
        $leave = $this->file($this->applicant());
        $other = User::factory()->create();
        $other->roles()->sync(Role::where('name', 'employee')->pluck('id'));

        $this->actingAs($other->fresh())->post(
            route('leave.block-signature.store', [$leave, 'approver']),
            ['signature' => UploadedFile::fake()->image('sig.png')]
        )->assertForbidden();
    }

    public function test_a_bad_slot_is_rejected(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($applicant)->post(
            route('leave.block-signature.store', [$leave, 'nonsense']),
            ['signature' => UploadedFile::fake()->image('sig.png')]
        )->assertNotFound();
    }

    public function test_signing_is_locked_once_the_leave_is_cancelled(): void
    {
        $applicant = $this->applicant();
        $leave = $this->file($applicant);

        $this->actingAs($applicant)->post(route('leave.cancel', $leave))->assertRedirect();

        $this->actingAs($applicant)->post(
            route('leave.block-signature.store', [$leave->fresh(), 'applicant']),
            ['signature' => UploadedFile::fake()->image('sig.png')]
        )->assertForbidden();
    }
}
