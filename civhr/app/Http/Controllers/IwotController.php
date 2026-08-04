<?php

namespace App\Http\Controllers;

use App\Models\IwotForm;
use App\Models\IwotFormGroup;
use App\Models\User;
use App\Support\FormSignatures;
use App\Support\IwotAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * IWOT — the Individual Work Output Target sheet. Filled on-screen as the same
 * matrix the personnel already know, saved as a draft, then printed on the
 * official template with e-signatures over the Prepared by / Approved by
 * blocks.
 */
class IwotController extends Controller
{
    private const MEASURES = ['Quality', 'Timeliness', 'Quantity'];

    public function index(Request $request)
    {
        $user = $request->user();
        $isManager = IwotAccess::isManager($user);

        $forms = IwotForm::with('employee:id,name')
            ->when(! $isManager, fn ($q) => $q->where('user_id', $user->id))
            ->withCount('groups')
            ->latest('updated_at')
            ->get()
            ->map(fn (IwotForm $f) => [
                'id' => $f->id,
                'employee' => $f->employee?->name ?? '—',
                'position_title' => $f->position_title,
                'rating_period' => $f->rating_period,
                'outputs' => $f->groups_count,
                'status' => $f->status,
                'updated_at' => $f->updated_at?->toDateString(),
            ]);

        return Inertia::render('Iwot/Index', [
            'forms' => $forms,
            'isManager' => $isManager,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Iwot/Form', [
            'form' => null,
            'personnel' => $this->personnel(),
            'isManager' => IwotAccess::isManager($request->user()),
            'currentUserId' => $request->user()->id,
            'defaults' => [
                'name' => $request->user()->name,
                'position' => $request->user()->employee?->position ?? '',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Only for themselves, and only as far as "submitted" — the UI hides
        // the rest, but the request must not be trusted.
        if (! IwotAccess::isManager($request->user())) {
            $data['user_id'] = $request->user()->id;
            $data['status'] = $this->ownerStatus($data['status']);
        }

        $form = $this->persist(new IwotForm(), $data);

        return redirect()->route('iwot.show', $form)->with('success', 'IWOT saved.');
    }

    public function edit(Request $request, IwotForm $iwot)
    {
        abort_unless(IwotAccess::canEdit($request->user(), $iwot), 403);

        return Inertia::render('Iwot/Form', [
            'form' => $this->payload($iwot),
            'personnel' => $this->personnel(),
            'isManager' => IwotAccess::isManager($request->user()),
            'currentUserId' => $request->user()->id,
            'defaults' => [
                'name' => $request->user()->name,
                'position' => $request->user()->employee?->position ?? '',
            ],
        ]);
    }

    public function update(Request $request, IwotForm $iwot)
    {
        abort_unless(IwotAccess::canEdit($request->user(), $iwot), 403);

        $data = $this->validated($request);

        if (! IwotAccess::isManager($request->user())) {
            $data['user_id'] = $iwot->user_id;
            $data['status'] = $this->ownerStatus($data['status']);
        }

        $this->persist($iwot, $data);

        return redirect()->route('iwot.show', $iwot)->with('success', 'IWOT updated.');
    }

    public function show(Request $request, IwotForm $iwot)
    {
        abort_unless(IwotAccess::canView($request->user(), $iwot), 403);

        return Inertia::render('Iwot/Show', [
            'form' => $this->payload($iwot),
            'canEdit' => IwotAccess::canEdit($request->user(), $iwot),
            'canDelete' => IwotAccess::canDelete($request->user(), $iwot),
            'canSubmit' => IwotAccess::canSubmit($request->user(), $iwot),
            'canDecide' => IwotAccess::canDecide($request->user(), $iwot),
        ]);
    }

    /** The official IWOT sheet, ready to print (and to sign on-screen). */
    public function print(Request $request, IwotForm $iwot)
    {
        abort_unless(IwotAccess::canView($request->user(), $iwot), 403);

        $user = $request->user();
        $iwot->load(['employee', 'groups.rows']);

        $signable = collect(IwotForm::SIGNATURE_SLOTS)
            ->mapWithKeys(fn ($slot) => [$slot => IwotAccess::canSignBlock($user, $iwot, $slot)])
            ->all();

        return view('iwot.print', [
            'form' => $iwot,
            'signatures' => [
                // The employee's own account e-signature stands in until an
                // image is uploaded onto this sheet.
                'prepared' => FormSignatures::resolve($iwot, 'prepared', 'iwot.signature', $iwot->employee),
                'approved' => FormSignatures::resolve($iwot, 'approved', 'iwot.signature'),
            ],
            'signable' => $signable,
            'canSign' => in_array(true, $signable, true),
        ]);
    }

    public function destroy(Request $request, IwotForm $iwot)
    {
        abort_unless(IwotAccess::canDelete($request->user(), $iwot), 403);

        $iwot->delete();

        return redirect()->route('iwot.index')->with('success', 'IWOT deleted.');
    }

    public function submit(Request $request, IwotForm $iwot)
    {
        abort_unless(IwotAccess::canSubmit($request->user(), $iwot), 403);

        $iwot->update(['status' => IwotForm::SUBMITTED, 'submitted_at' => now()]);

        return back()->with('success', 'IWOT submitted for approval.');
    }

    public function decide(Request $request, IwotForm $iwot)
    {
        abort_unless(IwotAccess::canDecide($request->user(), $iwot), 403);

        $decision = $request->validate(['decision' => ['required', 'in:approve,return']])['decision'];

        if ($decision === 'approve') {
            $iwot->update([
                'status' => IwotForm::APPROVED,
                'approved_at' => now(),
                'approved_by_id' => $request->user()->id,
            ]);

            return back()->with('success', 'IWOT approved.');
        }

        $iwot->update(['status' => IwotForm::RETURNED, 'submitted_at' => null]);

        return back()->with('success', 'IWOT returned to the employee.');
    }

    // ── e-signatures ─────────────────────────────────────────────────────

    public function storeSignature(Request $request, IwotForm $iwot, string $slot)
    {
        abort_unless(in_array($slot, IwotForm::SIGNATURE_SLOTS, true), 404);
        abort_unless(IwotAccess::canSignBlock($request->user(), $iwot, $slot), 403,
            'That signature block is not yours to sign.');

        $request->validate([
            'signature' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
        ], [
            'signature.image' => 'Upload a picture of the signature (a PNG with a transparent background prints cleanest).',
            'signature.max' => 'That image is too large — keep it under 8 MB.',
        ]);

        FormSignatures::put($iwot, $slot, $request->file('signature'), "iwot-signatures/{$iwot->id}");

        return back()->with('success', 'Signature added — it prints over the name.');
    }

    public function destroySignature(Request $request, IwotForm $iwot, string $slot)
    {
        abort_unless(in_array($slot, IwotForm::SIGNATURE_SLOTS, true), 404);
        abort_unless(IwotAccess::canSignBlock($request->user(), $iwot, $slot), 403);

        FormSignatures::forget($iwot, $slot);

        return back()->with('success', 'Signature removed.');
    }

    public function signature(Request $request, IwotForm $iwot, string $slot)
    {
        abort_unless(in_array($slot, IwotForm::SIGNATURE_SLOTS, true), 404);
        abort_unless(IwotAccess::canView($request->user(), $iwot), 403);
        abort_unless(FormSignatures::has($iwot, $slot), 404);

        return response()->file(Storage::path(FormSignatures::path($iwot, $slot)));
    }

    // ── helpers ──────────────────────────────────────────────────────────

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

    /** The owner can only leave their own sheet as a draft or submit it. */
    private function ownerStatus(?string $status): string
    {
        return in_array($status, [IwotForm::DRAFT, IwotForm::SUBMITTED], true)
            ? $status
            : IwotForm::DRAFT;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'position_title' => ['nullable', 'string', 'max:191'],
            'office_unit' => ['nullable', 'string', 'max:191'],
            'rating_period' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'in:draft,submitted,approved,returned'],

            'prepared_by' => ['nullable', 'string', 'max:191'],
            'prepared_designation' => ['nullable', 'string', 'max:191'],
            'approved_by' => ['nullable', 'string', 'max:191'],
            'approved_designation' => ['nullable', 'string', 'max:191'],

            'groups' => ['array'],
            'groups.*.major_final_output' => ['nullable', 'string'],
            'groups.*.timeliness' => ['nullable', 'string'],
            'groups.*.success_indicator' => ['nullable', 'string'],
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

    private function persist(IwotForm $form, array $data): IwotForm
    {
        $groups = $data['groups'] ?? [];
        unset($data['groups']);

        return DB::transaction(function () use ($form, $data, $groups) {
            $form->fill($data)->save();

            $form->groups()->delete();
            $form->rows()->delete();

            foreach (array_values($groups) as $gi => $g) {
                $major = trim((string) ($g['major_final_output'] ?? ''));
                if ($major === '') {
                    continue;
                }

                $group = $form->groups()->create([
                    'major_final_output' => $major,
                    'timeliness' => $g['timeliness'] ?? null,
                    'success_indicator' => $g['success_indicator'] ?? null,
                    'sort_order' => $gi,
                ]);

                foreach (array_values($g['rows'] ?? []) as $ri => $r) {
                    $form->rows()->create([
                        'group_id' => $group->id,
                        'performance_measure' => $r['performance_measure'] ?? (self::MEASURES[$ri] ?? ''),
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

            return $form;
        });
    }

    private function payload(IwotForm $form): array
    {
        $form->load(['employee:id,name', 'groups.rows']);

        return [
            'id' => $form->id,
            'user_id' => $form->user_id,
            'employee' => $form->employee?->name,
            'position_title' => $form->position_title,
            'office_unit' => $form->office_unit,
            'rating_period' => $form->rating_period,
            'status' => $form->status,
            'prepared_by' => $form->prepared_by,
            'prepared_designation' => $form->prepared_designation,
            'approved_by' => $form->approved_by,
            'approved_designation' => $form->approved_designation,
            'submitted_at' => optional($form->submitted_at)->toDateString(),
            'approved_at' => optional($form->approved_at)->toDateString(),
            'groups' => $form->groups->map(fn (IwotFormGroup $g) => [
                'major_final_output' => $g->major_final_output,
                'timeliness' => $g->timeliness,
                'success_indicator' => $g->success_indicator,
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
