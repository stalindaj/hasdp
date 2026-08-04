@php
    /**
     * The official IPCR (FORM E), reproduced from the office template
     * (IPCR-TEMPLATE.xlsx): commitment paragraph, the Reviewed/Approved band,
     * the rating grid (Output · Success Indicator · Actual Accomplishments ·
     * Ql1/Qn2/T3/A4 · Remarks), the summary ladder, the comments band and the
     * rater's block. Colours are the template's own theme fills — the peach
     * band is Accent 2 lighter 60% (#f8cbad), the grid head Accent 5 lighter
     * 60% (#bdd7ee).
     *
     * Each named block takes one e-signature image, dropped on from here.
     */
    $sigBlock = function ($frozen, $user) {
        if (is_array($frozen) && ($frozen['name'] ?? '') !== '') {
            return $frozen;
        }
        return $user?->signatoryBlock();
    };

    $ratee = $form->ratee;
    $emp = $ratee?->employee;
    $sg = $emp?->salary_grade;

    $rateeSig = $sigBlock($form->ratee_sig, $ratee);
    $reviewerSig = $form->reviewer_sig ?? [];
    $approverSig = $form->approver_sig ?? [];

    $name = fn ($b) => $b ? trim(implode(' ', array_filter([$b['rank'] ?? '', $b['name'] ?? '', $b['branch'] ?? '']))) : '';
    $desig = fn ($b) => $b ? (($b['designation'] ?? '') ?: ($b['position'] ?? '')) : '';

    $rateeName = $name($rateeSig) ?: ($ratee->name ?? '');
    $rateePosition = trim(($form->position_title ?: '').($sg ? " SG-{$sg}" : ''));

    $num = fn ($v) => $v === null || $v === '' ? '' : number_format((float) $v, 2);
    $rate = fn ($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');

    $signedDate = optional($form->submitted_at ?? $form->updated_at)->format('d F Y');

    // Assessed by / Final Rating by are the same two supervisors as the top
    // block, so they carry the same designations.
    $blocks = [
        'reviewer' => ['name' => $form->fe_reviewed_by, 'desig' => $desig($reviewerSig), 'date' => $form->fe_reviewed_date],
        'approver' => ['name' => $form->fe_approved_by, 'desig' => $desig($approverSig), 'date' => $form->fe_approved_date],
        'discussed' => ['name' => $form->discussed_with ?: $rateeName, 'desig' => 'Employee', 'date' => $form->discussed_date],
        'assessed' => ['name' => $form->fe_assessed_by, 'desig' => $desig($reviewerSig), 'date' => $form->fe_assessed_date],
        'final' => ['name' => $form->fe_final_rating_by, 'desig' => $desig($approverSig), 'date' => $form->fe_final_rating_date],
    ];

    // One signatory column: the ink band, the name, then the designation —
    // the template's three stacked rows.
    $canSignSlot = fn ($slot) => $canSign && ($signable[$slot] ?? false);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>IPCR (Form E) — {{ $rateeName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #f3f4f6; margin: 0; padding: 16px; font-size: 8pt; }
        /* Landscape, like the office template — Form E is a wide sheet. */
        .sheet { width: 10.4in; margin: 0 auto; background: #fff; padding: 0.35in 0.4in; box-shadow: 0 2px 14px rgba(0,0,0,.15); }

        @page { size: Letter landscape; margin: 10mm; }

        .form-e { text-align: right; font-weight: 700; font-size: 9pt; }
        .title { text-align: center; font-weight: 700; font-size: 9pt; margin: 10px 0 14px; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td { border: 1px solid #000; padding: 2px 4px; vertical-align: top; word-wrap: break-word; }

        /* The commitment reads full width; the ratee signs underneath, right. */
        .commit td { padding: 6px 8px; }
        .commit .stmt { font-weight: 700; }
        .commit .sign { width: 3.2in; margin: 10px 0 0 auto; text-align: center; position: relative; }
        .commit .sign .nm { font-weight: 700; }
        .commit .sign img { position: absolute; left: 50%; transform: translateX(-50%); bottom: 30px; max-height: 0.42in; max-width: 90%; }

        /* Template theme fills. */
        .band { background: #f8cbad; font-weight: 700; vertical-align: middle; }
        .head { background: #bdd7ee; font-weight: 700; text-align: center; vertical-align: middle; }

        .ctr { text-align: center; }
        .mid { vertical-align: middle; }

        .ink { height: 0.46in; position: relative; }
        .ink img { position: absolute; left: 50%; transform: translateX(-50%); bottom: 1px; max-height: 0.44in; max-width: 88%; }
        .signame { text-align: center; font-weight: 700; }
        .sigdesig { text-align: center; font-size: 7.5pt; }

        .sum td { font-weight: 700; }
        .sum .val { text-align: center; }
        .legend { margin-top: 8px; font-size: 7.5pt; }

        .noprint { text-align: center; margin: 14px 0; }
        .noprint button, .noprint a {
            background: #0b2a52; color: #fff; border: 0; border-radius: 6px;
            padding: 8px 18px; margin: 0 4px; cursor: pointer; font-family: Arial, sans-serif;
            font-size: 12px; text-decoration: none; display: inline-block;
        }
        .noprint .ghost { background: #374151; }

        .sigup {
            display: inline-block; margin-top: 2px;
            font-family: Arial, Helvetica, sans-serif; font-size: 7pt; line-height: 1;
            padding: 2px 5px; border: 0; border-radius: 3px;
            background: #2563eb; color: #fff; cursor: pointer;
        }
        .sigup.rm { background: #6b7280; }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { width: auto; box-shadow: none; padding: 0; }
            .noprint, .sigup { display: none !important; }
            .band, .head { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="sheet">

    <div class="form-e">(FORM E)</div>
    <div class="title">INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)</div>

    {{-- Commitment across the full width, the ratee signing underneath --}}
    <table class="commit">
        <tr>
            <td>
                <div class="stmt">
                    I, {{ $rateeName ?: '________' }}, {{ $rateePosition ?: '________' }} of the
                    {{ $form->office_unit ?: '________' }}, commit to deliver and agree to be rated on the
                    attainment of the following targets in accordance with the indicated measures for the
                    period {{ $form->rating_period }}.
                </div>
                <div class="sign">
                    @if (! empty($signatures['ratee']))
                        <img src="{{ $signatures['ratee'] }}" alt="">
                    @endif
                    <div class="nm">{{ $rateeName }}</div>
                    <div>{{ $rateePosition }}</div>
                    <div>Date: {{ $signedDate }}</div>
                    @if ($canSignSlot('ratee'))
                        <button type="button" class="sigup" onclick="pickSig('ratee')">
                            {{ !empty($form->signature_uploads['ratee']) ? '✎ Replace signature' : '✎ Upload signature' }}
                        </button>
                        @if (!empty($form->signature_uploads['ratee']))
                            <button type="button" class="sigup rm" onclick="removeSig('ratee')" title="Remove signature">✕</button>
                        @endif
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Reviewed by / Approved by --}}
    <table style="margin-top:10px">
        <tr>
            <td class="band" style="width:33%">Reviewed by</td>
            <td class="band ctr" style="width:12%">Date</td>
            <td class="band" style="width:43%">Approved by</td>
            <td class="band ctr" style="width:12%">Date</td>
        </tr>
        <tr>
            <td class="ink">
                @if (! empty($signatures['reviewer'])) <img src="{{ $signatures['reviewer'] }}" alt=""> @endif
            </td>
            <td rowspan="3" class="ctr mid">{{ $blocks['reviewer']['date'] }}</td>
            <td class="ink">
                @if (! empty($signatures['approver'])) <img src="{{ $signatures['approver'] }}" alt=""> @endif
            </td>
            <td rowspan="3" class="ctr mid">{{ $blocks['approver']['date'] }}</td>
        </tr>
        <tr>
            <td class="signame">{{ $blocks['reviewer']['name'] }}</td>
            <td class="signame">{{ $blocks['approver']['name'] }}</td>
        </tr>
        <tr>
            <td class="sigdesig">
                {{ $blocks['reviewer']['desig'] }}
                @if ($canSignSlot('reviewer'))
                    <button type="button" class="sigup" onclick="pickSig('reviewer')">
                        {{ !empty($form->signature_uploads['reviewer']) ? '✎ Replace' : '✎ Sign' }}
                    </button>
                    @if (!empty($form->signature_uploads['reviewer']))
                        <button type="button" class="sigup rm" onclick="removeSig('reviewer')">✕</button>
                    @endif
                @endif
            </td>
            <td class="sigdesig">
                {{ $blocks['approver']['desig'] }}
                @if ($canSignSlot('approver'))
                    <button type="button" class="sigup" onclick="pickSig('approver')">
                        {{ !empty($form->signature_uploads['approver']) ? '✎ Replace' : '✎ Sign' }}
                    </button>
                    @if (!empty($form->signature_uploads['approver']))
                        <button type="button" class="sigup rm" onclick="removeSig('approver')">✕</button>
                    @endif
                @endif
            </td>
        </tr>
    </table>

    {{-- The rating grid --}}
    <table style="margin-top:10px">
        <tr>
            <td rowspan="2" class="head" style="width:15%">Output</td>
            <td rowspan="2" class="head" style="width:26%">Success Indicator (Target + Measure)</td>
            <td rowspan="2" class="head" style="width:27%">Actual Accomplishments</td>
            <td colspan="4" class="head">Rating</td>
            <td rowspan="2" class="head" style="width:12%">Remarks</td>
        </tr>
        <tr>
            <td class="head" style="width:5%">Ql1</td>
            <td class="head" style="width:5%">Qn2</td>
            <td class="head" style="width:5%">T3</td>
            <td class="head" style="width:5%">A4</td>
        </tr>

        <tr><td colspan="8" style="font-weight:700">Strategic Priority No.: {{ $form->strategic_priority }}</td></tr>
        <tr><td colspan="8" style="font-weight:700">Core Function: {{ $form->core_function }}</td></tr>

        @forelse ($form->groups as $g)
            <tr>
                <td>{{ $g->major_final_output }}</td>
                <td>{{ $g->success_indicator }}</td>
                <td>{{ $g->actual_accomplishment }}</td>
                <td class="ctr mid">{{ $rate($g->quality_rating) }}</td>
                <td class="ctr mid">{{ $rate($g->quantity_rating) }}</td>
                <td class="ctr mid">{{ $rate($g->timeliness_rating) }}</td>
                <td class="ctr mid">{{ $num($g->average_rating) }}</td>
                <td>{{ $g->remarks }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="ctr" style="color:#777">No outputs recorded.</td></tr>
        @endforelse

        {{-- The label runs across the three text columns, its figure across the
             four rating columns, exactly as the office sheet has it. --}}
        <tr class="sum">
            <td colspan="3">Average point score</td>
            <td colspan="4" class="val">{{ $num($form->fe_average_point_score) }}</td>
            <td></td>
        </tr>
        <tr class="sum">
            <td colspan="3">
                Add: Intervening Activity:
                @foreach ($form->fe_intervening_activities ?? [] as $a)
                    <span style="font-weight:400">{{ $a['activity'] ?? '' }}@if (! $loop->last);@endif</span>
                @endforeach
            </td>
            <td colspan="4" class="val">{{ $form->interveningTotal() > 0 ? $num($form->fe_intervening_activity) : '' }}</td>
            <td></td>
        </tr>
        <tr class="sum">
            <td colspan="3">Overall point score:</td>
            <td colspan="4" class="val">{{ $num($form->fe_overall_point_score) }}</td>
            <td></td>
        </tr>
        <tr class="sum">
            <td colspan="3">Overall Equivalent Numerical Rating</td>
            <td colspan="4" class="val">{{ $num($form->fe_overall_numerical_rating) }}</td>
            <td></td>
        </tr>
        <tr class="sum">
            <td colspan="3">Overall Equivalent Adjectival Rating</td>
            <td colspan="4" class="val">{{ $form->fe_overall_adjectival_rating }}</td>
            <td></td>
        </tr>
        <tr><td colspan="8" class="band">Comments and Recommendations for Development Purposes</td></tr>
        <tr><td colspan="8" style="height:0.42in">{{ $form->fe_comments }}</td></tr>
    </table>

    {{-- The rater's block --}}
    <table style="margin-top:10px">
        <tr>
            <td class="band ctr" style="width:24%">Discussed with</td>
            <td class="band ctr" style="width:10%">Date</td>
            <td class="band ctr" style="width:24%">Assessed by</td>
            <td class="band ctr" style="width:10%">Date</td>
            <td class="band ctr" style="width:24%">Final Rating by</td>
            <td class="band ctr" style="width:8%">Date</td>
        </tr>
        <tr>
            @foreach (['discussed', 'assessed', 'final'] as $slot)
                <td class="ink">
                    @if (! empty($signatures[$slot])) <img src="{{ $signatures[$slot] }}" alt=""> @endif
                </td>
                <td rowspan="3" class="ctr mid">{{ $blocks[$slot]['date'] }}</td>
            @endforeach
        </tr>
        <tr>
            @foreach (['discussed', 'assessed', 'final'] as $slot)
                <td class="signame">{{ $blocks[$slot]['name'] }}</td>
            @endforeach
        </tr>
        <tr>
            @foreach (['discussed', 'assessed', 'final'] as $slot)
                <td class="sigdesig">
                    {{ $blocks[$slot]['desig'] }}
                    @if ($canSignSlot($slot))
                        <button type="button" class="sigup" onclick="pickSig('{{ $slot }}')">
                            {{ !empty($form->signature_uploads[$slot]) ? '✎ Replace' : '✎ Sign' }}
                        </button>
                        @if (!empty($form->signature_uploads[$slot]))
                            <button type="button" class="sigup rm" onclick="removeSig('{{ $slot }}')">✕</button>
                        @endif
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    <div class="legend">
        Legend: &nbsp;&nbsp; 1 - Quality (Ql) &nbsp;&nbsp; 2 - Quantity (Qn) &nbsp;&nbsp;
        3 - Timeliness (T) &nbsp;&nbsp; 4 - Average (A)
    </div>
</div>

<div class="noprint">
    <button onclick="window.print()">Print Form E</button>
    <a class="ghost" href="{{ route('ipcr.show', $form) }}">Back to the IPCR</a>
</div>

@include('partials.signature-picker', [
    'base' => parse_url(url('ipcr/'.$form->id.'/signature'), PHP_URL_PATH),
    'canSign' => $canSign,
])
</body>
</html>
