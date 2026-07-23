@php
    use Illuminate\Support\Carbon;

    /**
     * CS Form No. 6 (Revised 2020) — print-exact reproduction.
     *
     * Every coordinate below is lifted from the official CSC PDF (US Letter,
     * 612x792pt): the table runs x=102.4→538.7, the 6.A/6.B divider sits at
     * x=341.0, checkbox rows step by 12.6pt, and the leave-credit grid columns
     * land on 119.3/189.1/256.5/323.8. Keep these numbers if you edit the
     * layout — they are what make the printout line up with the paper form.
     *
     * Signature lines are deliberately left blank: this is printed and signed
     * by pen.
     */
    $L = 102.4; $R = 538.7; $MID = 341.0;          // table edges + column divider
    $ROW = 12.6;                                    // checkbox row pitch

    $d = fn ($date, $fmt = 'F j, Y') => $date ? Carbon::parse($date)->format($fmt) : '';
    $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');

    $checkedType = $app->leave_type_id;
    $det = fn ($field, $value) => $app->{$field} === $value;

    // Signature blocks are frozen at filing. Older rows predate the snapshot,
    // so fall back to the linked account's name rather than printing nothing.
    $sigOf = fn (?array $sig, $user) => $sig ?: [
        'rank'        => '',
        'name'        => strtoupper((string) ($user?->name ?? '')),
        'branch'      => '',
        'position'    => '',
        'designation' => '',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CS Form No. 6 — {{ $app->applicant_name }}</title>
<style>
    @page { size: 8.5in 11in; margin: 0; }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        padding: 0;
        background: #525659;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
    }

    .sheet {
        position: relative;
        width: 612pt;
        height: 792pt;
        margin: 16pt auto;
        background: #fff;
        overflow: hidden;
    }

    /* Absolutely-positioned primitives. Coordinates are PDF points. */
    .t   { position: absolute; white-space: nowrap; }         /* text */
    .box { position: absolute; border: 0.75pt solid #000; }   /* ruled cell */
    .ln  { position: absolute; border-bottom: 0.6pt solid #000; height: 0; } /* rule */

    /*
     * A ruled row: [checkbox] label ……… value ………
     * Flex keeps the box centred against its label and lets the rule fill the
     * leftover width, so nothing collides no matter how long the label is.
     */
    .row { position: absolute; display: flex; align-items: center; overflow: hidden; }
    .row > .lbl { white-space: nowrap; }

    .cb {                                   /* 6.A / 6.B / 6.D checkbox */
        flex: none;
        width: 7.7pt;
        height: 7.7pt;
        border: 0.75pt solid #000;
        margin-right: 2.4pt;
        font-size: 6.5pt;
        line-height: 6.4pt;
        text-align: center;
        font-weight: bold;
    }

    /* Rule that eats the remaining width; the value rides on top of it. */
    .fill {
        flex: 1 1 auto;
        min-width: 0;
        margin-left: 3pt;
        border-bottom: 0.6pt solid #000;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        font-size: 7pt;
        line-height: 8.4pt;
    }

    .f8   { font-size: 8pt; }
    .f75  { font-size: 7.5pt; }
    .f7   { font-size: 7pt; }
    .fine { font-size: 4.7pt; }             /* the legal-basis fine print */
    .b    { font-weight: bold; }
    .i    { font-style: italic; }
    .c    { text-align: center; }

    /* Values we print into the blanks — distinct enough to read, but still
       black ink so a photocopy of the signed form stays legible. */
    .v { font-family: Arial, Helvetica, sans-serif; }

    .band {                                 /* the "6. DETAILS OF APPLICATION" bars */
        position: absolute;
        border: 0.75pt solid #000;
        text-align: center;
        font-weight: bold;
        font-size: 8.5pt;
    }

    .wrap { white-space: normal; }

    /* Screen-only toolbar */
    .toolbar {
        position: sticky; top: 0; z-index: 10;
        background: #1f2937; color: #fff;
        padding: 10px 16px; text-align: center;
        font-size: 13px;
    }
    .toolbar button, .toolbar a {
        font: inherit; cursor: pointer;
        background: #2563eb; color: #fff;
        border: 0; border-radius: 6px;
        padding: 7px 14px; margin: 0 4px;
        text-decoration: none; display: inline-block;
    }
    .toolbar .ghost { background: #374151; }

    @media print {
        html, body { background: #fff; }
        .toolbar { display: none !important; }
        .sheet { margin: 0; page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }
    }
</style>
</head>
<body>

<div class="toolbar">
    <span style="margin-right:10px">CS Form No. 6 &mdash; {{ $app->applicant_name }}</span>
    <button onclick="window.print()">Print / Save as PDF</button>
    <a class="ghost" href="{{ route('leave.show', $app) }}">Back to application</a>
</div>

{{-- ══════════════════════════════ PAGE 1 ══════════════════════════════ --}}
<div class="sheet">

    {{-- Header --}}
    <div class="t i f7" style="left:{{ $L }}pt; top:60pt;">Civil Service Form No. 6 Revised 2020</div>

    {{-- Service seal (left) and unit seal (right) flank the agency block. --}}
    @if ($logoLeft)
        <img src="{{ $logoLeft }}" alt=""
             style="position:absolute; left:203pt; top:80pt; height:44pt; width:44pt; object-fit:contain;">
    @endif
    @if ($logoRight)
        <img src="{{ $logoRight }}" alt=""
             style="position:absolute; left:395pt; top:80pt; height:44pt; width:44pt; object-fit:contain;">
    @endif

    <div class="t f8 c"                  style="left:250pt; top:84pt; width:142pt;">Republic of the Philippines</div>
    <div class="t b c" style="left:250pt; top:93.5pt; width:142pt; font-size:10pt;">{{ $agencyName }}</div>
    <div class="t f8 c"                  style="left:250pt; top:106pt; width:142pt;">{{ $agencyAddress }}</div>
    @if ($agencyAddress2)
        <div class="t f8 c"              style="left:250pt; top:116pt; width:142pt;">{{ $agencyAddress2 }}</div>
    @endif

    <div class="t b c" style="left:{{ $L }}pt; top:135pt; width:{{ $R - $L }}pt; font-size:12pt;">APPLICATION FOR LEAVE</div>

    {{-- ── Row 1: 1. OFFICE/DEPARTMENT | 2. NAME ── --}}
    <div class="box" style="left:{{ $L }}pt; top:158.4pt; width:{{ $R - $L }}pt; height:28.5pt;"></div>
    <div class="t f75" style="left:106pt; top:161pt;">1.&nbsp;&nbsp; OFFICE/DEPARTMENT</div>
    <div class="t f75" style="left:245pt; top:161pt;">2.&nbsp; NAME :</div>
    <div class="t f75" style="left:305pt; top:161pt;">(Last)</div>
    <div class="t f75" style="left:378pt; top:161pt;">(First)</div>
    <div class="t f75" style="left:446pt; top:161pt;">(Middle)</div>

    <div class="t v f8" style="left:106pt; top:174pt; max-width:135pt; overflow:hidden;">{{ $app->office_department }}</div>
    <div class="t v f8" style="left:294pt; top:174pt; max-width:70pt; overflow:hidden;">{{ $app->applicant_last_name }}</div>
    <div class="t v f8" style="left:370pt; top:174pt; max-width:66pt; overflow:hidden;">{{ $app->applicant_first_name }}</div>
    <div class="t v f8" style="left:440pt; top:174pt; max-width:94pt; overflow:hidden;">{{ $app->applicant_middle_name }}</div>

    {{-- ── Row 2: 3. DATE OF FILING | 4. POSITION | 5. SALARY ── --}}
    <div class="box" style="left:{{ $L }}pt; top:186.9pt; width:{{ $R - $L }}pt; height:22.6pt;"></div>
    <div class="row" style="left:106pt; top:190.5pt; width:128pt; height:15pt;">
        <span class="lbl f75">3.&nbsp;&nbsp; DATE OF FILING</span>
        <span class="fill v">{{ $d($app->date_filing) }}</span>
    </div>

    <div class="row" style="left:245pt; top:190.5pt; width:167pt; height:15pt;">
        <span class="lbl f75">4.&nbsp;&nbsp; POSITION</span>
        <span class="fill v">{{ $app->position }}</span>
    </div>

    <div class="row" style="left:414pt; top:190.5pt; width:120pt; height:15pt;">
        <span class="lbl f75">5.&nbsp; SALARY GRADE</span>
        <span class="fill v">{{ $app->salary }}</span>
    </div>

    {{-- ── Band: 6. DETAILS OF APPLICATION ── --}}
    <div class="band" style="left:{{ $L }}pt; top:210.9pt; width:{{ $R - $L }}pt; height:13.4pt; line-height:12.4pt;">
        6.&nbsp; DETAILS OF APPLICATION
    </div>

    {{-- ── 6.A TYPE OF LEAVE TO BE AVAILED OF ── --}}
    <div class="box" style="left:{{ $L }}pt; top:225.6pt; width:{{ $MID - $L }}pt; height:{{ 447.0 - 225.6 }}pt;"></div>
    <div class="t f75" style="left:106pt; top:228.5pt;">6.A&nbsp; TYPE OF LEAVE TO BE AVAILED OF</div>

    @foreach ($types as $i => $type)
        @php $top = 244.0 + $i * $ROW; @endphp

        @if ($type->code === 'others')
            {{-- "Others:" is a labelled blank, not a checkbox row. --}}
            <div class="t i f75" style="left:119.5pt; top:{{ $top + 3 }}pt;">Others:</div>
            <div class="ln" style="left:119.5pt; top:{{ $top + 20 }}pt; width:150pt;"></div>
            <div class="t v f7 c" style="left:119.5pt; top:{{ $top + 12 }}pt; width:150pt; overflow:hidden;">
                {{ $othersText ?? '' }}
            </div>
        @else
            <div class="row" style="left:109.3pt; top:{{ $top }}pt; width:{{ $MID - 109.3 - 2 }}pt; height:{{ $ROW }}pt;">
                <span class="cb">{{ $checkedType === $type->id ? '✓' : '' }}</span>
                <span class="lbl f75">{{ $type->name }}</span>
                <span class="lbl fine">&nbsp;{{ $type->legal_basis }}</span>
            </div>
        @endif
    @endforeach

    {{-- ── 6.B DETAILS OF LEAVE ── --}}
    <div class="box" style="left:{{ $MID }}pt; top:225.6pt; width:{{ $R - $MID }}pt; height:{{ 447.0 - 225.6 }}pt;"></div>
    <div class="t f75" style="left:344.5pt; top:228.5pt;">6.B&nbsp; DETAILS OF LEAVE</div>

    @php
        // 6.B walks the same 12.6pt rhythm as 6.A.
        $bW = $R - 350.7 - 4.7;              // usable width inside the 6.B cell
        $bRow = fn ($i) => 244.0 + $i * $ROW;
    @endphp

    {{-- In case of Vacation/Special Privilege Leave --}}
    <div class="t i f75" style="left:350.7pt; top:{{ $bRow(0) + 2.5 }}pt;">In case of Vacation/Special Privilege Leave:</div>

    <div class="row" style="left:350.7pt; top:{{ $bRow(1) }}pt; width:{{ $bW }}pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $det('detail_vacation', 'within_philippines') ? '✓' : '' }}</span>
        <span class="lbl f75">Within the Philippines</span>
        <span class="fill v">{{ $det('detail_vacation', 'within_philippines') ? $app->detail_vacation_location : '' }}</span>
    </div>

    <div class="row" style="left:350.7pt; top:{{ $bRow(2) }}pt; width:{{ $bW }}pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $det('detail_vacation', 'abroad') ? '✓' : '' }}</span>
        <span class="lbl f75">Abroad (Specify)</span>
        <span class="fill v">{{ $det('detail_vacation', 'abroad') ? $app->detail_vacation_location : '' }}</span>
    </div>

    {{-- In case of Sick Leave --}}
    <div class="t i f75" style="left:350.7pt; top:{{ $bRow(3) + 2.5 }}pt;">In case of Sick Leave:</div>

    <div class="row" style="left:350.7pt; top:{{ $bRow(4) }}pt; width:{{ $bW }}pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $det('detail_sick', 'in_hospital') ? '✓' : '' }}</span>
        <span class="lbl f75">In Hospital (Specify Illness)</span>
        <span class="fill v">{{ $det('detail_sick', 'in_hospital') ? $app->detail_sick_illness : '' }}</span>
    </div>

    <div class="row" style="left:350.7pt; top:{{ $bRow(5) }}pt; width:{{ $bW }}pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $det('detail_sick', 'out_patient') ? '✓' : '' }}</span>
        <span class="lbl f75">Out Patient (Specify Illness)</span>
        <span class="fill v">{{ $det('detail_sick', 'out_patient') ? $app->detail_sick_illness : '' }}</span>
    </div>

    <div class="ln" style="left:350.7pt; top:{{ $bRow(6) + 8.4 }}pt; width:183.3pt;"></div>

    {{-- In case of Special Leave Benefits for Women --}}
    <div class="t i f75" style="left:350.7pt; top:{{ $bRow(7) + 2.5 }}pt;">In case of Special Leave Benefits for Women:</div>

    <div class="row" style="left:350.7pt; top:{{ $bRow(8) }}pt; width:{{ $bW }}pt; height:{{ $ROW }}pt;">
        <span class="lbl f75">(Specify Illness)</span>
        <span class="fill v">{{ $app->detail_women_illness }}</span>
    </div>
    <div class="ln" style="left:350.7pt; top:{{ $bRow(9) + 8.4 }}pt; width:183.3pt;"></div>

    {{-- In case of Study Leave --}}
    <div class="t i f75" style="left:350.7pt; top:{{ $bRow(10) + 2.5 }}pt;">In case of Study Leave:</div>

    <div class="row" style="left:350.7pt; top:{{ $bRow(11) }}pt; width:{{ $bW }}pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $det('detail_study', 'masters') ? '✓' : '' }}</span>
        <span class="lbl f75">Completion of Master's Degree</span>
    </div>

    <div class="row" style="left:350.7pt; top:{{ $bRow(12) }}pt; width:{{ $bW }}pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $det('detail_study', 'bar_board') ? '✓' : '' }}</span>
        <span class="lbl f75">BAR/Board Examination Review</span>
    </div>

    <div class="t i f75" style="left:350.7pt; top:{{ $bRow(13) + 2.5 }}pt;">Other purpose:</div>

    <div class="row" style="left:350.7pt; top:{{ $bRow(14) }}pt; width:{{ $bW }}pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $det('detail_other_purpose', 'monetization') ? '✓' : '' }}</span>
        <span class="lbl f75">Monetization of Leave Credits</span>
    </div>

    <div class="row" style="left:350.7pt; top:{{ $bRow(15) }}pt; width:{{ $bW }}pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $det('detail_other_purpose', 'terminal') ? '✓' : '' }}</span>
        <span class="lbl f75">Terminal Leave</span>
    </div>

    {{-- ── 6.C NUMBER OF WORKING DAYS APPLIED FOR ── --}}
    <div class="box" style="left:{{ $L }}pt; top:447pt; width:{{ $MID - $L }}pt; height:{{ 509.6 - 447.0 }}pt;"></div>
    <div class="t f75" style="left:106pt; top:450pt;">6.C&nbsp; NUMBER OF WORKING DAYS APPLIED FOR</div>
    <div class="ln" style="left:126pt; top:470pt; width:200pt;"></div>
    <div class="t v f8 c" style="left:126pt; top:461.5pt; width:200pt;">{{ $num($app->working_days) }}</div>

    <div class="t f75" style="left:122pt; top:476pt;">INCLUSIVE DATES</div>
    <div class="ln" style="left:126pt; top:495pt; width:200pt;"></div>
    <div class="t v f8 c" style="left:126pt; top:486.5pt; width:200pt; overflow:hidden;">{{ $app->inclusive_dates_text }}</div>

    {{-- ── 6.D COMMUTATION ── --}}
    <div class="box" style="left:{{ $MID }}pt; top:447pt; width:{{ $R - $MID }}pt; height:{{ 509.6 - 447.0 }}pt;"></div>
    <div class="t f75" style="left:344.5pt; top:450pt;">6.D&nbsp; COMMUTATION</div>

    <div class="row" style="left:350.7pt; top:461.5pt; width:120pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $app->commutation === 'not_requested' ? '✓' : '' }}</span>
        <span class="lbl f75">Not Requested</span>
    </div>

    <div class="row" style="left:350.7pt; top:474.1pt; width:120pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $app->commutation === 'requested' ? '✓' : '' }}</span>
        <span class="lbl f75">Requested</span>
    </div>

    {{-- Applicant signs here by pen. --}}
    @include('leave._sigblock', [
        'sig' => $app->applicant_sig ?: [
            'rank'   => '',
            'name'   => strtoupper((string) $app->applicant_name),
            'branch' => '',
        ],
        'left'    => $MID + 5,
        'width'   => 190,
        'top'     => 485,
        'caption' => '(Signature of Applicant)',
    ])

    {{-- ── Band: 7. DETAILS OF ACTION ON APPLICATION ── --}}
    <div class="band" style="left:{{ $L }}pt; top:510.9pt; width:{{ $R - $L }}pt; height:13.5pt; line-height:12.5pt;">
        7.&nbsp; DETAILS OF ACTION ON APPLICATION
    </div>

    {{-- ── 7.A CERTIFICATION OF LEAVE CREDITS ── --}}
    <div class="box" style="left:{{ $L }}pt; top:525.7pt; width:{{ $MID - $L }}pt; height:{{ 629.0 - 525.7 }}pt;"></div>
    <div class="t f75" style="left:106pt; top:528.5pt;">7.A&nbsp; CERTIFICATION OF LEAVE CREDITS</div>

    <div class="row" style="left:156pt; top:538.5pt; width:130pt; height:13pt;">
        <span class="lbl f75">As of</span>
        <span class="fill v">{{ $d($app->cert_as_of) }}</span>
    </div>

    {{-- Leave-credit grid: columns land on 119.3 / 189.1 / 256.5 / 323.8 --}}
    @php
        $gTop = 554.3; $gPitch = 9.1;
        $cols = [119.3, 189.1, 256.5, 323.8];
    @endphp
    <div class="box" style="left:119.3pt; top:{{ $gTop }}pt; width:{{ 323.8 - 119.3 }}pt; height:{{ $gPitch * 4 }}pt;"></div>
    {{-- inner verticals --}}
    <div style="position:absolute; left:189.1pt; top:{{ $gTop }}pt; height:{{ $gPitch * 4 }}pt; border-left:0.6pt solid #000;"></div>
    <div style="position:absolute; left:256.5pt; top:{{ $gTop }}pt; height:{{ $gPitch * 4 }}pt; border-left:0.6pt solid #000;"></div>
    {{-- inner horizontals --}}
    @for ($r = 1; $r < 4; $r++)
        <div class="ln" style="left:119.3pt; top:{{ $gTop + $r * $gPitch }}pt; width:{{ 323.8 - 119.3 }}pt;"></div>
    @endfor

    {{-- grid header --}}
    <div class="t f7 c" style="left:189.1pt; top:{{ $gTop + 1.6 }}pt; width:{{ 256.5 - 189.1 }}pt;">Vacation Leave</div>
    <div class="t f7 c" style="left:256.5pt; top:{{ $gTop + 1.6 }}pt; width:{{ 323.8 - 256.5 }}pt;">Sick Leave</div>

    @php
        $rows = [
            ['Total Earned',        $app->vl_earned,  $app->sl_earned],
            ['Less this application', $app->vl_less,  $app->sl_less],
            ['Balance',             $app->vl_balance, $app->sl_balance],
        ];
    @endphp
    @foreach ($rows as $r => [$label, $vl, $sl])
        @php $ry = $gTop + ($r + 1) * $gPitch; @endphp
        <div class="t i f7 c" style="left:119.3pt; top:{{ $ry + 1.6 }}pt; width:{{ 189.1 - 119.3 }}pt;">{{ $label }}</div>
        <div class="t v f7 c" style="left:189.1pt; top:{{ $ry + 1.6 }}pt; width:{{ 256.5 - 189.1 }}pt;">{{ $num($vl) }}</div>
        <div class="t v f7 c" style="left:256.5pt; top:{{ $ry + 1.6 }}pt; width:{{ 323.8 - 256.5 }}pt;">{{ $num($sl) }}</div>
    @endforeach

    @include('leave._sigblock', [
        'sig'     => $sigOf($app->hr_officer_sig, $app->hrOfficer),
        'left'    => 120,
        'width'   => 204,
        'top'     => 594,
        'caption' => '(Authorized Officer)',
    ])

    {{-- ── 7.B RECOMMENDATION ── --}}
    <div class="box" style="left:{{ $MID }}pt; top:525.7pt; width:{{ $R - $MID }}pt; height:{{ 629.0 - 525.7 }}pt;"></div>
    <div class="t f75" style="left:344.5pt; top:528.5pt;">7.B&nbsp; RECOMMENDATION</div>

    <div class="row" style="left:350.7pt; top:539pt; width:120pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $app->recommendation === 'approval' ? '✓' : '' }}</span>
        <span class="lbl f75">For approval</span>
    </div>

    {{-- Reason: first line runs beside the label, the rest use the ruled lines. --}}
    @php
        $reason = $app->recommendation === 'disapproval' ? (string) $app->recommendation_reason : '';
        $reasonLines = $reason === '' ? [] : explode("\n", wordwrap($reason, 40, "\n", true));
    @endphp

    <div class="row" style="left:350.7pt; top:551.6pt; width:{{ $R - 350.7 - 4.7 }}pt; height:{{ $ROW }}pt;">
        <span class="cb">{{ $app->recommendation === 'disapproval' ? '✓' : '' }}</span>
        <span class="lbl f75">For disapproval due to</span>
        <span class="fill v">{{ $reasonLines[0] ?? '' }}</span>
    </div>

    @for ($i = 0; $i < 3; $i++)
        <div class="ln" style="left:350.7pt; top:{{ 572.5 + $i * 9.1 }}pt; width:183.3pt;"></div>
        <div class="t v f7" style="left:352pt; top:{{ 565.5 + $i * 9.1 }}pt; width:181pt; overflow:hidden;">{{ $reasonLines[$i + 1] ?? '' }}</div>
    @endfor

    @include('leave._sigblock', [
        'sig'     => $sigOf($app->recommender_sig, null),   {{-- typed in by the admin, never linked to an account --}}
        'left'    => $MID + 5,
        'width'   => 190,
        'top'     => 594,
        'caption' => '(Authorized Officer)',
    ])

    {{-- ── 7.C APPROVED FOR / 7.D DISAPPROVED DUE TO ── --}}
    <div class="box" style="left:{{ $L }}pt; top:629pt; width:{{ $R - $L }}pt; height:{{ 721.8 - 629.0 }}pt;"></div>

    <div class="t f75" style="left:106pt; top:632pt;">7.C&nbsp; APPROVED FOR:</div>
    @php
        $approved = $app->decision === 'approved';
        $payRows = [
            [$app->days_with_pay,    'days with pay'],
            [$app->days_without_pay, 'days without pay'],
            [$app->days_others,      'others (Specify)'],
        ];
    @endphp
    @foreach ($payRows as $i => [$val, $label])
        @php $py = 642.5 + $i * 9.6; @endphp
        <div class="ln" style="left:126pt; top:{{ $py + 7.4 }}pt; width:36pt;"></div>
        <div class="t v f75 c" style="left:126pt; top:{{ $py }}pt; width:36pt;">{{ $approved ? $num($val) : '' }}</div>
        <div class="t f75" style="left:166pt; top:{{ $py }}pt;">{{ $label }}</div>
    @endforeach
    {{-- 7.C "others (Specify)" free text --}}
    <div class="t v f7" style="left:236pt; top:661.7pt; width:96pt; overflow:hidden;">{{ $approved ? $app->days_others_specify : '' }}</div>

    <div class="t f75" style="left:{{ $MID + 3.5 }}pt; top:632pt;">7.D&nbsp;&nbsp; DISAPPROVED DUE TO:</div>
    @php
        $dis = $app->decision === 'disapproved' ? (string) $app->disapproval_reason : '';
        $disLines = $dis === '' ? [] : explode("\n", wordwrap($dis, 44, "\n", true));
    @endphp
    @for ($i = 0; $i < 4; $i++)
        <div class="ln" style="left:350.7pt; top:{{ 642.5 + $i * 9.2 }}pt; width:183.3pt;"></div>
        <div class="t v f7" style="left:352pt; top:{{ 635.5 + $i * 9.2 }}pt; width:181pt; overflow:hidden;">{{ $disLines[$i] ?? '' }}</div>
    @endfor

    {{-- Approving official signs by pen. --}}
    @include('leave._sigblock', [
        'sig'     => $sigOf($app->approver_sig, $app->approver),
        'left'    => 216,
        'width'   => 199,
        'top'     => 686,
        'caption' => '(Authorized Official)',
    ])

</div>

</body>
</html>
