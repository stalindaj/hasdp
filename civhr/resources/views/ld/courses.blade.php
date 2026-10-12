{{--
    Table 2 of the HR report — Civilian Human Resources Sent to Local Career
    Courses and Seminars — built from the approved career-course filings,
    laid out like the report's Word table so it can be printed or copied in.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Table 2 — Local Career Courses and Seminars {{ $year }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #f3f4f6; margin: 0; padding: 16px; font-size: 11pt; }
        .sheet { width: 210mm; min-height: 120mm; margin: 0 auto; background: #fff; padding: 15mm; box-shadow: 0 2px 14px rgba(0,0,0,.15); }

        @page { size: A4 portrait; margin: 15mm; }

        .caption { font-style: italic; margin: 0 0 4px 12mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 6px; vertical-align: middle; }
        th { font-weight: 700; text-align: center; }
        .ctr { text-align: center; }
        .total td { font-weight: 700; }
        .empty { text-align: center; color: #777; padding: 18px; }

        .noprint { text-align: center; margin: 14px 0; font-family: Arial, sans-serif; font-size: 12px; }
        .noprint button, .noprint a {
            background: #0b2a52; color: #fff; border: 0; border-radius: 6px;
            padding: 8px 18px; margin: 0 4px; cursor: pointer;
            font-size: 12px; text-decoration: none; display: inline-block;
        }
        .noprint .ghost { background: #374151; }
        .noprint select { padding: 6px; border-radius: 6px; margin-right: 6px; }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { width: auto; min-height: 0; box-shadow: none; padding: 0; }
            .noprint { display: none !important; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <p class="caption">Table 2. Civilian Human Resources Sent to Local Career Courses and Seminars</p>

    <table>
        <tr>
            <th style="width:32%">COURSE TITLE</th>
            <th style="width:16%">BATCH</th>
            <th style="width:20%">INCLUSIVE DATES</th>
            <th style="width:14%">NR OF GRADUATES</th>
            <th style="width:18%">CONDUCTED BY</th>
        </tr>
        @forelse ($rows as $r)
            <tr>
                <td>{{ $r['title'] }}</td>
                <td class="ctr">{{ $r['batch'] }}</td>
                <td class="ctr">{{ $r['dates'] }}</td>
                <td class="ctr">{{ $r['graduates'] }}</td>
                <td class="ctr">{{ $r['conducted_by'] }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No approved career courses or seminars for {{ $year }}.</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="3" style="text-align:right">TOTAL</td>
            <td class="ctr">{{ $total }}</td>
            <td></td>
        </tr>
    </table>
</div>

<div class="noprint">
    <form method="get" style="display:inline">
        <select name="year" onchange="this.form.submit()">
            @foreach (range(now()->year, now()->year - 5) as $y)
                <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
            @endforeach
        </select>
    </form>
    <button onclick="window.print()">Print Table 2</button>
    <a class="ghost" href="{{ route('ld.index', ['year' => $year]) }}">Back to L&amp;D</a>
</div>
</body>
</html>
