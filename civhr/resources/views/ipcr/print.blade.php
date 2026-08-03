@php
    // Resolve a signatory block from the frozen snapshot, falling back to the
    // live user (so a draft prints before it is submitted/frozen).
    $block = function ($frozen, $user) {
        if (is_array($frozen) && ($frozen['name'] ?? '') !== '') {
            return $frozen;
        }
        return $user?->signatoryBlock();
    };

    $ratee = $form->ratee;
    $emp = $ratee?->employee;
    $sg = $emp?->salary_grade;

    $rateeSig = $block($form->ratee_sig, $ratee);
    $reviewerSig = $block($form->reviewer_sig, $form->reviewer);
    $approverSig = $block($form->approver_sig, $form->approver);

    $sigName = fn ($b) => $b ? trim(implode(' ', array_filter([$b['rank'] ?? '', $b['name'] ?? '', $b['branch'] ?? '']))) : '';
    $sigDesig = fn ($b) => $b ? (($b['designation'] ?? '') ?: ($b['position'] ?? '')) : '';

    $submitted = optional($form->submitted_at)->format('d F Y');
    $approved = optional($form->approved_at)->format('d F Y');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>IPCR (Form E) — {{ $ratee->name ?? '' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #000; margin: 14mm 10mm; font-size: 10px; }
        .title { display: flex; justify-content: space-between; font-weight: 700; font-size: 12px; }
        .commit { border: 1px solid #000; border-top: 0; padding: 6px 8px; display: flex; }
        .commit .txt { flex: 1; padding-right: 10px; }
        .commit .sign { width: 200px; text-align: center; font-size: 9px; }
        .commit .sign .nm { margin-top: 22px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
        .sig-head { background: #f0a868; font-weight: 700; }
        .sig-cell { height: 62px; vertical-align: bottom; text-align: center; font-size: 9px; }
        .sig-cell .nm { font-weight: 600; }
        .sig-cell .role { font-style: italic; }
        .date-col { width: 90px; text-align: center; font-size: 9px; }
        thead.grid th { background: #6fb2d6; font-weight: 700; text-align: center; }
        .rate { width: 26px; text-align: center; }
        .sec { background: #eee; font-weight: 700; }
        .sum td { font-weight: 600; }
        .legend { border: 1px solid #000; border-top: 0; padding: 4px 6px; font-size: 8px; }
        @media print { body { margin: 10mm; } .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="title" style="border:1px solid #000;border-bottom:0;padding:4px 8px;">
        <span>INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)</span>
        <span>(FORM E)</span>
    </div>

    {{-- Commitment statement + ratee signature --}}
    <div class="commit">
        <div class="txt">
            <strong>I, {{ $ratee->name ?? '________' }},
                {{ $form->position_title ?: '________' }}{{ $sg ? " (SG-{$sg})" : '' }}
                of {{ $form->office_unit ?: '________' }}</strong>
            commit to deliver and agree to be rated on the following targets in
            accordance with indicated measures for the period
            <strong>{{ $form->rating_period }}</strong>.
        </div>
        <div class="sign">
            <div class="nm">{{ $sigName($rateeSig) ?: ($ratee->name ?? '') }}</div>
            <div>Employee</div>
            <div>Date: {{ $submitted ?: '__________' }}</div>
        </div>
    </div>

    {{-- Top signatory block --}}
    <table>
        <tr>
            <td class="sig-head" style="width:35%">Reviewed by:</td>
            <td class="sig-head date-col">Date:</td>
            <td class="sig-head" style="width:35%">Approved by:</td>
            <td class="sig-head date-col">Date:</td>
        </tr>
        <tr>
            <td class="sig-cell">
                <div class="nm">{{ $sigName($reviewerSig) ?: '________' }}</div>
                <div>{{ $sigDesig($reviewerSig) }}</div>
                <div class="role">Immediate Supervisor</div>
            </td>
            <td class="date-col" style="vertical-align:middle">{{ $submitted }}</td>
            <td class="sig-cell">
                <div class="nm">{{ $sigName($approverSig) ?: '________' }}</div>
                <div>{{ $sigDesig($approverSig) }}</div>
                <div class="role">Supervisor's Rater</div>
            </td>
            <td class="date-col" style="vertical-align:middle">{{ $approved }}</td>
        </tr>
    </table>

    {{-- Main rating table --}}
    <table>
        <thead class="grid">
            <tr>
                <th rowspan="2" style="width:20%">Output</th>
                <th rowspan="2" style="width:24%">Success Indicator (Target + Measure)</th>
                <th rowspan="2" style="width:24%">Actual Accomplishment</th>
                <th colspan="4">Rating</th>
                <th rowspan="2" style="width:10%">Remarks</th>
            </tr>
            <tr>
                <th class="rate">Q</th>
                <th class="rate">Qn</th>
                <th class="rate">T3</th>
                <th class="rate">A4</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="8" class="sec">Strategic Priority No.: {{ $form->strategic_priority }}</td></tr>
            <tr><td colspan="8" class="sec">Core Function: {{ $form->core_function }}</td></tr>

            @forelse ($form->groups as $g)
                <tr>
                    <td>{{ $g->major_final_output }}</td>
                    <td>{{ $g->success_indicator }}</td>
                    <td>{{ $g->actual_accomplishment }}</td>
                    <td class="rate">{{ $g->quality_rating ? rtrim(rtrim(number_format($g->quality_rating,0),'0'),'.') : '' }}</td>
                    <td class="rate">{{ $g->quantity_rating ? rtrim(rtrim(number_format($g->quantity_rating,0),'0'),'.') : '' }}</td>
                    <td class="rate">{{ $g->timeliness_rating ? rtrim(rtrim(number_format($g->timeliness_rating,0),'0'),'.') : '' }}</td>
                    <td class="rate">{{ $g->average_rating !== null ? number_format($g->average_rating,2) : '' }}</td>
                    <td>{{ $g->remarks }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;color:#777">No outputs.</td></tr>
            @endforelse

            <tr class="sum"><td colspan="6">Average point score</td><td class="rate">{{ $form->fe_average_point_score !== null ? number_format($form->fe_average_point_score,2) : '' }}</td><td></td></tr>
            <tr class="sum"><td colspan="8">Add: Intervening Activity: {{ $form->fe_intervening_activity }}</td></tr>
            <tr class="sum"><td colspan="6">Overall point score</td><td class="rate">{{ $form->fe_overall_point_score !== null ? number_format($form->fe_overall_point_score,2) : '' }}</td><td></td></tr>
            <tr class="sum"><td colspan="6">Overall Equivalent Numerical Rating</td><td class="rate">{{ $form->fe_overall_numerical_rating !== null ? number_format($form->fe_overall_numerical_rating,2) : '' }}</td><td></td></tr>
            <tr class="sum"><td colspan="6">Overall Equivalent Adjective Rating</td><td colspan="2" style="text-align:center;font-weight:700">{{ $form->fe_overall_adjectival_rating }}</td></tr>
        </tbody>
    </table>

    {{-- Bottom signatory block --}}
    <table>
        <tr><td colspan="6" class="sig-head">From Rater's and Recommendations for Development Purpose</td></tr>
        <tr>
            <td class="sig-head" style="width:24%">Discussed with:</td>
            <td class="sig-head date-col">Date:</td>
            <td class="sig-head" style="width:24%">Assessed by:</td>
            <td class="sig-head date-col">Date:</td>
            <td class="sig-head" style="width:24%">Final Rating by:</td>
            <td class="sig-head date-col">Date:</td>
        </tr>
        <tr>
            <td class="sig-cell">
                <div class="nm">{{ $sigName($rateeSig) ?: ($ratee->name ?? '') }}</div>
                <div>{{ $form->position_title }}{{ $sg ? " (SG-{$sg})" : '' }}</div>
                <div class="role">Employee</div>
            </td>
            <td class="date-col" style="vertical-align:middle">{{ $approved }}</td>
            <td class="sig-cell">
                <div class="nm">{{ $sigName($reviewerSig) ?: '________' }}</div>
                <div>{{ $sigDesig($reviewerSig) }}</div>
                <div class="role">Supervisor</div>
            </td>
            <td class="date-col" style="vertical-align:middle">{{ $approved }}</td>
            <td class="sig-cell">
                <div class="nm">{{ $sigName($approverSig) ?: '________' }}</div>
                <div>{{ $sigDesig($approverSig) }}</div>
                <div class="role">Supervisor's Rater</div>
            </td>
            <td class="date-col" style="vertical-align:middle">{{ $approved }}</td>
        </tr>
    </table>

    <div class="legend">
        Legend: 1 - Quality (Ql) &nbsp; 2 - Quantity (Qn) &nbsp; 3 - Timeliness (T) &nbsp; 4 - Average (A)
    </div>

    <div class="noprint" style="margin-top:16px;text-align:center">
        <button onclick="window.print()"
            style="background:#0b2a52;color:#fff;border:0;padding:8px 18px;border-radius:6px;cursor:pointer">
            Print
        </button>
    </div>
</body>
</html>
