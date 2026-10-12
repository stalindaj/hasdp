<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LdEntry;
use App\Support\LdTarget;
use App\Support\LeaveWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Learning & Development submissions.
 *
 * The employee files a training from their own L&D page — the competency it
 * builds, its title, the dates it ran and the hours — and attaches the
 * certificate and/or a photo taken during it. Proof is asked for but not
 * required: a training nobody photographed is still a training, so it can be
 * recorded and the paper brought later. An admin approves or rejects it, and
 * only approved hours count toward the yearly target.
 *
 * Local career courses and seminars are filed and approved the same way
 * (kind = career_course) but kept apart: they carry a batch and who conducted
 * them instead of hours, never count toward the target, and are reported on
 * their own on the L&D report (Tables 2 and 3 of App\Support\LdReport).
 *
 * Proof images hold personal data, so they are stored on the private disk and
 * served only to the owner and admins through the file route below — never as
 * public files.
 */
class LdController extends Controller
{
    /**
     * Admin overview: every employee's L&D standing for a year, their
     * salary-grade target (8h for SG 1–14, 40h for SG 15+), and the queue of
     * submissions still awaiting a decision. Read-only roster — approvals and
     * logging a training for someone happen from the queue and the employee
     * card respectively.
     */
    public function index(Request $request)
    {
        abort_unless(LeaveWorkflow::isAdmin($request->user()), 403);

        $year = (int) $request->integer('year', now()->year);

        // Only people who are active in the system: they must have a login and
        // it must be switched on. This drops both deactivated accounts and
        // record-only entries with no login (separated staff, signatories),
        // which the plain active() scope would otherwise leave on the roster.
        $employees = Employee::query()
            ->whereHas('user', fn ($u) => $u->where('is_active', true))
            ->whereNotNull('emp_no')
            ->where('emp_no', '!=', 'mission')
            ->with([
                'ldEntries' => fn ($q) => $q->whereYear('date', $year)->orderByDesc('date'),
                'careerCourses' => fn ($q) => $q->whereYear('date', $year)->orderByDesc('date'),
            ])
            ->orderBy('last_name')
            ->get();

        $supervisorSg = (int) config('agency.ld_supervisor_sg_min');

        // What each filing looks like when a row is opened on the roster.
        $item = fn (LdEntry $l) => [
            'id'     => $l->id,
            'title'  => $l->title,
            'detail' => $l->isCourse()
                ? trim(implode(' · ', array_filter([$l->batch ? 'Batch '.$l->batch : null, $l->conducted_by, $l->outside_paf ? 'Outside PAF' : null])))
                : $l->competencyLabel(),
            'dates'  => $l->inclusive_dates_text,
            'hours'  => $l->isCourse() ? null : (float) $l->hours,
            'status' => $l->status,
        ];

        $rows = $employees->map(function ($e) use ($supervisorSg, $item) {
            $hours = (float) $e->ldEntries->where('status', LdEntry::APPROVED)->sum('hours');
            $target = LdTarget::hoursFor($e);

            return [
                'id'        => $e->id,
                'emp_no'    => $e->emp_no,
                'name'      => trim($e->last_name.', '.$e->first_name),
                'sg'        => $e->salary_grade,
                'level'     => (int) $e->salary_grade >= $supervisorSg ? '2nd' : '1st',
                'target'    => $target,
                'hours'     => round($hours, 1),
                'remaining' => round(max(0, $target - $hours), 1),
                'met'       => $hours >= $target,
                'pending'   => $e->ldEntries->where('status', LdEntry::PENDING)->count()
                    + $e->careerCourses->where('status', LdEntry::PENDING)->count(),
                'trainings' => $e->ldEntries->map($item)->values(),
                'courses'   => $e->careerCourses->map($item)->values(),
            ];
        })->values();

        // The approval queue — every pending submission, oldest first.
        $pending = LdEntry::with('employee:id,first_name,last_name')
            ->where('status', LdEntry::PENDING)
            ->oldest()
            ->get()
            ->map(fn ($l) => [
                'id'       => $l->id,
                'employee' => trim($l->employee?->first_name.' '.$l->employee?->last_name),
                'kind'     => $l->kind,
                'title'    => $l->title,
                'batch'    => $l->batch,
                'conducted_by' => $l->conducted_by,
                'competency' => $l->competencyLabel(),
                'hours'    => (float) $l->hours,
                'date'     => $l->inclusive_dates_text,
                'certificate' => $l->certificate_path ? route('ld.file', [$l, 'certificate']) : null,
                'photo'       => $l->photo_path ? route('ld.file', [$l, 'photo']) : null,
            ]);

        // Years that have any activity, so the switcher only offers real ones.
        $years = LdEntry::pluck('date')
            ->map(fn ($d) => (int) Carbon::parse($d)->year)
            ->push(now()->year)
            ->push($year)
            ->unique()
            ->sortDesc()
            ->values();

        return Inertia::render('Ld/Index', [
            'year'  => $year,
            'years' => $years,
            'rows'  => $rows,
            'pending' => $pending,
            'competencies' => collect(LdEntry::COMPETENCIES)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                ->values(),
            'summary' => [
                'total'   => $rows->count(),
                'met'     => $rows->where('met', true)->count(),
                'behind'  => $rows->where('met', false)->count(),
                'pending' => $pending->count(),
                'hours'   => round($rows->sum('hours'), 1),
                'courses' => $employees->sum(fn ($e) => $e->careerCourses->where('status', LdEntry::APPROVED)->count()),
            ],
        ]);
    }

    /**
     * The employee's own L&D page: what they have filed for a year, where it
     * stands against their salary-grade target, and the form to file more.
     */
    public function mine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee record.');

        $year = (int) $request->integer('year', now()->year);

        $entries = $employee->ldEntries()
            ->whereYear('date', $year)
            ->orderByDesc('date')
            ->get();

        $approved = (float) $entries->where('status', LdEntry::APPROVED)->sum('hours');
        $target = LdTarget::hoursFor($employee);

        // Hours per track, so the page shows the spread and not just a total.
        $byCompetency = collect(LdEntry::COMPETENCIES)
            ->map(fn ($label, $key) => [
                'key'   => $key,
                'label' => $label,
                'hours' => round((float) $entries->where('status', LdEntry::APPROVED)
                    ->where('competency', $key)->sum('hours'), 1),
            ])
            ->values();

        $courses = $employee->careerCourses()
            ->whereYear('date', $year)
            ->orderByDesc('date')
            ->get();

        $years = LdEntry::where('employee_id', $employee->id)->pluck('date')
            ->map(fn ($d) => (int) Carbon::parse($d)->year)
            ->push(now()->year)
            ->push($year)
            ->unique()
            ->sortDesc()
            ->values();

        return Inertia::render('Ld/Mine', [
            'year'  => $year,
            'years' => $years,
            'competencies' => collect(LdEntry::COMPETENCIES)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
                ->values(),
            'summary' => [
                'target'    => $target,
                'hours'     => round($approved, 1),
                'remaining' => round(max(0, $target - $approved), 1),
                'met'       => $approved >= $target,
                'pending'   => round((float) $entries->where('status', LdEntry::PENDING)->sum('hours'), 1),
                'by_competency' => $byCompetency,
            ],
            'entries' => $entries->map(fn (LdEntry $l) => [
                'id'         => $l->id,
                'competency' => $l->competencyLabel(),
                'competency_key' => $l->competency,
                'title'      => $l->title,
                'dates'      => $l->inclusive_dates_text,
                'date'       => optional($l->date)->toDateString(),
                'date_to'    => optional($l->date_to)->toDateString(),
                'hours'      => (float) $l->hours,
                'status'     => $l->status,
                'remarks'    => $l->remarks,
                'certificate' => $l->certificate_path ? route('ld.file', [$l, 'certificate']) : null,
                'photo'       => $l->photo_path ? route('ld.file', [$l, 'photo']) : null,
            ])->values(),
            'courses' => $courses->map(fn (LdEntry $l) => [
                'id'           => $l->id,
                'title'        => $l->title,
                'batch'        => $l->batch,
                'conducted_by' => $l->conducted_by,
                'outside_paf'  => (bool) $l->outside_paf,
                'dates'        => $l->inclusive_dates_text,
                'date'         => optional($l->date)->toDateString(),
                'date_to'      => optional($l->date_to)->toDateString(),
                'status'       => $l->status,
                'remarks'      => $l->remarks,
                'certificate'  => $l->certificate_path ? route('ld.file', [$l, 'certificate']) : null,
                'photo'        => $l->photo_path ? route('ld.file', [$l, 'photo']) : null,
            ])->values(),
        ]);
    }

    /**
     * An admin records a training or career course for any number of people
     * at once — a unit seminar everyone attended, say — so the records match
     * what the report needs. The admin is the approver, so each row is
     * approved on the spot.
     */
    public function storeFor(Request $request)
    {
        abort_unless(LeaveWorkflow::isAdmin($request->user()), 403);

        $course = $request->input('kind') === LdEntry::COURSE;
        $data = $this->validatedEntry($request, $course);
        $people = $request->validate([
            'employees'   => ['required', 'array', 'min:1'],
            'employees.*' => ['integer', 'exists:employees,id'],
        ])['employees'];

        foreach (array_unique($people) as $employeeId) {
            LdEntry::create([
                'employee_id' => $employeeId,
                'kind'        => $course ? LdEntry::COURSE : LdEntry::TRAINING,
                ...$this->fields($data, $course),
                'status'      => LdEntry::APPROVED,
                'submitted_by' => $request->user()->id,
                'decided_by'  => $request->user()->id,
                'decided_at'  => now(),
            ]);
        }

        $n = count(array_unique($people));

        return back()->with('success', ($course ? 'Course' : 'Training')." recorded for {$n} ".($n === 1 ? 'person' : 'people').'.');
    }

    /** Employee submits a training (or a career course) for approval. */
    public function store(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee record.');

        $course = $request->input('kind') === LdEntry::COURSE;
        $data = $this->validatedEntry($request, $course);

        $dir = "ld/{$employee->id}";

        LdEntry::create([
            'employee_id' => $employee->id,
            'kind'    => $course ? LdEntry::COURSE : LdEntry::TRAINING,
            ...$this->fields($data, $course),
            'status'  => LdEntry::PENDING,
            'certificate_path' => $request->file('certificate')?->store($dir),
            'photo_path'       => $request->file('photo')?->store($dir),
            'submitted_by'     => $request->user()->id,
        ]);

        return back()->with('success', $course
            ? 'Course submitted. It goes on the report once an admin approves it.'
            : 'Training submitted. The hours will count once an admin approves it.');
    }

    /**
     * The employee corrects one of their own entries. Any change puts it back
     * in the queue: an approved row's hours already count toward the target,
     * so they must not be editable behind the approver's back. Proof files
     * are only touched when a new one is attached.
     */
    public function update(Request $request, LdEntry $entry)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && (int) $entry->employee_id === (int) $employee->id, 403);

        // A row stays what it was filed as; only its details change.
        $course = $entry->isCourse();
        $data = $this->validatedEntry($request, $course);

        $dir = "ld/{$employee->id}";

        foreach (['certificate', 'photo'] as $kind) {
            if ($file = $request->file($kind)) {
                $old = $entry->{$kind.'_path'};
                $entry->{$kind.'_path'} = $file->store($dir);
                if ($old) {
                    Storage::delete($old);
                }
            }
        }

        $entry->fill([
            ...$this->fields($data, $course),
            'status'  => LdEntry::PENDING,
            'remarks' => null,
            'decided_by' => null,
            'decided_at' => null,
        ])->save();

        return back()->with('success', ($course ? 'Course' : 'Training').' updated — it goes back to the approver.');
    }

    /**
     * The employee removes one of their own entries (a duplicate, say); an
     * admin may remove anyone's — e.g. a record entered for the wrong person.
     */
    public function destroy(Request $request, LdEntry $entry)
    {
        $employee = $request->user()->employee;
        $isOwner = $employee && (int) $entry->employee_id === (int) $employee->id;
        abort_unless($isOwner || LeaveWorkflow::isAdmin($request->user()), 403);

        foreach ([$entry->certificate_path, $entry->photo_path] as $path) {
            if ($path) {
                Storage::delete($path);
            }
        }

        $entry->delete();

        return back()->with('success', 'Training removed.');
    }

    /** Admin approves or rejects a submission (re-decidable any time). */
    public function decide(Request $request, LdEntry $entry)
    {
        abort_unless(LeaveWorkflow::isAdmin($request->user()), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in([LdEntry::APPROVED, LdEntry::REJECTED])],
            'remarks'  => [Rule::requiredIf($request->input('decision') === LdEntry::REJECTED), 'nullable', 'string', 'max:255'],
        ]);

        $entry->update([
            'status'     => $data['decision'],
            'remarks'    => $data['decision'] === LdEntry::REJECTED ? $data['remarks'] : null,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return back()->with('success', $data['decision'] === LdEntry::APPROVED
            ? 'L&D approved — the hours now count.'
            : 'L&D rejected.');
    }

    /** What an employee may say about a training or course, filing or correcting it. */
    private function validatedEntry(Request $request, bool $course = false): array
    {
        $proof = [
            // Proof is asked for, never required: it can follow later.
            'certificate' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photo'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];

        if ($course) {
            return $request->validate([
                'title'        => ['required', 'string', 'max:255'],
                'batch'        => ['nullable', 'string', 'max:50'],
                'conducted_by' => ['nullable', 'string', 'max:191'],
                'outside_paf'  => ['nullable', 'boolean'],
                'date'         => ['required', 'date', 'before_or_equal:today'],
                'date_to'      => ['nullable', 'date', 'after_or_equal:date', 'before_or_equal:today'],
            ] + $proof);
        }

        return $request->validate([
            'competency' => ['nullable', Rule::in(array_keys(LdEntry::COMPETENCIES))],
            'title'   => ['required', 'string', 'max:255'],
            // A training with no hours given is taken as one hour rather than
            // refused — the office would rather have the row than the blank.
            'hours'   => ['nullable', 'numeric', 'min:0.5', 'max:999'],
            'date'    => ['required', 'date', 'before_or_equal:today'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date', 'before_or_equal:today'],
        ] + $proof);
    }

    /** The columns a filing writes. A course carries no hours or competency. */
    private function fields(array $data, bool $course): array
    {
        $common = [
            'title'   => $data['title'],
            'date'    => $data['date'],
            'date_to' => $data['date_to'] ?? null,
        ];

        return $course
            ? $common + [
                'batch'        => $data['batch'] ?? null,
                'conducted_by' => $data['conducted_by'] ?? null,
                'outside_paf'  => (bool) ($data['outside_paf'] ?? false),
                'hours'        => 0,
            ]
            : $common + [
                'competency' => $data['competency'] ?? null,
                'hours'      => $data['hours'] ?? 1,
            ];
    }

    /** Serve a proof image to the owner or an admin. */
    public function file(Request $request, LdEntry $entry, string $kind)
    {
        $user = $request->user();

        $isOwner = $user->employee && (int) $user->employee->id === (int) $entry->employee_id;
        abort_unless($isOwner || LeaveWorkflow::isAdmin($user), 403);

        $path = match ($kind) {
            'certificate' => $entry->certificate_path,
            'photo'       => $entry->photo_path,
            default       => null,
        };

        abort_unless($path && Storage::exists($path), 404);

        return response()->file(Storage::path($path));
    }
}
