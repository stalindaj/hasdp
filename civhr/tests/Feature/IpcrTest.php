<?php

namespace Tests\Feature;

use App\Models\IpcrForm;
use App\Models\Role;
use App\Models\User;
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

    private function payload(int $rateeId): array
    {
        return [
            'user_id' => $rateeId,
            'rating_period' => 'January – June 2026',
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

    public function test_ratee_only_sees_their_own_forms(): void
    {
        $manager = $this->manager();
        $ratee = $this->employee();
        $other = $this->employee();

        $mine = IpcrForm::create(['user_id' => $ratee->id, 'rating_period' => 'A', 'status' => 'draft']);
        $theirs = IpcrForm::create(['user_id' => $other->id, 'rating_period' => 'B', 'status' => 'draft']);

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

    public function test_ratee_cannot_delete(): void
    {
        $ratee = $this->employee();
        $form = IpcrForm::create(['user_id' => $ratee->id, 'rating_period' => 'A', 'status' => 'draft']);

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
            'rating_period' => 'A',
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
        $form = IpcrForm::create(['user_id' => $ratee->id, 'rating_period' => 'A', 'status' => 'draft']);
        $file = \Illuminate\Http\UploadedFile::fake()->create('ipcr.pdf', 100, 'application/pdf');

        // Not yet approved → blocked.
        $this->actingAs($ratee)->post(route('ipcr.scan.store', $form), ['scan' => $file])->assertForbidden();

        $form->update(['status' => 'approved']);
        $this->actingAs($ratee)->post(route('ipcr.scan.store', $form), ['scan' => $file])->assertRedirect();

        $form->refresh();
        $this->assertNotNull($form->scanned_copy_path);
        \Illuminate\Support\Facades\Storage::assertExists($form->scanned_copy_path);
    }
}
