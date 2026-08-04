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

    /**
     * Shrink a value just enough to fit its blank, instead of clipping it —
     * a long name like "Justin Gerrick Elmon" must still print in full.
     * Arial averages ~0.5em per character at these sizes; the floor keeps it
     * legible on a photocopy.
     */
    $fit = function (?string $text, float $widthPt, float $base = 8.0) {
        $len = mb_strlen(trim((string) $text));
        if ($len === 0) {
            return $base;
        }

        $needed = $len * 0.5 * $base;

        return $needed > $widthPt
            ? max(4.8, round($base * $widthPt / $needed, 2))
            : $base;
    };

    $checkedType = $app->leave_type_id;
    $det = fn ($field, $value) => $app->{$field} === $value;

    /**
     * Signature blocks are frozen at filing. Older rows predate the snapshot,
     * so fall back to the linked account's name rather than printing nothing.
     *
     * The e-signature is resolved live from the signatory's account (not
     * frozen), so uploading one later fixes every form at once. $signed says
     * whether that block's act has actually happened — a signature must never
     * print on a step nobody has taken yet.
     */
    $sigOf = function (?array $sig, $user, bool $signed = false, ?string $slot = null) use ($app) {
        $block = $sig ?: [
            'rank'        => '',
            'name'        => strtoupper((string) ($user?->name ?? '')),
            'branch'      => '',
            'position'    => '',
            'designation' => '',
        ];

        // A signature uploaded onto this very form wins — it also covers
        // signatories with no account (e.g. 7.B). Otherwise fall back to the
        // signatory's own account e-signature, once that step has been taken.
        $uploaded = $slot && ! empty(($app->signature_uploads ?? [])[$slot]);

        // Root-relative paths only: an absolute URL built from APP_URL would
        // point at the wrong host/port whenever the app is served somewhere
        // else (e.g. the dev server on :8123), leaving broken-image boxes.
        $block['signature'] = $uploaded
            ? parse_url(route('leave.block-signature', [$app, $slot]), PHP_URL_PATH).'?v='.substr(md5($app->signature_uploads[$slot]), 0, 8)
            : ($signed && $user?->signature_path ? parse_url(route('signature.show', $user), PHP_URL_PATH) : null);

        return $block;
    };

    // 7.A is signed once the credits are certified; 7.C/7.D once decided; the
    // applicant signs 6.D by filing.
    $certified = (bool) ($app->certified_at || $app->cert_as_of);
    $decided   = $app->decision !== null;
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

    /* On-screen "sign here" controls — never printed. Each sits at a signature
       block and opens the file picker to drop an image over the name. */
    .sigup {
        position: absolute; z-index: 6;
        display: inline-flex; align-items: center; gap: 3px;
        font-family: Arial, Helvetica, sans-serif; font-size: 7pt; line-height: 1;
        padding: 2.5pt 4pt; border: 0; border-radius: 3pt;
        background: #2563eb; color: #fff; cursor: pointer; white-space: nowrap;
        box-shadow: 0 1px 2px rgba(0,0,0,.25);
    }
    .sigup:hover { background: #1d4ed8; }
    .sigup.rm { background: #6b7280; padding: 2.5pt 3.5pt; }
    .sigup.rm:hover { background: #4b5563; }

    @media print {
        html, body { background: #fff; }
        .toolbar { display: none !important; }
        .sigup { display: none !important; }
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

    <div class="t v" style="left:106pt; top:174pt; width:135pt; overflow:hidden; font-size:{{ $fit($app->office_department, 135) }}pt;">{{ $app->office_department }}</div>
    <div class="t v" style="left:294pt; top:174pt; width:70pt; overflow:hidden; font-size:{{ $fit($app->applicant_last_name, 70) }}pt;">{{ $app->applicant_last_name }}</div>
    <div class="t v" style="left:370pt; top:174pt; width:66pt; overflow:hidden; font-size:{{ $fit($app->applicant_first_name, 66) }}pt;">{{ $app->applicant_first_name }}</div>
    <div class="t v" style="left:440pt; top:174pt; width:94pt; overflow:hidden; font-size:{{ $fit($app->applicant_middle_name, 94) }}pt;">{{ $app->applicant_middle_name }}</div>

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

    {{-- The applicant signs 6.D by filing the application. --}}
    @include('leave._sigblock', [
        'sig' => $sigOf($app->applicant_sig ?: [
            'rank'   => '',
            'name'   => strtoupper((string) $app->applicant_name),
            'branch' => '',
        ], $app->user, true, 'applicant'),
        'left'    => $MID + 5,
        'width'   => 190,
        'top'     => 485,
        'caption' => '(Signature of Applicant)',
        // The applicant's name already prints in box 2; 6.D is just their
        // signature over the line.
        'hideName' => true,
    ])

    {{-- ── Band: 7. DETAILS OF ACTION ON APPLICATION ── --}}
    <div class="band" style="left:{{ $L }}pt; top:510.9pt; width:{{ $R - $L }}pt; height:13.5pt; line-height:12.5pt;">
        7.&nbsp; DETAILS OF ACTION ON APPLICATION
    </div>

    {{-- ── 7.A CERTIFICATION OF LEAVE CREDITS ── --}}
    <div class="box" style="left:{{ $L }}pt; top:525.7pt; width:{{ $MID - $L }}pt; height:{{ 629.0 - 525.7 }}pt;"></div>
    <div class="t f75" style="left:106pt; top:528.5pt;">7.A&nbsp; CERTIFICATION OF LEAVE CREDITS</div>

    <div class="row" style="left:156pt; top:538pt; width:130pt; height:13pt;">
        <span class="lbl f75">As of</span>
        <span class="fill v">{{ $d($cert['cert_as_of'], 'j F Y') }}</span>
    </div>

    {{-- Leave-credit grid: columns land on 119.3 / 189.1 / 256.5 / 323.8.
         Rows stay a readable height; the grid is nudged up and the certifier's
         name pushed down (top:600) so a proper signature band fits in the gap
         between the balances and the name, clear of the numbers. --}}
    @php
        $gTop = 550; $gPitch = 8.5;
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
        // The figures come from the ledger (an admin's saved ones win), so the
        // block is filled in from the moment the leave is filed rather than
        // waiting to be retyped. "Less this application" prints a dash on the
        // side this leave does not draw from — a dash means "not charged here",
        // which is not the same as a zero.
        $less = fn ($v) => $v !== null ? $num($v) : '—';

        $rows = [
            ['Total Earned',          $num($cert['vl_earned']),  $num($cert['sl_earned'])],
            ['Less this application', $less($cert['vl_less']),   $less($cert['sl_less'])],
            ['Balance',               $num($cert['vl_balance']), $num($cert['sl_balance'])],
        ];
    @endphp
    @foreach ($rows as $r => [$label, $vl, $sl])
        @php $ry = $gTop + ($r + 1) * $gPitch; @endphp
        <div class="t i f7 c" style="left:119.3pt; top:{{ $ry + 1.6 }}pt; width:{{ 189.1 - 119.3 }}pt;">{{ $label }}</div>
        <div class="t v f7 c" style="left:189.1pt; top:{{ $ry + 1.6 }}pt; width:{{ 256.5 - 189.1 }}pt;">{{ $vl }}</div>
        <div class="t v f7 c" style="left:256.5pt; top:{{ $ry + 1.6 }}pt; width:{{ 323.8 - 256.5 }}pt;">{{ $sl }}</div>
    @endforeach

    @include('leave._sigblock', [
        'sig'     => $sigOf($app->hr_officer_sig, $app->hrOfficer, $certified, 'certifier'),
        'left'    => 120,
        'width'   => 204,
        'top'     => 602.5,
        'caption' => '(Authorized Officer)',
        // Tight cell. The ink sits ABOVE the printed name, in the gap the grid
        // leaves — so every point of that gap is worth having. The name stack
        // is pushed as far down as the cell bottom (629pt) allows and its lines
        // tightened, which buys the band 16.5pt between the grid (ends 584pt)
        // and the name. Uploads are cropped to the ink on the way in, so that
        // band is filled by signature rather than by blank paper.
        'sigHeight' => 16.5,
        'sigGapAbove' => 1.0,
        // Never up into the grid, whatever the signatory's name stack does.
        'sigTopMinArg' => $gTop + $gPitch * 4 + 1,
        'nameGapArg' => 6.2,
        'stepArg' => 5.2,
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
        'sig'     => $sigOf($app->recommender_sig, null, false, 'recommender'),   {{-- typed in by the admin, never linked to an account --}}
        'left'    => $MID + 5,
        'width'   => 190,
        'top'     => 594,
        'caption' => '(Authorized Officer)',
        'signedOn' => $app->recommended_at ? $d($app->recommended_at, 'd M Y') : null,
    ])

    {{-- ── 7.C APPROVED FOR / 7.D DISAPPROVED DUE TO ── --}}
    <div class="box" style="left:{{ $L }}pt; top:629pt; width:{{ $R - $L }}pt; height:{{ 721.8 - 629.0 }}pt;"></div>

    <div class="t f75" style="left:106pt; top:632pt;">7.C&nbsp; APPROVED FOR:</div>
    @php
        $approved = $app->decision === 'approved';
        // A saved draft prints its figures too, so the admin can proof the
        // whole form before approving. Once decided, only the matching block
        // prints. saveDraft() keeps the two sides mutually exclusive.
        $decided  = $app->decision !== null;
        $showPay  = $approved || ! $decided;
        $payRows = [
            [$app->days_with_pay,    'days with pay'],
            [$app->days_without_pay, 'days without pay'],
            [$app->days_others,      'others (Specify)'],
        ];
    @endphp
    @foreach ($payRows as $i => [$val, $label])
        @php $py = 642.5 + $i * 9.6; @endphp
        <div class="ln" style="left:126pt; top:{{ $py + 7.4 }}pt; width:36pt;"></div>
        <div class="t v f75 c" style="left:126pt; top:{{ $py }}pt; width:36pt;">{{ $showPay ? $num($val) : '' }}</div>
        <div class="t f75" style="left:166pt; top:{{ $py }}pt;">{{ $label }}</div>
    @endforeach
    {{-- 7.C "others (Specify)" free text --}}
    <div class="t v f7" style="left:236pt; top:661.7pt; width:96pt; overflow:hidden;">{{ $showPay ? $app->days_others_specify : '' }}</div>

    <div class="t f75" style="left:{{ $MID + 3.5 }}pt; top:632pt;">7.D&nbsp;&nbsp; DISAPPROVED DUE TO:</div>
    @php
        $dis = ! $approved ? (string) $app->disapproval_reason : '';
        $disLines = $dis === '' ? [] : explode("\n", wordwrap($dis, 44, "\n", true));
    @endphp
    @for ($i = 0; $i < 4; $i++)
        <div class="ln" style="left:350.7pt; top:{{ 642.5 + $i * 9.2 }}pt; width:183.3pt;"></div>
        <div class="t v f7" style="left:352pt; top:{{ 635.5 + $i * 9.2 }}pt; width:181pt; overflow:hidden;">{{ $disLines[$i] ?? '' }}</div>
    @endfor

    {{-- Approving official signs by pen. --}}
    @include('leave._sigblock', [
        'sig'     => $sigOf($app->approver_sig, $app->approver, $decided, 'approver'),
        'left'    => 216,
        'width'   => 199,
        'top'     => 686,
        'caption' => '(Authorized Official)',
        'signedOn' => $decided ? $d($app->decided_at, 'd M Y') : null,
    ])

    {{-- ── On-screen signing controls (never printed) ──
         One per block, sitting just right of its name, so a signature image can
         be dropped straight onto the form. --}}
    @if ($canSign)
        @php
            $sigButtons = [
                ['slot' => 'applicant',   'left' => 468, 'top' => 484],
                ['slot' => 'certifier',   'left' => 262, 'top' => 594],
                ['slot' => 'recommender', 'left' => 468, 'top' => 594],
                ['slot' => 'approver',    'left' => 352, 'top' => 686],
            ];
        @endphp
        @foreach ($sigButtons as $b)
            @continue (! ($signable[$b['slot']] ?? false))
            @php $has = ! empty(($app->signature_uploads ?? [])[$b['slot']]); @endphp
            <button type="button" class="sigup"
                    style="left:{{ $b['left'] }}pt; top:{{ $b['top'] }}pt;"
                    onclick="pickSig('{{ $b['slot'] }}')">{{ $has ? '✎ Replace signature' : '✎ Upload signature' }}</button>
            @if ($has)
                <button type="button" class="sigup rm"
                        style="left:{{ $b['left'] - 12 }}pt; top:{{ $b['top'] }}pt;"
                        title="Remove signature"
                        onclick="removeSig('{{ $b['slot'] }}')">✕</button>
            @endif
        @endforeach
    @endif

</div>

@if ($canSign)
    <input type="file" id="sig-file" accept="image/png,image/jpeg,image/webp" style="display:none">
    <script>
        (function () {
            const token = @json(csrf_token());
            // Path only — resolves against the current origin, so it works on
            // the dev server (:8123) and in production alike.
            const base = @json(parse_url(url('leave/'.$app->id.'/signature'), PHP_URL_PATH));
            const input = document.getElementById('sig-file');
            let slot = null;

            window.pickSig = function (s) { slot = s; input.value = ''; input.click(); };

            // Pull the clearest message out of whatever the server returned —
            // a validation JSON, plain text, or just a status code.
            function explain(r, body) {
                // PHP may print a warning ahead of the JSON, so parse from the
                // first brace rather than trusting the whole body.
                const start = body.indexOf('{');
                if (start !== -1) {
                    try {
                        const j = JSON.parse(body.slice(start));
                        if (j.errors && j.errors.signature) return j.errors.signature[0];
                        if (j.message) return j.message;
                    } catch (e) { /* not JSON after all */ }
                }
                if (/unable to create a temporary file/i.test(body)) {
                    return 'The server could not store the upload: PHP has no writable temp directory '
                        + '(set upload_tmp_dir in php.ini). No file was saved.';
                }
                if (r.status === 419) return 'Your session expired — reload the page and try again.';
                if (r.status === 413) return 'That image is too large for the server. Try one under 8 MB.';
                return 'Upload failed (' + r.status + '). Use a PNG, JPG or WEBP under 8 MB.';
            }

            input.addEventListener('change', function () {
                if (!input.files.length || !slot) return;
                const fd = new FormData();
                fd.append('signature', input.files[0]);
                fetch(base + '/' + slot, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: fd,
                }).then(function (r) {
                    if (r.ok || r.redirected) { location.reload(); return; }
                    return r.text().then(function (body) { alert(explain(r, body)); });
                }).catch(function () {
                    alert('Could not reach the server. Check your connection and try again.');
                });
            });

            window.removeSig = function (s) {
                if (!confirm('Remove this signature from the form?')) return;
                fetch(base + '/' + s, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                }).then(function (r) { if (r.ok || r.redirected) location.reload(); });
            };
        })();
    </script>
@endif

</body>
</html>
