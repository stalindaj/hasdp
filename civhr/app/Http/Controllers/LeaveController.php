<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\LeaveCredits;
use App\Support\LeaveWorkflow;
use App\Support\WorkingDays;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeaveController extends Controller
{
    /** The employee's own applications. */
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Leave/Index', [
            'applications' => LeaveApplication::with('leaveType:id,name,code')
                ->where('user_id', $user->id)
                ->latest()
                ->get()
                ->map(fn ($a) => $this->summary($a) + [
                    'signed_form' => (bool) $a->signed_form_path,
                ]),
            'isAdmin'      => LeaveWorkflow::isAdmin($user),
            'pendingCount' => LeaveWorkflow::isAdmin($user)
                ? LeaveApplication::where('status', LeaveWorkflow::PENDING)->count()
                : 0,
        ]);
    }

    /** Admin-only queue of everything waiting to be processed. */
    public function requests(Request $request)
    {
        abort_unless(LeaveWorkflow::isAdmin($request->user()), 403);

        return Inertia::render('Leave/Requests', [
            'applications' => LeaveApplication::with(['leaveType:id,name,code', 'user:id,name'])
                ->whereIn('status', [LeaveWorkflow::PENDING, LeaveWorkflow::APPROVED, LeaveWorkflow::DISAPPROVED])
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
                ->latest()
                ->get()
                ->map(fn ($a) => $this->summary($a) + [
                    'applicant'   => $a->applicant_name ?: $a->user->name,
                    'signed_form' => (bool) $a->signed_form_path,
                ]),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        // Boxes 1-5 come from the employee's profile and are locked on the
        // form. They are kept up to date under My Profile / (for official
        // fields) Admin → Employees, not retyped here.
        return Inertia::render('Leave/Create', [
            'leaveTypes' => LeaveType::active()->get(['id', 'code', 'name', 'legal_basis', 'detail_group', 'day_basis']),
            // 6.C is computed, not typed: the client mirrors the server's
            // working-day count live, so it needs the holiday calendar.
            'holidays' => Holiday::orderBy('date')->get()
                ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name]),
            'prefill' => [
                'office_department'    => $employee?->office_department ?? '',
                'applicant_last_name'  => $employee?->last_name ?? '',
                'applicant_first_name' => $employee?->first_name ?? '',
                'applicant_middle_name'=> $employee?->middle_name ?? '',
                'position'             => $employee?->position ?? '',
                'salary'               => $employee?->salary_grade ? (string) $employee->salary_grade : '',
                'date_filing'          => now()->toDateString(),
            ],
            'hasEmployeeRecord' => (bool) $employee,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $type = LeaveType::find($request->input('leave_type_id'));
        $group = $type?->detail_group;

        $data = $request->validate([
            'leave_type_id'     => ['required', 'exists:leave_types,id'],
            'other_leave_type'  => [Rule::requiredIf($type?->code === 'others'), 'nullable', 'string', 'max:255'],

            'office_department'     => ['required', 'string', 'max:255'],
            'applicant_last_name'   => ['required', 'string', 'max:255'],
            'applicant_first_name'  => ['required', 'string', 'max:255'],
            'applicant_middle_name' => ['nullable', 'string', 'max:255'],
            'date_filing'           => ['required', 'date'],
            'position'              => ['required', 'string', 'max:255'],
            'salary'                => ['nullable', 'string', 'max:100'],

            // 6.B — only the block that belongs to the chosen type is required
            'detail_vacation'          => [Rule::requiredIf($group === 'vacation'), 'nullable', Rule::in(['within_philippines', 'abroad'])],
            'detail_vacation_location' => ['nullable', 'string', 'max:255'],
            'detail_sick'              => [Rule::requiredIf($group === 'sick'), 'nullable', Rule::in(['in_hospital', 'out_patient'])],
            'detail_sick_illness'      => ['nullable', 'string', 'max:255'],
            'detail_women_illness'     => [Rule::requiredIf($group === 'women'), 'nullable', 'string', 'max:255'],
            'detail_study'             => [Rule::requiredIf($group === 'study'), 'nullable', Rule::in(['masters', 'bar_board', 'other'])],
            'detail_study_other'       => ['nullable', 'string', 'max:255'],
            'detail_other_purpose'     => ['nullable', Rule::in(['monetization', 'terminal'])],

            'date_from'    => ['required', 'date'],
            'date_to'      => ['required', 'date', 'after_or_equal:date_from'],
            'commutation'  => ['required', Rule::in(['not_requested', 'requested'])],
        ]);

        // 6.C is computed here, never taken from the client: weekends and
        // holidays don't count (calendar-day types like maternity count all).
        $data['working_days'] = WorkingDays::count(
            Carbon::parse($data['date_from']),
            Carbon::parse($data['date_to']),
            $type?->day_basis ?? 'working'
        );

        if ($data['working_days'] < 1) {
            throw ValidationException::withMessages([
                'date_from' => 'The inclusive dates fall entirely on weekends or holidays — there are no working days to apply for.',
            ]);
        }

        // Detail fields that belong to a different leave type would otherwise
        // linger and print on the wrong block of 6.B.
        foreach ($this->detailFieldsOutside($group) as $field) {
            $data[$field] = null;
        }

        $data['user_id']     = $user->id;
        $data['employee_id'] = $user->employee?->id;
        $data['status']      = LeaveWorkflow::PENDING;
        $data['applicant_sig'] = $this->applicantBlock($user, $data);

        // 7.A and 7.C/7.D carry fixed default signatories (e.g. the HR officer
        // and the Director for Personnel) so the form is never blank there.
        $certifier = $this->defaultCertifier();
        $approver  = $this->defaultApprover();
        $data['hr_officer_id']  = $certifier?->id;
        $data['hr_officer_sig'] = $certifier?->signatoryBlock();
        $data['approver_id']    = $approver?->id;
        $data['approver_sig']   = $approver?->signatoryBlock();

        // 7.B starts blank — the admin types the recommending officer
        // (name/rank/office) while processing.

        $application = LeaveApplication::create($data);

        LeaveWorkflow::log($application, $user, 'filed', null, LeaveWorkflow::PENDING);

        return redirect()
            ->route('leave.show', $application)
            ->with('success', 'Leave application filed. It is now with the admin for approval.');
    }

    public function show(Request $request, LeaveApplication $application)
    {
        $user = $request->user();
        $this->authorizeView($application, $user);

        $application->load(['leaveType', 'user:id,name', 'actions.user:id,name']);

        $canProcess = LeaveWorkflow::canProcess($application, $user);

        return Inertia::render('Leave/Show', [
            'application' => $this->detail($application),
            'can' => [
                'process' => $canProcess,
                'cancel'  => LeaveWorkflow::canCancel($application, $user),
                'print'   => LeaveWorkflow::canPrint($application, $user),
                // The applicant files the wet-signed copy once approved.
                'upload_form' => (int) $application->user_id === (int) $user->id
                    && $application->status === LeaveWorkflow::APPROVED,
            ],
            // CSC rules: credits are certified and checked BEFORE approval; if
            // the balance is short, the excess may be granted without pay.
            'balanceCheck' => $canProcess ? $this->balanceCheck($application) : null,
            // All three blocks are editable; 7.A / 7.C-D fall back to the role
            // holders while nothing has been typed for them.
            'signatories' => $canProcess ? [
                'certifier'   => $this->signatoryFields($application->hr_officer_sig, $this->defaultCertifier()),
                'recommender' => $this->signatoryFields($application->recommender_sig, null),
                'approver'    => $this->signatoryFields($application->approver_sig, $this->defaultApprover()),
            ] : null,
            'creditPrefill' => $canProcess ? $this->creditPrefill($application) : null,
        ]);
    }

    /**
     * Admin fills 7.A credits + 7.C/7.D and approves or disapproves. One step.
     */
    public function decide(Request $request, LeaveApplication $application)
    {
        $user = $request->user();

        abort_unless(LeaveWorkflow::canProcess($application, $user), 403, 'This application is not awaiting your action.');

        $approved = $request->input('decision') === 'approved';

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'disapproved'])],

            // 7.A certification of leave credits
            'cert_as_of' => ['nullable', 'date'],
            'vl_earned'  => ['nullable', 'numeric', 'min:0'],
            'vl_less'    => ['nullable', 'numeric', 'min:0'],
            'vl_balance' => ['nullable', 'numeric'],
            'sl_earned'  => ['nullable', 'numeric', 'min:0'],
            'sl_less'    => ['nullable', 'numeric', 'min:0'],
            'sl_balance' => ['nullable', 'numeric'],

            // 7.C approved / 7.D disapproved
            'days_with_pay'       => ['nullable', 'numeric', 'min:0'],
            'days_without_pay'    => ['nullable', 'numeric', 'min:0'],
            'days_others'         => ['nullable', 'numeric', 'min:0'],
            'days_others_specify' => ['nullable', 'string', 'max:255'],
            'disapproval_reason'  => [Rule::requiredIf(! $approved), 'nullable', 'string', 'max:1000'],
        ]);

        // 7.A and 7.C/7.D come from the HR-officer / approver roles, but an
        // admin may have typed a stand-in on the signatories card. Only fill
        // a block that is still empty, so deciding never wipes an override.
        // 7.B is likewise left alone here.
        $certifier = $application->hr_officer_sig ? null : $this->defaultCertifier();
        $approver  = $application->approver_sig ? null : $this->defaultApprover();

        // Keep 7.C and 7.D mutually exclusive on the printout.
        if ($approved) {
            $data['disapproval_reason'] = null;
        } else {
            $data['days_with_pay'] = $data['days_without_pay'] = $data['days_others'] = null;
            $data['days_others_specify'] = null;
        }

        $from = $application->status;

        $application->update($this->only($data, [
            'cert_as_of', 'vl_earned', 'vl_less', 'vl_balance', 'sl_earned', 'sl_less', 'sl_balance',
            'days_with_pay', 'days_without_pay', 'days_others', 'days_others_specify', 'disapproval_reason',
        ]) + array_filter([
            'hr_officer_id'   => $certifier?->id,
            'hr_officer_sig'  => $certifier?->signatoryBlock(),
            'approver_id'     => $approver?->id,
            'approver_sig'    => $approver?->signatoryBlock(),
        ]) + [
            'certified_at'    => now(),
            'decided_at'      => now(),
            'decision'        => $approved ? 'approved' : 'disapproved',
            'status'          => $approved ? LeaveWorkflow::APPROVED : LeaveWorkflow::DISAPPROVED,
        ]);

        LeaveWorkflow::log(
            $application,
            $user,
            $approved ? 'approved' : 'disapproved',
            $from,
            $application->status,
            $data['disapproval_reason'] ?? null
        );

        // Keep the credit ledger in sync (deduct on approve, and correctly
        // replace the deduction when a decision is revised).
        \App\Support\CreditLedger::applyForApplication(
            $application->fresh(['leaveType', 'employee'])
        );

        return back()->with('success', $approved
            ? 'Leave approved. The employee can now print the form for signature.'
            : 'Leave disapproved.');
    }

    /**
     * Save the 7.A credits and 7.C/7.D figures as a draft, without deciding —
     * so the admin can fill them in now and approve later.
     */
    public function saveDraft(Request $request, LeaveApplication $application)
    {
        $user = $request->user();
        abort_unless(LeaveWorkflow::canProcess($application, $user), 403, 'This application is not awaiting your action.');

        $data = $request->validate([
            'cert_as_of' => ['nullable', 'date'],
            'vl_earned'  => ['nullable', 'numeric', 'min:0'],
            'vl_less'    => ['nullable', 'numeric', 'min:0'],
            'vl_balance' => ['nullable', 'numeric'],
            'sl_earned'  => ['nullable', 'numeric', 'min:0'],
            'sl_less'    => ['nullable', 'numeric', 'min:0'],
            'sl_balance' => ['nullable', 'numeric'],
            'days_with_pay'       => ['nullable', 'numeric', 'min:0'],
            'days_without_pay'    => ['nullable', 'numeric', 'min:0'],
            'days_others'         => ['nullable', 'numeric', 'min:0'],
            'days_others_specify' => ['nullable', 'string', 'max:255'],
            'disapproval_reason'  => ['nullable', 'string', 'max:1000'],
        ]);

        // Only touch the figures — the status and decision are untouched.
        $application->update($data);

        return back()->with('success', 'Draft saved. The leave is still pending — approve or disapprove when ready.');
    }

    /**
     * Type in any of the three signature blocks, any time.
     *
     * 7.A and 7.C/7.D normally come from the HR-officer / approver roles and
     * rarely change; 7.B is chosen per application. Either way the admin
     * types the person, because a stand-in may not have a user account.
     *
     * Military signatories print rank at the left with the service branch
     * opposite; civilians print neither, and may carry two title lines
     * (e.g. "Admin Officer IV (HRMO II)" over "Wing Civilian Supervisor").
     * A blank name clears the block — 7.A / 7.C-D then fall back to the role
     * holder the next time the leave is decided.
     */
    public function setSignatory(Request $request, LeaveApplication $application)
    {
        abort_unless(LeaveWorkflow::isAdmin($request->user()), 403);

        $data = $request->validate([
            'slot'     => ['required', Rule::in(['certifier', 'recommender', 'approver'])],
            'type'     => ['required', Rule::in(['military', 'civilian'])],
            'name'     => ['nullable', 'string', 'max:255'],
            'rank'     => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'office'   => ['nullable', 'string', 'max:255'],
        ]);

        $military = $data['type'] === 'military';
        $name = strtoupper(trim((string) ($data['name'] ?? '')));
        $rank = $military ? trim((string) ($data['rank'] ?? '')) : '';

        $sig = $name === '' ? null : [
            'rank'   => $rank,
            'name'   => $name,
            'branch' => $rank !== '' ? (string) config('agency.branch_suffix') : '',
            // Civilians get the extra title line; a ranked officer's block
            // stays the compact rank/name/branch/office shape.
            'position'    => $military ? '' : trim((string) ($data['position'] ?? '')),
            'designation' => trim((string) ($data['office'] ?? '')),
        ];

        $column = [
            'certifier'   => 'hr_officer_sig',
            'recommender' => 'recommender_sig',
            'approver'    => 'approver_sig',
        ][$data['slot']];

        $application->update([$column => $sig]);

        $label = ['certifier' => '7.A', 'recommender' => '7.B', 'approver' => '7.C/7.D'][$data['slot']];

        return back()->with('success', $sig
            ? "{$label} set to {$name}."
            : "{$label} cleared.");
    }

    /**
     * After approval the employee prints the form, gathers the wet
     * signatures, and uploads a scan/photo here so the office keeps a
     * digital copy. Owner-only, approved leaves only; re-upload replaces.
     */
    public function storeSignedForm(Request $request, LeaveApplication $application)
    {
        $user = $request->user();

        abort_unless((int) $application->user_id === (int) $user->id, 403);
        abort_unless($application->status === LeaveWorkflow::APPROVED, 403, 'The leave must be approved first.');

        $request->validate([
            'signed_form' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        // Replace, never accumulate — one signed copy per leave.
        if ($application->signed_form_path) {
            \Illuminate\Support\Facades\Storage::delete($application->signed_form_path);
        }

        $application->update([
            'signed_form_path'        => $request->file('signed_form')->store("leave-forms/{$application->id}"),
            'signed_form_uploaded_at' => now(),
        ]);

        LeaveWorkflow::log($application, $user, 'uploaded signed form', null, null);

        return back()->with('success', 'Signed form uploaded — it is now on file.');
    }

    /** The uploaded signed form, visible to the owner and admins. */
    public function signedForm(Request $request, LeaveApplication $application)
    {
        $this->authorizeView($application, $request->user());

        $path = $application->signed_form_path;
        abort_unless($path && \Illuminate\Support\Facades\Storage::exists($path), 404);

        return response()->file(\Illuminate\Support\Facades\Storage::path($path));
    }

    public function cancel(Request $request, LeaveApplication $application)
    {
        $user = $request->user();

        abort_unless(LeaveWorkflow::canCancel($application, $user), 403, 'This application can no longer be cancelled.');

        $from = $application->status;
        $application->update(['status' => LeaveWorkflow::CANCELLED]);

        LeaveWorkflow::log($application, $user, 'cancelled', $from, LeaveWorkflow::CANCELLED);

        return back()->with('success', 'Application cancelled.');
    }

    /** The print-ready CS Form No. 6 — everything filled but the signatures. */
    public function print(Request $request, LeaveApplication $application)
    {
        $user = $request->user();
        $this->authorizeView($application, $user);

        abort_unless(LeaveWorkflow::canPrint($application, $user), 403, 'This form is not ready to print yet.');

        $application->load('leaveType');

        $asset = fn (?string $path) => $path && file_exists(public_path($path)) ? asset($path) : null;

        return view('leave.print', [
            'app'            => $application,
            // Only the official CSC checkboxes render in 6.A; unofficial types
            // (e.g. Wellness Leave) print on the "Others:" blank instead.
            'types'          => LeaveType::active()->where('is_official', true)->get(),
            'othersText'     => ! $application->leaveType->is_official
                ? trim($application->leaveType->name.' '.$application->leaveType->legal_basis)
                : ($application->leaveType->code === 'others' ? $application->other_leave_type : ''),
            'agencyName'     => config('agency.name'),
            'agencyAddress'  => config('agency.address'),
            'agencyAddress2' => config('agency.address2'),
            'logoLeft'       => $asset(config('agency.logo_left')),
            'logoRight'      => $asset(config('agency.logo_right')),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    private function only(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }

    /** How the applicant is printed on 6.D. */
    private function applicantBlock(User $user, array $data): array
    {
        $rank = $user->employee?->rank;

        return [
            'rank'   => (string) ($rank ?? ''),
            'name'   => strtoupper(trim(implode(' ', array_filter([
                $data['applicant_first_name'],
                $data['applicant_middle_name'] ?? null,
                $data['applicant_last_name'],
            ])))),
            'branch'      => $rank ? (string) config('agency.branch_suffix') : '',
            'position'    => '',
            'designation' => '',
        ];
    }

    /** The fixed 7.A certifying officer (e.g. Marie, the HR officer). */
    private function defaultCertifier(): ?User
    {
        return $this->firstWithRole('hr_officer');
    }

    /** The fixed 7.C/7.D approving official (e.g. the Director for Personnel). */
    private function defaultApprover(): ?User
    {
        return $this->firstWithRole('approver');
    }

    /** One signature block as the edit form wants it, with a printed preview. */
    private function signatoryFields(?array $sig, ?User $fallback): array
    {
        $sig ??= $fallback?->signatoryBlock();

        $rank = $sig['rank'] ?? '';

        return [
            'type'     => $rank !== '' ? 'military' : 'civilian',
            'name'     => $sig['name'] ?? '',
            'rank'     => $rank,
            'position' => $sig['position'] ?? '',
            'office'   => $sig['designation'] ?? '',
            'label'    => trim(implode(' ', array_filter([
                $rank,
                $sig['name'] ?? '',
                $sig['branch'] ?? '',
            ]))),
        ];
    }

    private function firstWithRole(string $role): ?User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))
            ->with('employee')
            ->where('is_active', true)
            ->orderBy('name')
            ->first();
    }

    /**
     * The pre-approval credit check. Splits the applied days into with-pay /
     * without-pay based on the actual balance, the way 7.C expects.
     */
    private function balanceCheck(LeaveApplication $a): ?array
    {
        if (! $a->employee) {
            return null;
        }

        $balances = \App\Support\CreditLedger::balances($a->employee);
        $kind = $a->leaveType->credit_kind;
        $days = (float) $a->working_days;

        if (! $kind) {
            return [
                'kind'     => null,
                'type'     => $a->leaveType->name,
                'days'     => $days,
                'balances' => $balances,
            ];
        }

        $available = (float) $balances[$kind];
        $withPay = round(max(0, min($available, $days)), 2);

        return [
            'kind'        => strtoupper($kind),
            'type'        => $a->leaveType->name,
            'days'        => $days,
            'balance'     => $available,
            'sufficient'  => $available >= $days,
            'with_pay'    => $withPay,
            'without_pay' => round(max(0, $days - $withPay), 2),
            'after'       => round($available - $withPay, 2),
            'balances'    => $balances,
        ];
    }

    private function creditPrefill(LeaveApplication $a): array
    {
        // The ledger is the source of truth for VL/SL balances; the days
        // applied for come off whichever balance this leave type draws on.
        if ($a->employee) {
            $balances = \App\Support\CreditLedger::balances($a->employee);
            $kind = $a->leaveType->credit_kind;
            $days = (float) $a->working_days;

            $vlLess = $kind === 'vl' ? $days : 0.0;
            $slLess = $kind === 'sl' ? $days : 0.0;

            return [
                'cert_as_of' => now()->toDateString(),
                'vl_earned'  => $balances['vl'],
                'vl_less'    => $vlLess,
                'vl_balance' => round($balances['vl'] - $vlLess, 2),
                'sl_earned'  => $balances['sl'],
                'sl_less'    => $slLess,
                'sl_balance' => round($balances['sl'] - $slLess, 2),
            ];
        }

        return LeaveCredits::certificationFor(
            $a->employee,
            $a->leaveType->code ?? '',
            (float) $a->working_days
        );
    }

    private function detailFieldsOutside(?string $group): array
    {
        $map = [
            'vacation' => ['detail_vacation', 'detail_vacation_location'],
            'sick'     => ['detail_sick', 'detail_sick_illness'],
            'women'    => ['detail_women_illness'],
            'study'    => ['detail_study', 'detail_study_other'],
        ];

        unset($map[$group]);

        return $map ? array_merge(...array_values($map)) : [];
    }

    private function authorizeView(LeaveApplication $application, User $user): void
    {
        $allowed = LeaveWorkflow::isAdmin($user)
            || (int) $application->user_id === (int) $user->id;

        abort_unless($allowed, 403, 'You do not have access to this leave application.');
    }

    private function summary(LeaveApplication $a): array
    {
        return [
            'id'            => $a->id,
            'type'          => $a->leaveType->code === 'others'
                                ? ($a->other_leave_type ?: 'Others')
                                : $a->leaveType->name,
            'working_days'  => (float) $a->working_days,
            'inclusive'     => $a->inclusive_dates_text,
            'date_filing'   => optional($a->date_filing)->format('M j, Y'),
            'status'        => $a->status,
            'status_label'  => LeaveWorkflow::label($a->status),
        ];
    }

    private function detail(LeaveApplication $a): array
    {
        return $this->summary($a) + [
            'signed_form' => [
                'uploaded' => (bool) $a->signed_form_path,
                'at'       => optional($a->signed_form_uploaded_at)->format('M j, Y g:i A'),
            ],
            'applicant'         => $a->applicant_name,
            'office_department' => $a->office_department,
            'position'          => $a->position,
            'salary'            => $a->salary,
            'date_from'         => optional($a->date_from)->format('Y-m-d'),
            'date_to'           => optional($a->date_to)->format('Y-m-d'),
            'commutation'       => $a->commutation,
            'details'           => [
                'vacation'          => $a->detail_vacation,
                'vacation_location' => $a->detail_vacation_location,
                'sick'              => $a->detail_sick,
                'sick_illness'      => $a->detail_sick_illness,
                'women_illness'     => $a->detail_women_illness,
                'study'             => $a->detail_study,
                'other_purpose'     => $a->detail_other_purpose,
            ],
            'certification' => [
                'as_of'      => optional($a->cert_as_of)->format('Y-m-d'),
                'vl_earned'  => $a->vl_earned,
                'vl_less'    => $a->vl_less,
                'vl_balance' => $a->vl_balance,
                'sl_earned'  => $a->sl_earned,
                'sl_less'    => $a->sl_less,
                'sl_balance' => $a->sl_balance,
            ],
            'officer' => $a->approver_sig
                ? trim(($a->approver_sig['rank'] ?? '').' '.($a->approver_sig['name'] ?? ''))
                : null,
            'decision' => [
                'value'            => $a->decision,
                'days_with_pay'    => $a->days_with_pay,
                'days_without_pay' => $a->days_without_pay,
                'days_others'      => $a->days_others,
                'others_specify'   => $a->days_others_specify,
                'reason'           => $a->disapproval_reason,
                'at'               => optional($a->decided_at)->format('M j, Y g:i A'),
            ],
            'history' => $a->actions->map(fn ($h) => [
                'id'      => $h->id,
                'action'  => $h->action,
                'by'      => $h->user?->name ?? 'System',
                'at'      => $h->created_at->format('M j, Y g:i A'),
                'remarks' => $h->remarks,
            ]),
        ];
    }
}
