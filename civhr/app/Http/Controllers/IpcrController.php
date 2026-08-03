<?php

namespace App\Http\Controllers;

use App\Models\IpcrForm;
use App\Models\IpcrFormGroup;
use App\Models\User;
use App\Support\IpcrAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * The IPCR form, ported natively from the standalone PHP app. Managers (admin /
 * HR / approver) work on everyone's forms; a ratee works on their own. The
 * rating maths is computed server-side on save (see IpcrForm/Group).
 */
class IpcrController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isManager = IpcrAccess::isManager($user);

        $forms = IpcrForm::with('ratee:id,name,username')
            ->when(! $isManager, fn ($q) => $q->where('user_id', $user->id))
            ->latest('updated_at')
            ->get()
            ->map(fn (IpcrForm $f) => [
                'id' => $f->id,
                'ratee' => $f->ratee?->name ?? '—',
                'rating_period' => $f->rating_period,
                'status' => $f->status,
                'overall_rating' => $f->overall_rating,
                'adjectival' => $f->fe_overall_adjectival_rating,
                'updated_at' => $f->updated_at?->toDateString(),
            ]);

        return Inertia::render('Ipcr/Index', [
            'forms' => $forms,
            'isManager' => $isManager,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Ipcr/Form', [
            'form' => null,
            'personnel' => $this->personnel(),
            'isManager' => IpcrAccess::isManager($request->user()),
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // A ratee may only file for themselves.
        if (! IpcrAccess::isManager($request->user())) {
            $data['user_id'] = $request->user()->id;
        }

        $form = $this->persist(new IpcrForm(), $data);

        return redirect()->route('ipcr.show', $form)->with('success', 'IPCR saved.');
    }

    public function edit(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canEdit($request->user(), $ipcr), 403);

        return Inertia::render('Ipcr/Form', [
            'form' => $this->payload($ipcr),
            'personnel' => $this->personnel(),
            'isManager' => IpcrAccess::isManager($request->user()),
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function update(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canEdit($request->user(), $ipcr), 403);

        $data = $this->validated($request);

        if (! IpcrAccess::isManager($request->user())) {
            $data['user_id'] = $ipcr->user_id; // ratee cannot reassign
        }

        $this->persist($ipcr, $data);

        return redirect()->route('ipcr.show', $ipcr)->with('success', 'IPCR updated.');
    }

    public function show(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canView($request->user(), $ipcr), 403);

        return Inertia::render('Ipcr/Show', [
            'form' => $this->payload($ipcr),
            'canEdit' => IpcrAccess::canEdit($request->user(), $ipcr),
            'canDelete' => IpcrAccess::canDelete($request->user(), $ipcr),
        ]);
    }

    public function print(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canView($request->user(), $ipcr), 403);

        $ipcr->load(['ratee', 'groups.rows']);

        return view('ipcr.print', ['form' => $ipcr]);
    }

    public function destroy(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canDelete($request->user(), $ipcr), 403);

        $ipcr->delete();

        return redirect()->route('ipcr.index')->with('success', 'IPCR deleted.');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /** Employees who can be rated (managers pick the ratee). */
    private function personnel()
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'employee'))
            ->orderBy('name')
            ->get(['id', 'name', 'username'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'username' => $u->username]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'rating_period' => ['required', 'string', 'max:191'],
            'position_title' => ['nullable', 'string', 'max:191'],
            'office_unit' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'in:draft,submitted,reviewed,approved,rejected'],

            'prepared_by' => ['nullable', 'string', 'max:191'],
            'approved_by' => ['nullable', 'string', 'max:191'],
            'discussed_with' => ['nullable', 'string', 'max:191'],
            'discussed_date' => ['nullable', 'date'],

            'fe_reviewed_by' => ['nullable', 'string', 'max:191'],
            'fe_reviewed_date' => ['nullable', 'date'],
            'fe_approved_by' => ['nullable', 'string', 'max:191'],
            'fe_approved_date' => ['nullable', 'date'],
            'fe_review_remarks' => ['nullable', 'string'],
            'fe_assessed_by' => ['nullable', 'string', 'max:191'],
            'fe_assessed_date' => ['nullable', 'date'],
            'fe_final_rating_by' => ['nullable', 'string', 'max:191'],
            'fe_final_rating_date' => ['nullable', 'date'],
            'fe_comments' => ['nullable', 'string'],
            'fe_intervening_activity' => ['nullable', 'string'],

            'groups' => ['array'],
            'groups.*.major_final_output' => ['nullable', 'string'],
            'groups.*.success_indicator' => ['nullable', 'string'],
            'groups.*.timeliness' => ['nullable', 'string'],
            'groups.*.actual_accomplishment' => ['nullable', 'string'],
            'groups.*.quality_pct' => ['nullable', 'numeric'],
            'groups.*.timeliness_pct' => ['nullable', 'numeric'],
            'groups.*.quantity_pct' => ['nullable', 'numeric'],
            'groups.*.quality_rating' => ['nullable', 'numeric', 'between:0,5'],
            'groups.*.timeliness_rating' => ['nullable', 'numeric', 'between:0,5'],
            'groups.*.quantity_rating' => ['nullable', 'numeric', 'between:0,5'],
            'groups.*.remarks' => ['nullable', 'string'],
            'groups.*.rows' => ['array'],
            'groups.*.rows.*.performance_measure' => ['nullable', 'string', 'max:191'],
            'groups.*.rows.*.performance_targets' => ['nullable', 'string'],
            'groups.*.rows.*.outstanding' => ['nullable', 'string'],
            'groups.*.rows.*.very_satisfactory' => ['nullable', 'string'],
            'groups.*.rows.*.satisfactory' => ['nullable', 'string'],
            'groups.*.rows.*.unsatisfactory' => ['nullable', 'string'],
            'groups.*.rows.*.poor' => ['nullable', 'string'],
        ]);
    }

    /** Create/update a form and rebuild its groups + rows, then recompute. */
    private function persist(IpcrForm $form, array $data): IpcrForm
    {
        $groups = $data['groups'] ?? [];
        unset($data['groups']);

        return DB::transaction(function () use ($form, $data, $groups) {
            $form->fill($data)->save();

            // Rebuild children (mirrors the original delete-then-insert).
            $form->groups()->delete();
            $form->rows()->delete();

            foreach (array_values($groups) as $gi => $g) {
                $major = trim((string) ($g['major_final_output'] ?? ''));
                if ($major === '') {
                    continue;
                }

                $group = $form->groups()->create([
                    'major_final_output' => $major,
                    'success_indicator' => $g['success_indicator'] ?? null,
                    'timeliness' => $g['timeliness'] ?? null,
                    'sort_order' => $gi,
                    'actual_accomplishment' => $g['actual_accomplishment'] ?? null,
                    'quality_pct' => $this->num($g['quality_pct'] ?? null),
                    'timeliness_pct' => $this->num($g['timeliness_pct'] ?? null),
                    'quantity_pct' => $this->num($g['quantity_pct'] ?? null),
                    'quality_rating' => $this->num($g['quality_rating'] ?? null),
                    'timeliness_rating' => $this->num($g['timeliness_rating'] ?? null),
                    'quantity_rating' => $this->num($g['quantity_rating'] ?? null),
                    'remarks' => $g['remarks'] ?? null,
                ]);

                // Compute + store this group's average.
                $group->computeAverage();
                $group->save();

                foreach (array_values($g['rows'] ?? []) as $ri => $r) {
                    $form->rows()->create([
                        'group_id' => $group->id,
                        'performance_measure' => $r['performance_measure'] ?? '',
                        'performance_targets' => $r['performance_targets'] ?? null,
                        'outstanding' => $r['outstanding'] ?? null,
                        'very_satisfactory' => $r['very_satisfactory'] ?? null,
                        'satisfactory' => $r['satisfactory'] ?? null,
                        'unsatisfactory' => $r['unsatisfactory'] ?? null,
                        'poor' => $r['poor'] ?? null,
                        'sort_order' => $ri,
                    ]);
                }
            }

            $form->load('groups');
            $form->recomputeRatings();
            $form->save();

            return $form;
        });
    }

    private function num($v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    /** Full nested payload for the editor / viewer. */
    private function payload(IpcrForm $ipcr): array
    {
        $ipcr->load(['ratee:id,name,username', 'groups.rows']);

        return [
            'id' => $ipcr->id,
            'user_id' => $ipcr->user_id,
            'ratee' => $ipcr->ratee?->name,
            'rating_period' => $ipcr->rating_period,
            'position_title' => $ipcr->position_title,
            'office_unit' => $ipcr->office_unit,
            'status' => $ipcr->status,
            'prepared_by' => $ipcr->prepared_by,
            'approved_by' => $ipcr->approved_by,
            'discussed_with' => $ipcr->discussed_with,
            'discussed_date' => optional($ipcr->discussed_date)->toDateString(),
            'fe_reviewed_by' => $ipcr->fe_reviewed_by,
            'fe_reviewed_date' => optional($ipcr->fe_reviewed_date)->toDateString(),
            'fe_approved_by' => $ipcr->fe_approved_by,
            'fe_approved_date' => optional($ipcr->fe_approved_date)->toDateString(),
            'fe_review_remarks' => $ipcr->fe_review_remarks,
            'fe_assessed_by' => $ipcr->fe_assessed_by,
            'fe_assessed_date' => optional($ipcr->fe_assessed_date)->toDateString(),
            'fe_final_rating_by' => $ipcr->fe_final_rating_by,
            'fe_final_rating_date' => optional($ipcr->fe_final_rating_date)->toDateString(),
            'fe_comments' => $ipcr->fe_comments,
            'fe_intervening_activity' => $ipcr->fe_intervening_activity,
            'overall_rating' => $ipcr->overall_rating,
            'fe_overall_numerical_rating' => $ipcr->fe_overall_numerical_rating,
            'fe_overall_adjectival_rating' => $ipcr->fe_overall_adjectival_rating,
            'groups' => $ipcr->groups->map(fn (IpcrFormGroup $g) => [
                'major_final_output' => $g->major_final_output,
                'success_indicator' => $g->success_indicator,
                'timeliness' => $g->timeliness,
                'actual_accomplishment' => $g->actual_accomplishment,
                'quality_pct' => $g->quality_pct,
                'timeliness_pct' => $g->timeliness_pct,
                'quantity_pct' => $g->quantity_pct,
                'quality_rating' => $g->quality_rating,
                'timeliness_rating' => $g->timeliness_rating,
                'quantity_rating' => $g->quantity_rating,
                'average_rating' => $g->average_rating,
                'remarks' => $g->remarks,
                'rows' => $g->rows->map(fn ($r) => [
                    'performance_measure' => $r->performance_measure,
                    'performance_targets' => $r->performance_targets,
                    'outstanding' => $r->outstanding,
                    'very_satisfactory' => $r->very_satisfactory,
                    'satisfactory' => $r->satisfactory,
                    'unsatisfactory' => $r->unsatisfactory,
                    'poor' => $r->poor,
                ])->values(),
            ])->values(),
        ];
    }
}
