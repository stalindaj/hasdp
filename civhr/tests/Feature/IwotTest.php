<?php

namespace Tests\Feature;

use App\Models\IwotForm;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IwotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function manager(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync(Role::where('name', 'admin')->pluck('id'));

        return $u;
    }

    private function employee(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync(Role::where('name', 'employee')->pluck('id'));

        return $u;
    }

    private function payload(int $userId, int $year = 2026, int $semester = 1): array
    {
        return [
            'user_id' => $userId,
            'position_title' => 'Administrative Aide III (Clerk I)',
            'office_unit' => '15th Strike Wing, PAF / Office of Directorate for Personnel',
            'year' => $year,
            'semester' => $semester,
            'status' => 'draft',
            'prepared_by' => 'Jean Marie B Tubat',
            'prepared_designation' => 'Employee',
            'approved_by' => 'TSg Ronnie R Doble PAF',
            'approved_designation' => 'NCOIC',
            'groups' => [
                [
                    'major_final_output' => 'Monthly Reportorial Reports',
                    'timeliness' => 'Monthly',
                    'success_indicator' => 'Submit Monthly Reports every 25th of the month with 90% accuracy.',
                    'rows' => [
                        [
                            'performance_measure' => 'Quality',
                            'performance_targets' => '90% Accuracy',
                            'outstanding' => '100% Accurate (No Error)',
                            'very_satisfactory' => '95% Accurate (3 Errors in Record)',
                            'satisfactory' => '90% Accurate (6 Errors in Record)',
                            'unsatisfactory' => '85% Accurate (9 Errors in Record)',
                            'poor' => '83% less Accurate (10 and more Errors in Record)',
                        ],
                        [
                            'performance_measure' => 'Timeliness',
                            'performance_targets' => '10th Day of the Month',
                            'outstanding' => '21st Day of the Month',
                            'very_satisfactory' => '23rd Day of the Month',
                            'satisfactory' => '25th Day of the month',
                            'unsatisfactory' => '27th Day of the month',
                            'poor' => 'Beyond 28th Day of the Month',
                        ],
                        ['performance_measure' => 'Quantity', 'performance_targets' => '-'],
                    ],
                ],
            ],
        ];
    }

    public function test_employee_saves_a_draft_and_it_keeps_the_matrix(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->post(route('iwot.store'), $this->payload($employee->id))
            ->assertRedirect();

        $form = IwotForm::with('groups.rows')->first();

        $this->assertSame($employee->id, $form->user_id);
        $this->assertSame('draft', $form->status);
        $this->assertCount(1, $form->groups);
        $this->assertCount(3, $form->groups->first()->rows);
        $this->assertSame('Quality', $form->groups->first()->rows->first()->performance_measure);
        $this->assertSame('100% Accurate (No Error)', $form->groups->first()->rows->first()->outstanding);
    }

    public function test_print_renders_the_official_sheet(): void
    {
        $employee = $this->employee();
        $this->actingAs($employee)->post(route('iwot.store'), $this->payload($employee->id));

        $this->actingAs($employee)->get(route('iwot.print', IwotForm::first()))
            ->assertOk()
            ->assertSee('MAJOR FINAL OUTPUT')
            ->assertSee('PERFORMANCE STANDARDS')
            ->assertSee('PREPARED BY:')
            ->assertSee('APPROVED BY:')
            ->assertSee('Monthly Reportorial Reports')
            ->assertSee('Beyond 28th Day of the Month');
    }

    public function test_employee_and_manager_can_both_sign_either_block(): void
    {
        Storage::fake('local');

        $employee = $this->employee();
        $this->actingAs($employee)->post(route('iwot.store'), $this->payload($employee->id));
        $form = IwotForm::first();

        $ink = UploadedFile::fake()->image('sig.png', 400, 120);

        $this->actingAs($employee)
            ->post(route('iwot.signature.store', [$form, 'prepared']), ['signature' => $ink])
            ->assertRedirect();

        $form->refresh();
        $this->assertNotEmpty($form->signature_uploads['prepared']);
        Storage::assertExists($form->signature_uploads['prepared']);

        // The NCOIC signs on paper, so the employee uploads the scan of it —
        // and a manager can do the same.
        $this->actingAs($employee)
            ->post(route('iwot.signature.store', [$form, 'approved']), ['signature' => $ink])
            ->assertRedirect();

        $form->refresh();
        $this->assertNotEmpty($form->signature_uploads['approved']);

        $this->actingAs($this->manager())
            ->post(route('iwot.signature.store', [$form, 'approved']), ['signature' => $ink])
            ->assertRedirect();

        // Somebody else's IWOT is still off limits.
        $this->actingAs($this->employee())
            ->post(route('iwot.signature.store', [$form, 'approved']), ['signature' => $ink])
            ->assertForbidden();

        // …and it prints over the name.
        $this->actingAs($employee)->get(route('iwot.print', $form))
            ->assertOk()
            ->assertSee('/iwot/'.$form->id.'/signature/prepared', false);
    }

    public function test_signature_can_be_removed(): void
    {
        Storage::fake('local');

        $employee = $this->employee();
        $this->actingAs($employee)->post(route('iwot.store'), $this->payload($employee->id));
        $form = IwotForm::first();

        $this->actingAs($employee)->post(route('iwot.signature.store', [$form, 'prepared']), [
            'signature' => UploadedFile::fake()->image('sig.png', 400, 120),
        ]);

        $this->actingAs($employee)
            ->delete(route('iwot.signature.destroy', [$form, 'prepared']))
            ->assertRedirect();

        $this->assertEmpty(IwotForm::first()->signature_uploads['prepared'] ?? null);
    }

    public function test_employee_only_sees_their_own_sheets(): void
    {
        $mine = $this->employee();
        $other = $this->employee();

        $a = IwotForm::create(['user_id' => $mine->id, 'status' => 'draft']);
        $b = IwotForm::create(['user_id' => $other->id, 'status' => 'draft']);

        $this->actingAs($mine)->get(route('iwot.show', $a))->assertOk();
        $this->actingAs($mine)->get(route('iwot.show', $b))->assertForbidden();
        $this->actingAs($this->manager())->get(route('iwot.show', $b))->assertOk();
    }

    public function test_submit_then_manager_approves(): void
    {
        $employee = $this->employee();
        $manager = $this->manager();
        $this->actingAs($employee)->post(route('iwot.store'), $this->payload($employee->id));
        $form = IwotForm::first();

        $this->actingAs($employee)->post(route('iwot.submit', $form))->assertRedirect();
        $this->assertSame('submitted', $form->fresh()->status);

        // The employee cannot approve their own targets.
        $this->actingAs($employee)->post(route('iwot.decide', $form), ['decision' => 'approve'])->assertForbidden();

        $this->actingAs($manager)->post(route('iwot.decide', $form), ['decision' => 'approve'])->assertRedirect();
        $form->refresh();
        $this->assertSame('approved', $form->status);
        $this->assertSame($manager->id, $form->approved_by_id);

        // Once approved the employee can no longer edit it.
        $this->actingAs($employee)->get(route('iwot.edit', $form))->assertForbidden();
    }

    /** One hat at a time — an admin in employee mode sees only their own. */
    public function test_admin_in_employee_mode_is_only_an_employee(): void
    {
        $admin = $this->manager();
        $other = $this->employee();

        $mine = IwotForm::create(['user_id' => $admin->id, 'status' => 'draft']);
        $theirs = IwotForm::create(['user_id' => $other->id, 'status' => 'submitted']);

        $this->actingAs($admin)->get(route('iwot.show', $theirs))->assertOk();

        $this->actingAs($admin)->post(route('view-mode.toggle'))->assertRedirect();

        $this->actingAs($admin)->get(route('iwot.show', $mine))->assertOk();
        $this->actingAs($admin)->get(route('iwot.show', $theirs))->assertForbidden();
        $this->actingAs($admin)->delete(route('iwot.destroy', $theirs))->assertForbidden();
        $this->actingAs($admin)
            ->post(route('iwot.decide', $theirs), ['decision' => 'approve'])
            ->assertForbidden();
    }

    /** The status dropdown is limited in the UI; the server must enforce it. */
    public function test_employee_cannot_self_approve_through_the_status_field(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)->post(route('iwot.store'), [
            ...$this->payload($employee->id),
            'status' => 'approved',
        ])->assertRedirect();

        $this->assertSame('draft', IwotForm::first()->status);
    }

    public function test_nobody_approves_their_own_targets(): void
    {
        $admin = $this->manager();
        $own = IwotForm::create(['user_id' => $admin->id, 'status' => 'submitted']);

        $this->actingAs($admin)
            ->post(route('iwot.decide', $own), ['decision' => 'approve'])
            ->assertForbidden();
    }

    public function test_employee_cannot_file_for_someone_else_or_delete(): void
    {
        $employee = $this->employee();
        $other = $this->employee();

        $this->actingAs($employee)->post(route('iwot.store'), $this->payload($other->id))->assertRedirect();
        $this->assertSame($employee->id, IwotForm::first()->user_id);

        $this->actingAs($employee)->delete(route('iwot.destroy', IwotForm::first()))->assertForbidden();
    }

    public function test_only_one_iwot_per_person_per_semester(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->post(route('iwot.store'), $this->payload($employee->id, 2026, 1))
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(route('iwot.store'), $this->payload($employee->id, 2026, 1))
            ->assertSessionHasErrors('semester');

        // The other semester is fine — two a year, never more.
        $this->actingAs($employee)
            ->post(route('iwot.store'), $this->payload($employee->id, 2026, 2))
            ->assertRedirect();

        $this->assertSame(2, IwotForm::where('user_id', $employee->id)->count());
    }

    public function test_submitted_iwots_land_in_the_pending_queue(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();

        IwotForm::create(['user_id' => $employee->id, 'year' => 2026, 'semester' => 1, 'status' => 'draft']);
        $waiting = IwotForm::create([
            'user_id' => $employee->id, 'year' => 2026, 'semester' => 2, 'status' => IwotForm::SUBMITTED,
        ]);

        $this->actingAs($manager)->get(route('iwot.index'))
            ->assertInertia(fn ($page) => $page
                ->where('filter', 'pending')
                ->where('pendingCount', 1)
                ->has('forms', 1)
                ->where('forms.0.id', $waiting->id));

        $this->actingAs($manager)->get(route('iwot.index', ['filter' => 'all']))
            ->assertInertia(fn ($page) => $page->has('forms', 2));
    }
}
