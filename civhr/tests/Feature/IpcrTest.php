<?php

namespace Tests\Feature;

use App\Models\IpcrForm;
use App\Models\Role;
use App\Models\User;
use App\Support\IpcrAccess;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrTest extends TestCase
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

    private function payload(int $rateeId, int $year = 2026, int $semester = 1): array
    {
        return [
            'user_id' => $rateeId,
            'year' => $year,
            'semester' => $semester,
            'status' => 'approved',
            'groups' => [
                [
                    'major_final_output' => 'Maintain aircraft readiness',
                    'success_indicator' => '95% availability',
                    'quality_rating' => 5,
                    'timeliness_rating' => 4,
                    'quantity_rating' => 4, // avg 4.33
                    'rows' => [
                        ['performance_measure' => 'Quality', 'outstanding' => '100%'],
                        ['performance_measure' => 'Timeliness'],
                        ['performance_measure' => 'Quantity'],
                    ],
                ],
                [
                    'major_final_output' => 'Records management',
                    'quality_rating' => 5,
                    'timeliness_rating' => 5,
                    'quantity_rating' => 5, // avg 5
                    'rows' => [],
                ],
            ],
        ];
    }

    public function test_manager_creates_ipcr_and_ratings_compute_server_side(): void
    {
        $manager = $this->manager();
        $ratee = $this->employee();

        $this->actingAs($manager)
            ->post(route('ipcr.store'), $this->payload($ratee->id))
            ->assertRedirect();

        $form = IpcrForm::with('groups')->first();

        $this->assertNotNull($form);
        $this->assertSame($ratee->id, $form->user_id);
        $this->assertCount(2, $form->groups);

        // group1 = (5+4+4)/3 = 4.33 ; group2 = 5 ; overall = (4.33+5)/2 = 4.67
        $this->assertEqualsWithDelta(4.33, (float) $form->groups[0]->average_rating, 0.01);
        $this->assertEqualsWithDelta(5.00, (float) $form->groups[1]->average_rating, 0.01);
        $this->assertEqualsWithDelta(4.67, (float) $form->overall_rating, 0.01);
        $this->assertSame('Outstanding', $form->fe_overall_adjectival_rating);
    }

    public function test_ratings_are_derived_from_the_percent_against_the_matrix_standards(): void
    {
        $ratee = $this->employee();

        // Only percentages are sent — no ratings — so the server must read them
        // off the Performance Standards descriptors itself.
        $this->actingAs($ratee)->post(route('ipcr.store'), [
            'user_id' => $ratee->id,
            'year' => 2020,
            'semester' => 1,
            'status' => 'draft',
            'groups' => [[
                'major_final_output' => 'Process leave applications',
                'quality_pct' => 97,      // >= 95 -> Outstanding (5)
                'timeliness_pct' => 91,   // >= 90 -> Very Satisfactory (4)
                'quantity_pct' => 40,     // below Unsatisfactory -> Poor (1)
                'rows' => [
                    [
                        'performance_measure' => 'Quality',
                        'outstanding' => '95% and above', 'very_satisfactory' => '90-94%',
                        'satisfactory' => '85-89%', 'unsatisfactory' => '80-84%', 'poor' => 'below 80%',
                        'selected_band' => 'o',
                    ],
                    [
                        'performance_measure' => 'Timeliness',
                        'outstanding' => '95% and above', 'very_satisfactory' => '90-94%',
                        'satisfactory' => '85-89%', 'unsatisfactory' => '80-84%', 'poor' => 'below 80%',
                    ],
                    [
                        'performance_measure' => 'Quantity',
                        'outstanding' => '95% and above', 'very_satisfactory' => '90-94%',
                        'satisfactory' => '85-89%', 'unsatisfactory' => '80-84%', 'poor' => 'below 80%',
                    ],
                ],
            ]],
        ])->assertRedirect();

        $group = IpcrForm::with('groups.rows')->first()->groups->first();

        $this->assertEqualsWithDelta(5, (float) $group->quality_rating, 0.01);
        $this->assertEqualsWithDelta(4, (float) $group->timeliness_rating, 0.01);
        $this->assertEqualsWithDelta(1, (float) $group->quantity_rating, 0.01);
        $this->assertEqualsWithDelta(3.33, (float) $group->average_rating, 0.01);

        // The clicked standard cell is remembered so the green check survives.
        $this->assertSame('o', $group->rows->first()->selected_band);
    }

    public function test_intervening_activities_add_to_the_overall_score_and_form_e_dates_are_free_text(): void
    {
        $ratee = $this->employee();

        $this->actingAs($ratee)->post(route('ipcr.store'), [
            'user_id' => $ratee->id,
            'year' => 2021,
            'semester' => 2,
            'status' => 'draft',
            // Filled from the rating period — not calendar dates.
            'fe_reviewed_date' => 'January',
            'fe_approved_date' => 'January',
            'discussed_date' => 'June 2026',
            'fe_assessed_date' => 'June 2026',
            'fe_final_rating_date' => 'June 2026',
            'fe_intervening_activities' => [
                ['activity' => 'Typhoon response detail', 'rating' => 0.25],
                ['activity' => 'Inventory augmentation', 'rating' => 0.5],
            ],
            'groups' => [[
                'major_final_output' => 'Records management',
                'quality_rating' => 4,
                'timeliness_rating' => 4,
                'quantity_rating' => 4,
                'rows' => [],
            ]],
        ])->assertRedirect();

        $form = IpcrForm::with('groups')->first();

        $this->assertSame('January', $form->fe_reviewed_date);
        $this->assertSame('June 2026', $form->fe_final_rating_date);
        $this->assertCount(2, $form->fe_intervening_activities);

        // average 4.00 + intervening 0.75 = 4.75 -> Outstanding
        $this->assertEqualsWithDelta(4.00, (float) $form->fe_average_point_score, 0.01);
        $this->assertEqualsWithDelta(0.75, (float) $form->fe_intervening_activity, 0.01);
        $this->assertEqualsWithDelta(4.75, (float) $form->fe_overall_point_score, 0.01);
        $this->assertSame('Outstanding', $form->fe_overall_adjectival_rating);
    }

    public function test_overall_numerical_rating_is_capped_at_five(): void
    {
        $ratee = $this->employee();

        $this->actingAs($ratee)->post(route('ipcr.store'), [
            'user_id' => $ratee->id,
            'year' => 2021,
            'semester' => 1,
            'status' => 'draft',
            'fe_intervening_activities' => [['activity' => 'Extra duty', 'rating' => 1.5]],
            'groups' => [[
                'major_final_output' => 'Everything, perfectly',
                'quality_rating' => 5, 'timeliness_rating' => 5, 'quantity_rating' => 5,
                'rows' => [],
            ]],
        ])->assertRedirect();

        $form = IpcrForm::first();

        $this->assertEqualsWithDelta(6.50, (float) $form->fe_overall_point_score, 0.01);
        $this->assertEqualsWithDelta(5.00, (float) $form->fe_overall_numerical_rating, 0.01);
        $this->assertEqualsWithDelta(5.00, (float) $form->overall_rating, 0.01);
    }

    public function test_print_renders_the_official_form_e(): void
    {
        $ratee = $this->employee();

        $this->actingAs($ratee)->post(route('ipcr.store'), [
            'user_id' => $ratee->id,
            'year' => 2022,
            'semester' => 2,
            'status' => 'draft',
            'fe_reviewed_by' => 'TSg Ronnie R Doble PAF',
            'fe_approved_by' => 'MAJ Ariel Dickson C Almeda PAF',
            'strategic_priority' => 'Territorial defense, security and stability services',
            'core_function' => 'Administration of PAF Civ HRs',
            'groups' => [[
                'major_final_output' => 'Maintain the personnel database',
                'success_indicator' => '100% of records updated monthly',
                'actual_accomplishment' => 'All records updated by the 21st',
                'quality_rating' => 5, 'timeliness_rating' => 4, 'quantity_rating' => 4,
                'rows' => [['performance_measure' => 'Quality', 'outstanding' => '95% and above']],
            ]],
        ])->assertRedirect();

        $this->actingAs($ratee)->get(route('ipcr.print', IpcrForm::first()))
            ->assertOk()
            ->assertSee('INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)')
            ->assertSee('(FORM E)')
            ->assertSee('Reviewed by')
            ->assertSee('Approved by')
            ->assertSee('Ql1')
            ->assertSee('TSg Ronnie R Doble PAF')
            ->assertSee('Comments and Recommendations for Development Purposes')
            ->assertSee('Strategic Priority No.: Territorial defense, security and stability services')
            ->assertSee('Maintain the personnel database')
            // (5 + 4 + 4) / 3 = 4.33 -> Very Satisfactory
            ->assertSee('Overall Equivalent Adjectival Rating')
            ->assertSee('Very Satisfactory');
    }

    public function test_ratee_signs_their_own_blocks_only_and_the_ink_prints(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $ratee = $this->employee();
        $form = IpcrForm::create(['user_id' => $ratee->id, 'year' => 2022,
            'semester' => 1, 'status' => 'draft']);
        $ink = \Illuminate\Http\UploadedFile::fake()->image('sig.png', 400, 120);

        // The commitment block and "Discussed with" are the ratee's own.
        $this->actingAs($ratee)
            ->post(route('ipcr.signature.store', [$form, 'ratee']), ['signature' => $ink])
            ->assertRedirect();

        // The supervisor blocks are not.
        $this->actingAs($ratee)
            ->post(route('ipcr.signature.store', [$form, 'approver']), ['signature' => $ink])
            ->assertForbidden();

        $this->actingAs($this->manager())
            ->post(route('ipcr.signature.store', [$form, 'approver']), ['signature' => $ink])
            ->assertRedirect();

        $form->refresh();
        \Illuminate\Support\Facades\Storage::assertExists($form->signature_uploads['ratee']);

        $this->actingAs($ratee)->get(route('ipcr.print', $form))
            ->assertOk()
            ->assertSee('/ipcr/'.$form->id.'/signature/ratee', false);

        // …and it can be taken off again.
        $this->actingAs($ratee)
            ->delete(route('ipcr.signature.destroy', [$form, 'ratee']))
            ->assertRedirect();

        $this->assertEmpty(IpcrForm::first()->signature_uploads['ratee'] ?? null);
    }

    public function test_ratee_only_sees_their_own_forms(): void
    {
        $manager = $this->manager();
        $ratee = $this->employee();
        $other = $this->employee();

        $mine = IpcrForm::create(['user_id' => $ratee->id, 'year' => 2023,
            'semester' => 2, 'status' => 'draft']);
        $theirs = IpcrForm::create(['user_id' => $other->id, 'year' => 2023,
            'semester' => 1, 'status' => 'draft']);

        $this->actingAs($ratee)->get(route('ipcr.show', $mine))->assertOk();
        $this->actingAs($ratee)->get(route('ipcr.show', $theirs))->assertForbidden();

        // Manager sees everyone.
        $this->actingAs($manager)->get(route('ipcr.show', $theirs))->assertOk();
    }

    public function test_ratee_cannot_file_for_someone_else(): void
    {
        $ratee = $this->employee();
        $other = $this->employee();

        $this->actingAs($ratee)
            ->post(route('ipcr.store'), $this->payload($other->id))
            ->assertRedirect();

        // Forced back to the ratee themselves.
        $this->assertSame($ratee->id, IpcrForm::first()->user_id);
    }

    /**
     * One hat at a time: while an admin is in employee mode they are just a
     * ratee — their own IPCR only, no rating of others, no approving.
     */
    public function test_admin_in_employee_mode_is_only_a_ratee(): void
    {
        $admin = $this->manager();
        $other = $this->employee();

        $mine = IpcrForm::create(['user_id' => $admin->id, 'year' => 2024,
            'semester' => 2, 'status' => 'draft']);
        $theirs = IpcrForm::create(['user_id' => $other->id, 'year' => 2024,
            'semester' => 1, 'status' => 'submitted']);

        // Admin hat: everyone's forms, and they may decide.
        $this->actingAs($admin)->get(route('ipcr.show', $theirs))->assertOk();
        $this->assertTrue(IpcrAccess::isManager($admin));

        // Switch to the employee hat.
        $this->actingAs($admin)->post(route('view-mode.toggle'))->assertRedirect();

        $this->actingAs($admin)->get(route('ipcr.show', $mine))->assertOk();
        $this->actingAs($admin)->get(route('ipcr.show', $theirs))->assertForbidden();
        $this->actingAs($admin)->get(route('ipcr.edit', $theirs))->assertForbidden();
        $this->actingAs($admin)->delete(route('ipcr.destroy', $theirs))->assertForbidden();
        $this->actingAs($admin)
            ->post(route('ipcr.decide', $theirs), ['decision' => 'approve'])
            ->assertForbidden();

        // Filing while in employee mode files for themselves, not for others.
        $this->actingAs($admin)->post(route('ipcr.store'), $this->payload($other->id))->assertRedirect();
        $this->assertSame($admin->id, IpcrForm::latest('id')->first()->user_id);
    }

    /** The status dropdown is limited in the UI; the server must enforce it. */
    public function test_ratee_cannot_self_approve_through_the_status_field(): void
    {
        $ratee = $this->employee();

        // payload() asks for "approved" — a ratee must not get it.
        $this->actingAs($ratee)->post(route('ipcr.store'), $this->payload($ratee->id))->assertRedirect();

        $this->assertSame('draft', IpcrForm::first()->status);
    }

    public function test_nobody_approves_their_own_ipcr(): void
    {
        $admin = $this->manager();
        $own = IpcrForm::create(['user_id' => $admin->id, 'year' => 2025,
            'semester' => 2, 'status' => 'submitted']);

        $this->actingAs($admin)
            ->post(route('ipcr.decide', $own), ['decision' => 'approve'])
            ->assertForbidden();

        $this->assertSame('submitted', $own->fresh()->status);
    }

    public function test_ratee_cannot_delete(): void
    {
        $ratee = $this->employee();
        $form = IpcrForm::create(['user_id' => $ratee->id, 'year' => 2025,
            'semester' => 1, 'status' => 'draft']);

        $this->actingAs($ratee)->delete(route('ipcr.destroy', $form))->assertForbidden();
        $this->assertDatabaseHas('ipcr_forms', ['id' => $form->id]);
    }

    public function test_typed_signatories_and_submit_approve_workflow(): void
    {
        $manager = $this->manager();
        $ratee = $this->employee();

        // Ratee files with typed (hand-entered) supervisors.
        $this->actingAs($ratee)->post(route('ipcr.store'), [
            'user_id' => $ratee->id,
            'year' => 2026,
            'semester' => 2,
            'status' => 'draft',
            'reviewer_name' => 'TSg Ronnie R Doble PAF',
            'reviewer_designation' => 'Pneudraulics Shop Supervisor / NCOIC',
            'approver_name' => 'MAJ Ariel Dickson C Almeda PAF',
            'approver_designation' => '461st FWFMS Commanding Officer',
            'groups' => [],
        ])->assertRedirect();

        $form = IpcrForm::first();
        $this->assertSame('TSg Ronnie R Doble PAF', $form->reviewer_sig['name']);
        $this->assertSame('461st FWFMS Commanding Officer', $form->approver_sig['designation']);

        // Ratee submits.
        $this->actingAs($ratee)->post(route('ipcr.submit', $form))->assertRedirect();
        $this->assertSame('submitted', $form->fresh()->status);

        // Ratee cannot approve their own.
        $this->actingAs($ratee)->post(route('ipcr.decide', $form), ['decision' => 'approve'])->assertForbidden();

        // Manager approves.
        $this->actingAs($manager)->post(route('ipcr.decide', $form), ['decision' => 'approve'])->assertRedirect();
        $form->refresh();
        $this->assertSame('approved', $form->status);
        $this->assertSame($manager->id, $form->approved_by_id);
    }

    public function test_scanned_copy_only_after_approval(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $ratee = $this->employee();
        $form = IpcrForm::create(['user_id' => $ratee->id, 'year' => 2026,
            'semester' => 1, 'status' => 'draft']);
        $file = \Illuminate\Http\UploadedFile::fake()->create('ipcr.pdf', 100, 'application/pdf');

        // Not yet approved → blocked.
        $this->actingAs($ratee)->post(route('ipcr.scan.store', $form), ['scan' => $file])->assertForbidden();

        $form->update(['status' => 'approved']);
        $this->actingAs($ratee)->post(route('ipcr.scan.store', $form), ['scan' => $file])->assertRedirect();

        $form->refresh();
        $this->assertNotNull($form->scanned_copy_path);
        \Illuminate\Support\Facades\Storage::assertExists($form->scanned_copy_path);
    }

    public function test_only_one_ipcr_per_person_per_semester(): void
    {
        $manager = $this->manager();
        $ratee = $this->employee();

        $this->actingAs($manager)
            ->post(route('ipcr.store'), $this->payload($ratee->id, 2026, 1))
            ->assertRedirect();

        // Same person, same semester → rejected with a readable message.
        $this->actingAs($manager)
            ->post(route('ipcr.store'), $this->payload($ratee->id, 2026, 1))
            ->assertSessionHasErrors('semester');

        // The other semester of the same year is fine — two a year, never more.
        $this->actingAs($manager)
            ->post(route('ipcr.store'), $this->payload($ratee->id, 2026, 2))
            ->assertRedirect();

        $this->assertSame(2, IpcrForm::where('user_id', $ratee->id)->count());
    }

    public function test_submitted_forms_land_in_the_pending_queue(): void
    {
        $manager = $this->manager();
        $ratee = $this->employee();

        $draft = IpcrForm::create([
            'user_id' => $ratee->id, 'year' => 2026, 'semester' => 1, 'status' => 'draft',
        ]);
        $waiting = IpcrForm::create([
            'user_id' => $ratee->id, 'year' => 2026, 'semester' => 2, 'status' => IpcrForm::SUBMITTED,
        ]);

        // Default view for a manager is the pending queue.
        $this->actingAs($manager)->get(route('ipcr.index'))
            ->assertInertia(fn ($page) => $page
                ->where('filter', 'pending')
                ->where('pendingCount', 1)
                ->has('forms', 1)
                ->where('forms.0.id', $waiting->id));

        // "All records" shows both.
        $this->actingAs($manager)->get(route('ipcr.index', ['filter' => 'all']))
            ->assertInertia(fn ($page) => $page->has('forms', 2));

        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_approval_ticks_the_semester_compliance_flag(): void
    {
        $manager = $this->manager();
        $employee = \App\Models\Employee::create([
            'emp_no' => '9001', 'first_name' => 'Test', 'last_name' => 'Ratee',
        ]);
        $ratee = $this->employee();
        $ratee->update(['employee_id' => $employee->id]);

        $form = IpcrForm::create([
            'user_id' => $ratee->id, 'year' => 2026, 'semester' => 2, 'status' => IpcrForm::SUBMITTED,
        ]);

        $this->actingAs($manager)
            ->post(route('ipcr.decide', $form), ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertDatabaseHas('ipcr_records', [
            'employee_id' => $employee->id,
            'year' => 2026,
            'sem2_done' => true,
        ]);

        // An approved IPCR is final — it can no longer be returned.
        $this->actingAs($manager)
            ->post(route('ipcr.decide', $form->fresh()), ['decision' => 'return'])
            ->assertForbidden();

        // Deleting it is how a mistake is undone, and that takes the tick back.
        $this->actingAs($manager)->delete(route('ipcr.destroy', $form))->assertRedirect();

        $this->assertDatabaseHas('ipcr_records', [
            'employee_id' => $employee->id,
            'year' => 2026,
            'sem2_done' => false,
        ]);
    }
}
