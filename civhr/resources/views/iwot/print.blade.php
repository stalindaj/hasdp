@php
    /**
     * The official IWOT sheet: three header lines (name / position+SG / unit),
     * the performance-standards matrix, then PREPARED BY (employee) and
     * APPROVED BY (NCOIC) with room for a signature over each name.
     *
     * Landscape — the matrix is eleven columns wide on the office template.
     */
    $employee = $form->employee;
    $emp = $employee?->employee;
    $sg = $emp?->salary_grade;

    $block = $employee?->signatoryBlock();
    $employeeName = $block
        ? trim(implode(' ', array_filter([$block['rank'] ?? '', $block['name'] ?? '', $block['branch'] ?? ''])))
        : ($employee->name ?? '');

    $position = trim(($form->position_title ?: '').($sg ? "/ SG-{$sg}" : ''));

    $bands = [
        'outstanding' => 'Outstanding',
        'very_satisfactory' => 'Very Satisfactory',
        'satisfactory' => 'Satisfactory',
        'unsatisfactory' => 'Unsatisfactory',
        'poor' => 'Poor',
    ];

    $footer = [
        'prepared' => ['label' => 'PREPARED BY:', 'name' => $form->prepared_by ?: $employeeName, 'desig' => $form->prepared_designation ?: 'Employee'],
        'approved' => ['label' => 'APPROVED BY:', 'name' => $form->approved_by, 'desig' => $form->approved_designation ?: 'NCOIC'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>IWOT — {{ $employeeName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Times New Roman', Times, serif; color: #000; background: #f3f4f6; margin: 0; padding: 16px; font-size: 9pt; }
        .sheet { width: 11in; margin: 0 auto; background: #fff; padding: 0.4in; box-shadow: 0 2px 14px rgba(0,0,0,.15); }

        @page { size: Letter landscape; margin: 10mm; }

        /* Name, then position and unit underlined — the template's header. */
        .who { text-align: center; margin-bottom: 12px; }
        .who .nm { font-weight: 700; font-size: 10.5pt; }
        .who .ps, .who .of { font-size: 9.5pt; text-decoration: underline; }
        .who .pd { font-size: 9pt; font-style: italic; }

        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 4px 5px; vertical-align: top; }
        th { text-align: center; font-weight: 700; vertical-align: middle; }
        .measure { text-align: center; vertical-align: middle; font-weight: 600; }

        .foot { margin-top: 26px; width: 100%; border: 0; }
        .foot td { border: 0; padding: 0; vertical-align: top; width: 50%; }
        .foot .label { font-weight: 700; }
        .foot .sigbox { position: relative; height: 0.85in; }
        .foot .sigbox img { position: absolute; left: 0; bottom: 2px; max-height: 0.62in; max-width: 3in; }
        /* Names print exactly as typed — "Civ HR" must not become "CIV HR". */
        .foot .nm { font-weight: 700; }
        .foot .dg { font-size: 8.5pt; }

        .noprint { text-align: center; margin: 14px 0; }
        .noprint button, .noprint a {
            background: #0b2a52; color: #fff; border: 0; border-radius: 6px;
            padding: 8px 18px; margin: 0 4px; cursor: pointer; font-family: Arial, sans-serif;
            font-size: 12px; text-decoration: none; display: inline-block;
        }
        .noprint .ghost { background: #374151; }

        .sigup {
            display: inline-block; margin-top: 3px;
            font-family: Arial, Helvetica, sans-serif; font-size: 7pt; line-height: 1;
            padding: 2px 5px; border: 0; border-radius: 3px;
            background: #2563eb; color: #fff; cursor: pointer;
        }
        .sigup.rm { background: #6b7280; }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { width: auto; box-shadow: none; padding: 0; }
            .noprint, .sigup { display: none !important; }
        }
    </style>
</head>
<body>
<div class="sheet">

    <div class="who">
        <div class="nm">{{ $employeeName }}</div>
        <div class="ps">{{ $position }}</div>
        <div class="of">{{ $form->office_unit }}</div>
        @if ($form->rating_period)
            <div class="pd">{{ $form->rating_period }}</div>
        @endif
    </div>

    <table>
        <tr>
            <th rowspan="2" style="width:16%">MAJOR FINAL OUTPUT</th>
            <th rowspan="2" style="width:9%">TIMELINESS</th>
            <th style="width:9%">PERFORMANCE MEASURES</th>
            <th style="width:11%">PERFORMANCE TARGETS</th>
            <th rowspan="2" style="width:16%">SUCCESS INDICATOR<br><span style="font-weight:400;font-style:italic">Measures+Targets</span></th>
            <th colspan="5">PERFORMANCE STANDARDS</th>
        </tr>
        <tr>
            <th style="font-weight:400;font-style:italic">(Measures)</th>
            <th style="font-weight:400;font-style:italic">(Targets)</th>
            @foreach ($bands as $label)
                <th style="width:7.8%">{{ $label }}</th>
            @endforeach
        </tr>

        @forelse ($form->groups as $g)
            @php $rows = $g->rows->values(); $span = max($rows->count(), 1); @endphp
            @foreach ($rows as $mi => $row)
                <tr>
                    @if ($mi === 0)
                        <td rowspan="{{ $span }}">{{ $g->major_final_output }}</td>
                        <td rowspan="{{ $span }}">{{ $g->timeliness }}</td>
                    @endif
                    <td class="measure">{{ $row->performance_measure }}</td>
                    <td>{{ $row->performance_targets }}</td>
                    @if ($mi === 0)
                        <td rowspan="{{ $span }}" style="vertical-align:middle">{{ $g->success_indicator }}</td>
                    @endif
                    @foreach ($bands as $column => $label)
                        <td>{{ $row->$column }}</td>
                    @endforeach
                </tr>
            @endforeach
        @empty
            <tr><td colspan="10" style="text-align:center;color:#777">No major final outputs recorded.</td></tr>
        @endforelse
    </table>

    <table class="foot">
        <tr>
            @foreach ($footer as $slot => $f)
                <td>
                    <div class="label">{{ $f['label'] }}</div>
                    <div class="sigbox">
                        @if (! empty($signatures[$slot]))
                            <img src="{{ $signatures[$slot] }}" alt="">
                        @endif
                    </div>
                    <div class="nm">{{ $f['name'] ?: '________________________' }}</div>
                    <div class="dg">{{ $f['desig'] }}</div>
                    @if ($canSign && ($signable[$slot] ?? false))
                        <button type="button" class="sigup" onclick="pickSig('{{ $slot }}')">
                            {{ !empty($form->signature_uploads[$slot]) ? '✎ Replace signature' : '✎ Upload signature' }}
                        </button>
                        @if (!empty($form->signature_uploads[$slot]))
                            <button type="button" class="sigup rm" onclick="removeSig('{{ $slot }}')" title="Remove signature">✕</button>
                        @endif
                    @endif
                </td>
            @endforeach
        </tr>
    </table>
</div>

<div class="noprint">
    <button onclick="window.print()">Print IWOT</button>
    <a class="ghost" href="{{ route('iwot.show', $form) }}">Back to the IWOT</a>
</div>

@include('partials.signature-picker', [
    'base' => parse_url(url('iwot/'.$form->id.'/signature'), PHP_URL_PATH),
    'canSign' => $canSign,
])
</body>
</html>
