<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LdEntry;
use App\Models\LeaveApplication;
use App\Models\LeaveCreditEntry;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Support\LdTarget;
use App\Support\LeaveWorkflow;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
        // Freeze mid-month so accrual timing is deterministic (the running
        // month has not closed, so only earlier months have accrued).
        Carbon::setTestNow('2026-08-17');
        $this->seed(RoleSeeder::class);
        $this->seed(LeaveTypeSeeder::class);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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
            'credits_accrual_start' => now()->subMonthNoOverflow()->startOfMonth(),
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

    public function test_ld_records_without_proof_so_the_paper_can_follow(): void
    {
        $this->actingAs($this->employeeUser())->post(route('ld.store'), [
            'title' => 'Seminar', 'hours' => 4, 'date' => '2026-07-10',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $entry = LdEntry::firstOrFail();
        $this->assertNull($entry->certificate_path);
        $this->assertNull($entry->photo_path);
        $this->assertSame(LdEntry::PENDING, $entry->status);
    }

    public function test_a_training_is_filed_with_its_competency_and_date_range(): void
    {
        $user = $this->employeeUser();

        $this->actingAs($user)->post(route('ld.store'), [
            'competency' => 'supervisory',
            'title'   => 'Supervisory Development Course Track 1',
            'date'    => '2026-07-29',
            'date_to' => '2026-08-03',
            'hours'   => 24,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $entry = LdEntry::firstOrFail();
        $this->assertSame('supervisory', $entry->competency);
        $this->assertSame('Supervisory Development Course Track 1', $entry->title);
        $this->assertSame('29 July - 03 August 2026', $entry->inclusive_dates_text);
    }

    public function test_hours_left_blank_count_as_one(): void
    {
        $this->actingAs($this->employeeUser())->post(route('ld.store'), [
            'title' => 'Flag raising lecture on RA 6713', 'date' => '2026-07-10',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEquals(1.0, (float) LdEntry::firstOrFail()->hours);
    }

    public function test_an_unknown_competency_is_refused(): void
    {
        $this->actingAs($this->employeeUser())->post(route('ld.store'), [
            'competency' => 'whatever', 'title' => 'Seminar', 'date' => '2026-07-10',
        ])->assertSessionHasErrors('competency');

        $this->assertSame(0, LdEntry::count());
    }

    public function test_the_employee_sees_their_own_ld_page_with_their_standing(): void
    {
        $user = $this->employeeUser();

        $this->actingAs($user)->post(route('ld.store'), [
            'competency' => 'technical', 'title' => 'Records Management Seminar',
            'date' => '2026-07-10', 'hours' => 8,
        ])->assertRedirect();
        $this->actingAs($this->adminUser())
            ->patch(route('ld.decide', LdEntry::firstOrFail()), ['decision' => 'approved'])
            ->assertRedirect();

        $this->actingAs($user)->get(route('ld.mine', ['year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Ld/Mine')
                ->where('summary.hours', 8)
                ->where('summary.met', true)
                ->where('entries.0.title', 'Records Management Seminar')
                ->where('entries.0.competency', 'Technical')
                ->where('entries.0.dates', '10 July 2026'));
    }

    public function test_an_employee_corrects_their_own_entry_and_it_goes_back_to_pending(): void
    {
        $user = $this->employeeUser();

        $this->actingAs($user)->post(route('ld.store'), [
            'competency' => 'technical', 'title' => 'Dat Analytcs', 'date' => '2026-07-10', 'hours' => 8,
        ])->assertRedirect();
        $entry = LdEntry::firstOrFail();
        $this->actingAs($this->adminUser())
            ->patch(route('ld.decide', $entry), ['decision' => 'approved'])->assertRedirect();

        $this->actingAs($user)->post(route('ld.update', $entry), [
            'competency' => 'managerial', 'title' => 'Data Analytics Essentials',
            'date' => '2026-07-10', 'date_to' => '2026-07-11', 'hours' => 16,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $entry->refresh();
        $this->assertSame('Data Analytics Essentials', $entry->title);
        $this->assertSame('managerial', $entry->competency);
        $this->assertEquals(16.0, (float) $entry->hours);
        // The hours counted while it was approved, so an edit re-queues it.
        $this->assertSame(LdEntry::PENDING, $entry->status);
        $this->assertNull($entry->decided_at);
    }

    public function test_an_employee_removes_their_own_duplicate_but_not_someone_elses(): void
    {
        $owner = $this->employeeUser();
        $this->actingAs($owner)->post(route('ld.store'), [
            'title' => 'Seminar', 'date' => '2026-07-10', 'hours' => 4,
        ])->assertRedirect();
        $entry = LdEntry::firstOrFail();

        $other = $this->userWithRoles(['employee'], ['emp_no' => '9001', 'first_name' => 'Nosy', 'last_name' => 'Neighbour']);
        $this->actingAs($other)->delete(route('ld.destroy', $entry))->assertForbidden();
        $this->actingAs($other)->post(route('ld.update', $entry), [
            'title' => 'Hijacked', 'date' => '2026-07-10',
        ])->assertForbidden();
        $this->assertSame(1, LdEntry::count());

        $this->actingAs($owner)->delete(route('ld.destroy', $entry))->assertRedirect();
        $this->assertSame(0, LdEntry::count());
    }

    public function test_an_admin_removes_a_record_entered_for_the_wrong_person(): void
    {
        $entry = $this->approvedTraining($this->employeeUser());

        $this->actingAs($this->adminUser())->delete(route('ld.destroy', $entry))->assertRedirect();
        $this->assertModelMissing($entry);
    }

    // ── Local career courses & seminars ──────────────────────────────

    private function fileCourse(User $user, array $over = []): LdEntry
    {
        $this->actingAs($user)->post(route('ld.store'), $over + [
            'kind' => LdEntry::COURSE,
            'title' => 'Civilian Human Resource Supervisory Course',
            'batch' => '2026-01',
            'conducted_by' => 'PAFCHRS',
            'date' => '2026-07-01', 'date_to' => '2026-07-31',
        ])->assertSessionHasNoErrors()->assertRedirect();

        return LdEntry::latest('id')->firstOrFail();
    }

    public function test_an_employee_files_a_career_course_and_it_waits_for_approval(): void
    {
        $user = $this->employeeUser();
        $course = $this->fileCourse($user);

        $this->assertSame(LdEntry::COURSE, $course->kind);
        $this->assertSame(LdEntry::PENDING, $course->status);
        $this->assertSame('2026-01', $course->batch);
        $this->assertSame('PAFCHRS', $course->conducted_by);

        $this->actingAs($this->adminUser())
            ->patch(route('ld.decide', $course), ['decision' => 'approved'])->assertRedirect();

        // It lists under courses, never under trainings, and adds no hours.
        $this->actingAs($user)->get(route('ld.mine', ['year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.hours', 0)
                ->has('entries', 0)
                ->where('courses.0.title', 'Civilian Human Resource Supervisory Course')
                ->where('courses.0.dates', '01-31 July 2026')
                ->where('courses.0.status', LdEntry::APPROVED));
    }

    public function test_editing_a_course_keeps_it_a_course_and_requeues_it(): void
    {
        $user = $this->employeeUser();
        $course = $this->fileCourse($user);
        $this->actingAs($this->adminUser())
            ->patch(route('ld.decide', $course), ['decision' => 'approved'])->assertRedirect();

        $this->actingAs($user)->post(route('ld.update', $course), [
            'title' => 'Civilian HR Supervisory Course', 'batch' => '2026-02',
            'conducted_by' => 'PAFCHRS', 'date' => '2026-07-01',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $course->refresh();
        $this->assertSame(LdEntry::COURSE, $course->kind);
        $this->assertSame('2026-02', $course->batch);
        $this->assertSame(LdEntry::PENDING, $course->status);
    }

    public function test_table_2_counts_approved_graduates_per_course_batch(): void
    {
        $justin = $this->employeeUser();
        $jean = $this->userWithRoles(['employee'], ['emp_no' => '5038', 'first_name' => 'Jean', 'last_name' => 'Tubat']);
        $admin = $this->adminUser();

        $helo = ['title' => 'Helicopter Mechanic Apprentice Course', 'batch' => '2026-01',
            'conducted_by' => '19th CTTS, 15th SW', 'date' => '2026-02-24', 'date_to' => '2026-07-28'];

        foreach ([$this->fileCourse($justin, $helo), $this->fileCourse($jean, $helo), $this->fileCourse($jean)] as $c) {
            $this->actingAs($admin)->patch(route('ld.decide', $c), ['decision' => 'approved']);
        }
        // Still pending: not on the report.
        $this->fileCourse($justin, ['title' => 'Not Yet Approved Seminar']);

        // Outside the PAF: Table 3, not Table 2.
        $outside = $this->fileCourse($jean, [
            'title' => 'Aviation Safety Course', 'conducted_by' => 'FAA', 'outside_paf' => '1',
        ]);
        $this->actingAs($admin)->patch(route('ld.decide', $outside), ['decision' => 'approved']);

        $t2 = $this->reportTable($admin, 't2');
        $this->assertSame('Table 2. Civilian Human Resources Sent to Local Career Courses and Seminars', $t2['title']);
        $this->assertCount(2, $t2['rows']);
        $this->assertSame([
            'title' => 'Helicopter Mechanic Apprentice Course', 'batch' => '2026-01',
            'dates' => '24 February - 28 July 2026', 'graduates' => 2, 'conducted_by' => '19th CTTS, 15th SW',
        ], $t2['rows'][0]['cells']);
        $this->assertSame(1, $t2['rows'][1]['cells']['graduates']);
        $this->assertEquals(3, $t2['total']['graduates']);
        $this->assertNotContains('Not Yet Approved Seminar', array_column(array_column($t2['rows'], 'cells'), 'title'));

        $t3 = $this->reportTable($admin, 't3');
        $this->assertSame(['Aviation Safety Course'], array_column(array_column($t3['rows'], 'cells'), 'title'));

        $this->actingAs($justin)->get(route('ld.reports'))->assertForbidden();
    }

    // ── The L&D accomplishment report ─────────────────────────────────

    private function reportTable(User $admin, string $report, array $query = ['year' => 2026]): array
    {
        $tables = $this->actingAs($admin)->get(route('ld.reports', $query))->assertOk()
            ->viewData('page')['props']['tables'];

        return collect($tables)->firstWhere('report', $report);
    }

    private function approvedTraining(User $user, array $over = []): LdEntry
    {
        $this->actingAs($user)->post(route('ld.store'), $over + [
            'competency' => 'technical', 'title' => 'Records Management Seminar',
            'date' => '2026-07-10', 'hours' => 8,
        ])->assertSessionHasNoErrors();
        $entry = LdEntry::latest('id')->firstOrFail();
        $this->actingAs($this->adminUser())->patch(route('ld.decide', $entry), ['decision' => 'approved']);

        return $entry;
    }

    public function test_the_report_tables_follow_the_approved_trainings(): void
    {
        $justin = $this->employeeUser();
        $admin = $this->adminUser();
        $vawc = ['competency' => 'foundational', 'title' => 'VAWC Seminar', 'date' => '2026-03-04', 'hours' => 4];

        $this->approvedTraining($justin, $vawc);
        $this->approvedTraining($admin, $vawc);
        $this->approvedTraining($justin, ['date' => '2026-07-10', 'hours' => 10]);
        // Pending: counts nowhere.
        $this->actingAs($justin)->post(route('ld.store'), ['title' => 'Pending', 'date' => '2026-07-11', 'hours' => 50]);

        $t1 = $this->reportTable($admin, 't1');
        $this->assertSame(['Foundational', 'Technical'], array_column(array_column($t1['rows'], 'cells'), 'competency'));
        $this->assertSame(['VAWC Seminar', '04 March 2026', 4.0, 2], [
            $t1['rows'][0]['cells']['title'], $t1['rows'][0]['cells']['dates'],
            $t1['rows'][0]['cells']['hours'], $t1['rows'][0]['cells']['participants'],
        ]);
        $this->assertEquals(14, $t1['total']['hours']);
        $this->assertEquals(3, $t1['total']['participants']);

        // Both are 1st level (8h target each). Justin's 14h give 8 toward his
        // target and 6 extra; Marie's 4h all count.
        $first = collect($this->reportTable($admin, 't4')['rows'])->firstWhere('key', 'first');
        $this->assertSame(2, $first['cells']['filled']);
        $this->assertEquals(16, $first['cells']['target']);
        $this->assertEquals(12, $first['cells']['actual']);
        $this->assertEquals(6, $first['cells']['excess']);
        $this->assertSame('75%', $first['cells']['pct']);

        $mon = collect($this->reportTable($admin, 'monitoring')['rows'])->firstWhere('cells.name', 'Bercades, Justin');
        $this->assertEquals(4, $mon['cells']['mar']);
        $this->assertEquals(10, $mon['cells']['jul']);
        $this->assertEquals(14, $mon['cells']['total']);
        $this->assertSame('YES', $mon['cells']['complied']);
        $this->assertEquals(6, $mon['cells']['additional']);

        // "As of Q1" leaves July out.
        $q1 = collect($this->reportTable($admin, 'monitoring', ['year' => 2026, 'quarter' => 1])['rows'])
            ->firstWhere('cells.name', 'Bercades, Justin');
        $this->assertEquals(4, $q1['cells']['total']);
    }

    public function test_a_hand_edit_overrides_one_cell_and_the_totals_follow(): void
    {
        $admin = $this->adminUser();
        $this->approvedTraining($this->employeeUser());

        $row = $this->reportTable($admin, 't1')['rows'][0];
        $cell = ['year' => 2026, 'report' => 't1', 'row_key' => $row['key'], 'col' => 'hours'];

        $this->actingAs($admin)->post(route('ld.reports.cell'), $cell + ['value' => '830'])->assertRedirect();
        $t1 = $this->reportTable($admin, 't1');
        $this->assertSame('830', $t1['rows'][0]['cells']['hours']);
        $this->assertEquals(8, $t1['rows'][0]['edited']['hours']);   // the live value, kept for reset
        $this->assertEquals(830, $t1['total']['hours']);

        // Only that period carries the edit.
        $this->assertEquals(8, $this->reportTable($admin, 't1', ['year' => 2026, 'quarter' => 3])['rows'][0]['cells']['hours']);

        $this->actingAs($admin)->delete(route('ld.reports.reset'), $cell)->assertRedirect();
        $this->assertEquals(8, $this->reportTable($admin, 't1')['rows'][0]['cells']['hours']);

        // Derived columns are not editable.
        $this->actingAs($admin)->post(route('ld.reports.cell'), [
            'year' => 2026, 'report' => 't4', 'row_key' => 'first', 'col' => 'pct', 'value' => '999%',
        ])->assertStatus(422);
    }

    public function test_table_5_percentages_use_the_typed_targets(): void
    {
        $admin = $this->adminUser();
        $this->approvedTraining($this->employeeUser());

        $this->actingAs($admin)->post(route('ld.reports.cell'), [
            'year' => 2026, 'report' => 't5', 'row_key' => 'technical', 'col' => 'adj_course', 'value' => '2',
        ])->assertRedirect();

        $tech = collect($this->reportTable($admin, 't5')['rows'])->firstWhere('key', 'technical');
        $this->assertSame(1, $tech['cells']['actual_course']);
        $this->assertSame('50%', $tech['cells']['pct_course']);
        $this->assertSame('-', $tech['cells']['pct_pax']);
    }

    public function test_an_admin_records_one_training_for_several_people_approved(): void
    {
        $justin = $this->employeeUser();
        $jean = $this->userWithRoles(['employee'], ['emp_no' => '5038', 'first_name' => 'Jean', 'last_name' => 'Tubat']);
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('ld.record'), [
            'kind' => 'training', 'competency' => 'foundational', 'title' => 'VAWC Seminar',
            'date' => '2026-03-04', 'hours' => 4,
            'employees' => [$justin->employee_id, $jean->employee_id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $rows = LdEntry::where('title', 'VAWC Seminar')->get();
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->every(fn ($l) => $l->status === LdEntry::APPROVED && $l->decided_by === $admin->id));

        // It counts straight away, on the report and on each person's row.
        $this->assertSame(2, $this->reportTable($admin, 't1')['rows'][0]['cells']['participants']);
        $this->actingAs($admin)->get(route('ld.index', ['year' => 2026]))
            ->assertInertia(fn ($page) => $page->where('summary.hours', 8));

        // Employees cannot record for others.
        $this->actingAs($justin)->post(route('ld.record'), [
            'title' => 'X', 'date' => '2026-03-04', 'employees' => [$jean->employee_id],
        ])->assertForbidden();
    }

    public function test_the_report_downloads_as_an_excel_workbook(): void
    {
        $admin = $this->adminUser();
        $this->approvedTraining($this->employeeUser());

        $response = $this->actingAs($admin)->get(route('ld.reports.export', ['year' => 2026]))->assertOk();
        $file = $response->baseResponse->getFile()->getPathname();

        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $this->assertSame(['Table 1', 'Table 2', 'Table 3', 'Table 4', 'Table 5', 'L&D Monitoring'], $book->getSheetNames());
        $sheet = $book->getSheetByName('Table 1');
        $this->assertSame('Table 1. Conducted Unit Training/Seminars and L&D Intervention', $sheet->getCell('A1')->getValue());
        $this->assertSame('Records Management Seminar', $sheet->getCell('B5')->getValue());
        $this->assertEquals(8, $sheet->getCell('D5')->getValue());
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

        // Employee has 1.25 VL from last month's accrual. Set it to 15.
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

    // ── L&D roster & salary-grade targets ─────────────────────────────

    public function test_the_ld_target_follows_salary_grade(): void
    {
        $clerk = Employee::create(['emp_no' => '9101', 'first_name' => 'A', 'last_name' => 'B', 'salary_grade' => 14]);
        $chief = Employee::create(['emp_no' => '9102', 'first_name' => 'C', 'last_name' => 'D', 'salary_grade' => 15]);
        $noSg  = Employee::create(['emp_no' => '9103', 'first_name' => 'E', 'last_name' => 'F']);

        $this->assertEquals(8.0, LdTarget::hoursFor($clerk));   // SG 1–14
        $this->assertEquals(40.0, LdTarget::hoursFor($chief));  // SG 15+
        $this->assertEquals(8.0, LdTarget::hoursFor($noSg));    // unset → rank-and-file
    }

    public function test_the_ld_roster_reports_targets_and_is_admin_only(): void
    {
        $admin = $this->adminUser();

        $clerk = $this->userWithRoles(['employee'], ['emp_no' => '9001', 'first_name' => 'Rank', 'last_name' => 'File', 'salary_grade' => 11])->employee;
        $this->userWithRoles(['employee'], ['emp_no' => '9002', 'first_name' => 'Big', 'last_name' => 'Boss', 'salary_grade' => 18]);

        // The clerk logs exactly the 8h their grade requires → met.
        $clerk->ldEntries()->create([
            'title'  => 'Records seminar',
            'hours'  => 8,
            'date'   => now()->toDateString(),
            'status' => LdEntry::APPROVED,
        ]);

        $this->actingAs($admin)->get(route('ld.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Ld/Index')
                ->where('rows', function ($rows) {
                    $rows = collect($rows);
                    $clerk = $rows->firstWhere('emp_no', '9001');
                    $chief = $rows->firstWhere('emp_no', '9002');

                    return (float) $clerk['target'] === 8.0 && $clerk['met'] === true
                        && (float) $chief['target'] === 40.0 && $chief['met'] === false;
                })
                ->etc());

        // The roster is admin-only.
        $this->actingAs($this->employeeUser())->get(route('ld.index'))->assertForbidden();
    }

    public function test_the_ld_roster_excludes_deactivated_and_account_less_employees(): void
    {
        $admin = $this->adminUser();

        // On the roster: an active login.
        $this->userWithRoles(['employee'], ['emp_no' => '9001', 'first_name' => 'Still', 'last_name' => 'Here']);

        // A deactivated login — the person has left.
        $gone = $this->userWithRoles(['employee'], ['emp_no' => '9002', 'first_name' => 'Long', 'last_name' => 'Gone']);
        $gone->update(['is_active' => false]);

        // A record with no login at all (e.g. a signatory or pre-go-live entry).
        Employee::create(['emp_no' => '9003', 'first_name' => 'No', 'last_name' => 'Login']);

        $this->actingAs($admin)->get(route('ld.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('rows', function ($rows) {
                    $nos = collect($rows)->pluck('emp_no');

                    return $nos->contains('9001')
                        && ! $nos->contains('9002')
                        && ! $nos->contains('9003');
                })
                ->etc());
    }
}
