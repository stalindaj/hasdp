<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>IPCR — {{ $form->ratee->name ?? '' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #111; margin: 24px; font-size: 12px; }
        .head { text-align: center; margin-bottom: 16px; }
        .head h1 { font-size: 16px; margin: 0; color: #0b2a52; }
        .head .sub { font-size: 11px; color: #555; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .meta td { padding: 3px 6px; }
        .meta .k { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #666; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.grid th, table.grid td { border: 1px solid #999; padding: 5px 6px; vertical-align: top; }
        table.grid th { background: #0b2a52; color: #fff; font-size: 10px; text-transform: uppercase; }
        .rate { text-align: center; width: 48px; }
        .overall { background: #0b2a52; color: #fff; padding: 8px 12px; display: flex;
                   justify-content: space-between; font-weight: 700; }
        .overall .adj { color: #c9a341; }
        .sigs { display: flex; gap: 24px; margin-top: 28px; }
        .sig { flex: 1; text-align: center; font-size: 11px; }
        .sig .line { border-top: 1px solid #333; margin-top: 34px; padding-top: 4px; }
        @media print { body { margin: 12mm; } .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="head">
        <h1>Individual Performance Commitment and Review (IPCR)</h1>
        <div class="sub">15th Strike Wing — Civilian Personnel Management System</div>
    </div>

    <table class="meta">
        <tr>
            <td class="k">Ratee</td><td>{{ $form->ratee->name ?? '—' }}</td>
            <td class="k">Rating period</td><td>{{ $form->rating_period }}</td>
        </tr>
        <tr>
            <td class="k">Position</td><td>{{ $form->position_title ?: '—' }}</td>
            <td class="k">Office / Unit</td><td>{{ $form->office_unit ?: '—' }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width:26%">Major Final Output</th>
                <th style="width:24%">Success Indicator</th>
                <th style="width:26%">Actual Accomplishment</th>
                <th class="rate">Q</th>
                <th class="rate">T</th>
                <th class="rate">Qn</th>
                <th class="rate">Ave</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($form->groups as $g)
                <tr>
                    <td>{{ $g->major_final_output }}</td>
                    <td>{{ $g->success_indicator }}</td>
                    <td>{{ $g->actual_accomplishment }}</td>
                    <td class="rate">{{ $g->quality_rating ?? '' }}</td>
                    <td class="rate">{{ $g->timeliness_rating ?? '' }}</td>
                    <td class="rate">{{ $g->quantity_rating ?? '' }}</td>
                    <td class="rate"><strong>{{ $g->average_rating ?? '' }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:#777">No entries.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="overall">
        <span>OVERALL RATING</span>
        <span>{{ $form->overall_rating ?? '—' }}
            <span class="adj">{{ $form->fe_overall_adjectival_rating }}</span>
        </span>
    </div>

    <div class="sigs">
        <div class="sig"><div class="line">{{ $form->prepared_by ?: '&nbsp;' }}<br>Prepared / Ratee</div></div>
        <div class="sig"><div class="line">{{ $form->fe_reviewed_by ?: '&nbsp;' }}<br>Reviewed by</div></div>
        <div class="sig"><div class="line">{{ $form->approved_by ?: '&nbsp;' }}<br>Approved by</div></div>
    </div>

    <div class="noprint" style="margin-top:24px;text-align:center">
        <button onclick="window.print()"
            style="background:#0b2a52;color:#fff;border:0;padding:8px 18px;border-radius:6px;cursor:pointer">
            Print
        </button>
    </div>
</body>
</html>
