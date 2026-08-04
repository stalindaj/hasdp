<?php

namespace App\Http\Controllers;

use App\Models\IpcrForm;
use App\Models\IpcrFormGroup;
use App\Models\User;
use App\Support\FormSignatures;
use App\Support\IpcrAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            'defaults' => $this->defaults($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // A ratee may only file for themselves, and only as far as "submitted"
        // — the UI hides the rest, but the request must not be trusted.
        if (! IpcrAccess::isManager($request->user())) {
            $data['user_id'] = $request->user()->id;
            $data['status'] = $this->rateeStatus($data['status']);
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
            'defaults' => $this->defaults($request),
        ]);
    }

    public function update(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canEdit($request->user(), $ipcr), 403);

        $data = $this->validated($request);

        if (! IpcrAccess::isManager($request->user())) {
            $data['user_id'] = $ipcr->user_id; // ratee cannot reassign
            $data['status'] = $this->rateeStatus($data['status']);
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
            'canSubmit' => IpcrAccess::canSubmit($request->user(), $ipcr),
            'canDecide' => IpcrAccess::canDecide($request->user(), $ipcr),
            'canUploadScan' => IpcrAccess::canUploadScan($request->user(), $ipcr),
            'hasScan' => (bool) $ipcr->scanned_copy_path,
        ]);
    }

    /** The official Form E, ready to print (and to sign on-screen). */
    public function print(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canView($request->user(), $ipcr), 403);

        $user = $request->user();
        $ipcr->load(['ratee.employee', 'reviewer.employee', 'approver.employee', 'groups.rows']);

        $signable = collect(IpcrForm::SIGNATURE_SLOTS)
            ->mapWithKeys(fn ($slot) => [$slot => IpcrAccess::canSignBlock($user, $ipcr, $slot)])
            ->all();

        // The ratee signs their own two blocks, so their account e-signature
        // stands in until an image is uploaded onto this form. The typed
        // supervisors have no account to fall back on.
        $signatures = collect(IpcrForm::SIGNATURE_SLOTS)
            ->mapWithKeys(fn ($slot) => [$slot => FormSignatures::resolve(
                $ipcr,
                $slot,
                'ipcr.signature',
                in_array($slot, ['ratee', 'discussed'], true) ? $ipcr->ratee : null,
            )])
            ->all();

        return view('ipcr.print', [
            'form' => $ipcr,
            'signatures' => $signatures,
            'signable' => $signable,
            'canSign' => in_array(true, $signable, true),
        ]);
    }

    // ── e-signatures ─────────────────────────────────────────────────────

    public function storeSignature(Request $request, IpcrForm $ipcr, string $slot)
    {
        abort_unless(in_array($slot, IpcrForm::SIGNATURE_SLOTS, true), 404);
        abort_unless(IpcrAccess::canSignBlock($request->user(), $ipcr, $slot), 403,
            'That signature block is not yours to sign.');

        $request->validate([
            'signature' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
        ], [
            'signature.image' => 'Upload a picture of the signature (a PNG with a transparent background prints cleanest).',
            'signature.max' => 'That image is too large — keep it under 8 MB.',
        ]);

        FormSignatures::put($ipcr, $slot, $request->file('signature'), "ipcr-signatures/{$ipcr->id}");

        return back()->with('success', 'Signature added — it prints over the name on Form E.');
    }

    public function destroySignature(Request $request, IpcrForm $ipcr, string $slot)
    {
        abort_unless(in_array($slot, IpcrForm::SIGNATURE_SLOTS, true), 404);
        abort_unless(IpcrAccess::canSignBlock($request->user(), $ipcr, $slot), 403);

        FormSignatures::forget($ipcr, $slot);

        return back()->with('success', 'Signature removed.');
    }

    public function signature(Request $request, IpcrForm $ipcr, string $slot)
    {
        abort_unless(in_array($slot, IpcrForm::SIGNATURE_SLOTS, true), 404);
        abort_unless(IpcrAccess::canView($request->user(), $ipcr), 403);
        abort_unless(FormSignatures::has($ipcr, $slot), 404);

        return response()->file(Storage::path(FormSignatures::path($ipcr, $slot)));
    }

    public function destroy(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canDelete($request->user(), $ipcr), 403);

        $ipcr->delete();

        return redirect()->route('ipcr.index')->with('success', 'IPCR deleted.');
    }

    /** The ratee (or a manager) submits a draft for approval. */
    public function submit(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canSubmit($request->user(), $ipcr), 403);

        // Signatory snapshots were frozen at save; just refresh the ratee's in
        // case their account details changed.
        $ipcr->ratee_sig = $ipcr->ratee?->signatoryBlock();
        $ipcr->status = IpcrForm::SUBMITTED;
        $ipcr->submitted_at = now();
        $ipcr->save();

        return back()->with('success', 'IPCR submitted for approval.');
    }

    /** A manager / the named approver approves or returns a submitted form. */
    public function decide(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canDecide($request->user(), $ipcr), 403);

        $decision = $request->validate([
            'decision' => ['required', 'in:approve,return'],
        ])['decision'];

        if ($decision === 'approve') {
            $ipcr->status = IpcrForm::APPROVED;
            $ipcr->approved_at = now();
            $ipcr->approved_by_id = $request->user()->id;
            $ipcr->save();

            return back()->with('success', 'IPCR approved.');
        }

        $ipcr->status = IpcrForm::RETURNED;
        $ipcr->submitted_at = null;
        $ipcr->save();

        return back()->with('success', 'IPCR returned to the ratee.');
    }

    /** Upload the scanned, wet-signed copy (after approval). */
    public function storeScan(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canUploadScan($request->user(), $ipcr), 403);

        $request->validate([
            'scan' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:12288'],
        ]);

        if ($ipcr->scanned_copy_path) {
            Storage::delete($ipcr->scanned_copy_path);
        }

        $ipcr->scanned_copy_path = $request->file('scan')->store("ipcr-forms/{$ipcr->id}");
        $ipcr->save();

        return back()->with('success', 'Scanned copy uploaded.');
    }

    /** Serve the scanned copy to the ratee or a manager. */
    public function scan(Request $request, IpcrForm $ipcr)
    {
        abort_unless(IpcrAccess::canView($request->user(), $ipcr), 403);
        abort_unless($ipcr->scanned_copy_path && Storage::exists($ipcr->scanned_copy_path), 404);

        return response()->file(Storage::path($ipcr->scanned_copy_path));
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * Employees who can be rated (managers pick the ratee). The position comes
     * along so picking a ratee fills the matrix header the way the original
     * app's updateUserDetails() did.
     */
    private function personnel()
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'employee'))
            ->with('employee:id,position')
            ->orderBy('name')
            ->get(['id', 'employee_id', 'name', 'username'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'position' => $u->employee?->position,
            ])
            ->values();
    }

    /** The signed-in user's own header defaults for a brand-new form. */
    private function defaults(Request $request): array
    {
        $user = $request->user();

        return [
            'name' => $user->name,
            'position' => $user->employee?->position ?? '',
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'reviewer_name' => ['nullable', 'string', 'max:191'],
            'reviewer_designation' => ['nullable', 'string', 'max:191'],
            'approver_name' => ['nullable', 'string', 'max:191'],
            'approver_designation' => ['nullable', 'string', 'max:191'],
            'rating_period' => ['required', 'string', 'max:191'],
            'position_title' => ['nullable', 'string', 'max:191'],
            'office_unit' => ['nullable', 'string', 'max:191'],
            'strategic_priority' => ['nullable', 'string', 'max:191'],
            'core_function' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'in:draft,submitted,reviewed,approved,rejected,returned'],

            'prepared_by' => ['nullable', 'string', 'max:191'],
            'approved_by' => ['nullable', 'string', 'max:191'],
            'discussed_with' => ['nullable', 'string', 'max:191'],

            'fe_reviewed_by' => ['nullable', 'string', 'max:191'],
            'fe_approved_by' => ['nullable', 'string', 'max:191'],
            'fe_review_remarks' => ['nullable', 'string'],
            'fe_assessed_by' => ['nullable', 'string', 'max:191'],
            'fe_final_rating_by' => ['nullable', 'string', 'max:191'],
            'fe_comments' => ['nullable', 'string'],

            // Form E date cells are free text, filled from the rating period
            // ("January", "June 2026") — not calendar dates.
            'discussed_date' => ['nullable', 'string', 'max:60'],
            'fe_reviewed_date' => ['nullable', 'string', 'max:60'],
            'fe_approved_date' => ['nullable', 'string', 'max:60'],
            'fe_assessed_date' => ['nullable', 'string', 'max:60'],
            'fe_final_rating_date' => ['nullable', 'string', 'max:60'],

            // "Add: Intervening Activity" — a list of {activity, rating} rows;
            // their total is computed server-side into fe_intervening_activity.
            'fe_intervening_activities' => ['nullable', 'array'],
            'fe_intervening_activities.*.activity' => ['nullable', 'string', 'max:191'],
            'fe_intervening_activities.*.rating' => ['nullable', 'numeric'],

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
            'groups.*.rows.*.selected_band' => ['nullable', 'in:o,vs,s,u,p'],
        ]);
    }

    /** Create/update a form and rebuild its groups + rows, then recompute. */
    private function persist(IpcrForm $form, array $data): IpcrForm
    {
        $groups = $data['groups'] ?? [];
        unset($data['groups']);

        // Typed signatories -> frozen {name, designation} snapshots (these are
        // usually military supervisors, not accounts in the system). The names
        // are typed straight into the Form E "Reviewed by" / "Approved by"
        // cells; the older explicit fields still win when they are sent.
        $reviewerSig = $this->sig(
            $data['reviewer_name'] ?? $data['fe_reviewed_by'] ?? null,
            $data['reviewer_designation'] ?? null,
        );
        $approverSig = $this->sig(
            $data['approver_name'] ?? $data['fe_approved_by'] ?? null,
            $data['approver_designation'] ?? null,
        );
        unset($data['reviewer_name'], $data['reviewer_designation'], $data['approver_name'], $data['approver_designation']);

        return DB::transaction(function () use ($form, $data, $groups, $reviewerSig, $approverSig) {
            $form->fill($data)->save();

            // Ratee signs from their own account; the two supervisors are typed.
            $form->ratee_sig = $form->ratee?->signatoryBlock();
            $form->reviewer_sig = $reviewerSig;
            $form->approver_sig = $approverSig;
            $form->save();

            // Rebuild children (mirrors the original delete-then-insert).
            $form->groups()->delete();
            $form->rows()->delete();

            foreach (array_values($groups) as $gi => $g) {
                $major = trim((string) ($g['major_final_output'] ?? ''));
                if ($major === '') {
                    continue;
                }

                $rows = array_values($g['rows'] ?? []);

                $group = $form->groups()->create([
                    'major_final_output' => $major,
                    'success_indicator' => $g['success_indicator'] ?? null,
                    'timeliness' => $g['timeliness'] ?? null,
                    'sort_order' => $gi,
                    'actual_accomplishment' => $g['actual_accomplishment'] ?? null,
                    'quality_pct' => $this->num($g['quality_pct'] ?? null),
                    'timeliness_pct' => $this->num($g['timeliness_pct'] ?? null),
                    'quantity_pct' => $this->num($g['quantity_pct'] ?? null),
                    'quality_rating' => $this->rating($g, $rows, 0),
                    'timeliness_rating' => $this->rating($g, $rows, 1),
                    'quantity_rating' => $this->rating($g, $rows, 2),
                    'remarks' => $g['remarks'] ?? null,
                ]);

                // Compute + store this group's average.
                $group->computeAverage();
                $group->save();

                foreach ($rows as $ri => $r) {
                    $form->rows()->create([
                        'group_id' => $group->id,
                        'performance_measure' => $r['performance_measure']
                            ?? (IpcrFormGroup::MEASURES[$ri]['measure'] ?? ''),
                        'performance_targets' => $r['performance_targets'] ?? null,
                        'outstanding' => $r['outstanding'] ?? null,
                        'very_satisfactory' => $r['very_satisfactory'] ?? null,
                        'satisfactory' => $r['satisfactory'] ?? null,
                        'unsatisfactory' => $r['unsatisfactory'] ?? null,
                        'poor' => $r['poor'] ?? null,
                        'selected_band' => $r['selected_band'] ?? null,
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

    /** A ratee can only leave their own form as a draft or submit it. */
    private function rateeStatus(?string $status): string
    {
        return in_array($status, [IpcrForm::DRAFT, IpcrForm::SUBMITTED], true)
            ? $status
            : IpcrForm::DRAFT;
    }

    /**
     * One measure's rating. A rating typed into Form E wins (the ratee may
     * override); otherwise it is read off the achieved % against that
     * measure's five standard descriptors, so the score never depends on the
     * browser having run the auto-rating.
     */
    private function rating(array $group, array $rows, int $measure): ?float
    {
        $keys = IpcrFormGroup::MEASURES[$measure];

        if (($typed = $this->num($group[$keys['rating']] ?? null)) !== null) {
            return $typed;
        }

        return IpcrFormGroup::rateFromPercent(
            $this->num($group[$keys['pct']] ?? null),
            $rows[$measure] ?? [],
        );
    }

    /** A typed signatory -> a frozen block matching User::signatoryBlock() keys. */
    private function sig(?string $name, ?string $designation): ?array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return [
            'rank' => '',
            'name' => $name,
            'branch' => '',
            'position' => trim((string) $designation),
            'designation' => trim((string) $designation),
        ];
    }

    /** Full nested payload for the editor / viewer. */
    private function payload(IpcrForm $ipcr): array
    {
        $ipcr->load(['ratee:id,name,username', 'groups.rows']);

        return [
            'id' => $ipcr->id,
            'user_id' => $ipcr->user_id,
            'ratee' => $ipcr->ratee?->name,
            'reviewer_id' => $ipcr->reviewer_id,
            'approver_id' => $ipcr->approver_id,
            'ratee_sig' => $ipcr->ratee_sig,
            'reviewer_sig' => $ipcr->reviewer_sig,
            'approver_sig' => $ipcr->approver_sig,
            'submitted_at' => optional($ipcr->submitted_at)->toDateString(),
            'approved_at' => optional($ipcr->approved_at)->toDateString(),
            'rating_period' => $ipcr->rating_period,
            'position_title' => $ipcr->position_title,
            'office_unit' => $ipcr->office_unit,
            'strategic_priority' => $ipcr->strategic_priority,
            'core_function' => $ipcr->core_function,
            'status' => $ipcr->status,
            'prepared_by' => $ipcr->prepared_by,
            'approved_by' => $ipcr->approved_by,
            'discussed_with' => $ipcr->discussed_with,
            'discussed_date' => $ipcr->discussed_date,
            'fe_reviewed_by' => $ipcr->fe_reviewed_by,
            'fe_reviewed_date' => $ipcr->fe_reviewed_date,
            'fe_approved_by' => $ipcr->fe_approved_by,
            'fe_approved_date' => $ipcr->fe_approved_date,
            'fe_review_remarks' => $ipcr->fe_review_remarks,
            'fe_assessed_by' => $ipcr->fe_assessed_by,
            'fe_assessed_date' => $ipcr->fe_assessed_date,
            'fe_final_rating_by' => $ipcr->fe_final_rating_by,
            'fe_final_rating_date' => $ipcr->fe_final_rating_date,
            'fe_comments' => $ipcr->fe_comments,
            'fe_intervening_activity' => $ipcr->fe_intervening_activity,
            'fe_intervening_activities' => $ipcr->fe_intervening_activities ?? [],
            'overall_rating' => $ipcr->overall_rating,
            'fe_average_point_score' => $ipcr->fe_average_point_score,
            'fe_overall_point_score' => $ipcr->fe_overall_point_score,
            'fe_overall_numerical_rating' => $ipcr->fe_overall_numerical_rating,
            'fe_overall_adjectival_rating' => $ipcr->fe_overall_adjectival_rating,
            'groups' => $ipcr->groups->map(fn (IpcrFormGroup $g) => [
                'major_final_output' => $g->major_final_output,
                'success_indicator' => $g->success_indicator,
                'timeliness' => $g->timeliness,
                'actual_accomplishment' => $g->actual_accomplishment,
                // Plain numbers, not the "90.00" the decimal cast returns, so
                // the editor shows 4 rather than 4.00 in the rating cells.
                'quality_pct' => $this->num($g->quality_pct),
                'timeliness_pct' => $this->num($g->timeliness_pct),
                'quantity_pct' => $this->num($g->quantity_pct),
                'quality_rating' => $this->num($g->quality_rating),
                'timeliness_rating' => $this->num($g->timeliness_rating),
                'quantity_rating' => $this->num($g->quantity_rating),
                'average_rating' => $this->num($g->average_rating),
                'remarks' => $g->remarks,
                'rows' => $g->rows->map(fn ($r) => [
                    'performance_measure' => $r->performance_measure,
                    'performance_targets' => $r->performance_targets,
                    'outstanding' => $r->outstanding,
                    'very_satisfactory' => $r->very_satisfactory,
                    'satisfactory' => $r->satisfactory,
                    'unsatisfactory' => $r->unsatisfactory,
                    'poor' => $r->poor,
                    'selected_band' => $r->selected_band,
                ])->values(),
            ])->values(),
        ];
    }
}
