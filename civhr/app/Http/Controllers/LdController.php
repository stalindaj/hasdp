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
            ->with(['ldEntries' => fn ($q) => $q->whereYear('date', $year)])
            ->orderBy('last_name')
            ->get();

        $rows = $employees->map(function ($e) {
            $hours = (float) $e->ldEntries->where('status', LdEntry::APPROVED)->sum('hours');
            $target = LdTarget::hoursFor($e);

            return [
                'id'        => $e->id,
                'emp_no'    => $e->emp_no,
                'name'      => trim($e->last_name.', '.$e->first_name),
                'sg'        => $e->salary_grade,
                'target'    => $target,
                'hours'     => round($hours, 1),
                'remaining' => round(max(0, $target - $hours), 1),
                'met'       => $hours >= $target,
                'pending'   => $e->ldEntries->where('status', LdEntry::PENDING)->count(),
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
                'title'    => $l->title,
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
            'summary' => [
                'total'   => $rows->count(),
                'met'     => $rows->where('met', true)->count(),
                'behind'  => $rows->where('met', false)->count(),
                'pending' => $pending->count(),
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

        $years = $employee->ldEntries()->pluck('date')
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
                'title'      => $l->title,
                'dates'      => $l->inclusive_dates_text,
                'hours'      => (float) $l->hours,
                'status'     => $l->status,
                'remarks'    => $l->remarks,
                'certificate' => $l->certificate_path ? route('ld.file', [$l, 'certificate']) : null,
                'photo'       => $l->photo_path ? route('ld.file', [$l, 'photo']) : null,
            ])->values(),
        ]);
    }

    /** Employee submits a training for approval. */
    public function store(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee record.');

        $data = $request->validate([
            'competency' => ['nullable', Rule::in(array_keys(LdEntry::COMPETENCIES))],
            'title'   => ['required', 'string', 'max:255'],
            // A training with no hours given is taken as one hour rather than
            // refused — the office would rather have the row than the blank.
            'hours'   => ['nullable', 'numeric', 'min:0.5', 'max:999'],
            'date'    => ['required', 'date', 'before_or_equal:today'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date', 'before_or_equal:today'],
            // Proof is asked for, never required: it can follow later.
            'certificate' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photo'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $dir = "ld/{$employee->id}";

        $employee->ldEntries()->create([
            'competency' => $data['competency'] ?? null,
            'title'   => $data['title'],
            'hours'   => $data['hours'] ?? 1,
            'date'    => $data['date'],
            'date_to' => $data['date_to'] ?? null,
            'status'  => LdEntry::PENDING,
            'certificate_path' => $request->file('certificate')?->store($dir),
            'photo_path'       => $request->file('photo')?->store($dir),
            'submitted_by'     => $request->user()->id,
        ]);

        return back()->with('success', 'Training submitted. The hours will count once an admin approves it.');
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
