@php
    $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
    $sigName = function ($user) {
        if (! $user) return '';
        $e = $user->employee;
        $name = $e ? trim(implode(' ', array_filter([$e->first_name, $e->middle_name, $e->last_name, $e->suffix]))) : $user->name;
        return trim(($e && $e->rank ? $e->rank.' ' : '').$name.($e && $e->rank ? ' '.config('agency.branch_suffix') : ''));
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Leave ledger — {{ trim($employee->last_name.', '.$employee->first_name) }}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: "Times New Roman", Times, serif; color: #000; margin: 0; padding: 24px; font-size: 12px; }
    .sheet { max-width: 960px; margin: 0 auto; }
    h1 { text-align: center; font-size: 15px; margin: 0 0 2px; letter-spacing: .5px; }
    .sub { text-align: center; font-size: 12px; margin: 0 0 16px; }
    .head { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
    .head td { padding: 2px 4px; font-size: 12px; }
    .head .lbl { font-weight: bold; white-space: nowrap; }
    .head .val { border-bottom: 1px solid #000; width: 33%; }
    table.card { width: 100%; border-collapse: collapse; }
    table.card th, table.card td { border: 1px solid #000; padding: 2px 4px; text-align: center; font-size: 11px; }
    table.card th { font-weight: bold; }
    table.card td.left { text-align: left; }
    .period td { font-weight: bold; }
    .totrow td { font-weight: bold; }
    .sig { width: 100%; margin-top: 28px; border-collapse: collapse; }
    .sig td { width: 33.33%; vertical-align: bottom; padding: 0 8px; font-size: 12px; }
    .sig .role { font-weight: bold; padding-bottom: 34px; }
    .sig .name { border-top: 1px solid #000; text-align: center; padding-top: 2px; font-weight: bold; }
    .sig .title { text-align: center; font-size: 11px; }
    @media print {
        body { padding: 0; }
        .noprint { display: none; }
        @page { size: A4 landscape; margin: 12mm; }
    }
    .toolbar { max-width: 960px; margin: 0 auto 16px; text-align: right; }
    .toolbar button { font-family: system-ui, sans-serif; font-size: 13px; padding: 8px 16px; border: 0; border-radius: 6px; background: #1e3a8a; color: #fff; cursor: pointer; }
</style>
</head>
<body>
    <div class="toolbar noprint">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="sheet">
        <h1>EMPLOYEE'S LEAVE CARD</h1>
        <p class="sub">{{ $agencyUnit }}</p>

        <table class="head">
            <tr>
                <td class="lbl">Name:</td>
                <td class="val">{{ trim($employee->last_name.', '.$employee->first_name.' '.$employee->middle_name) }}</td>
                <td class="lbl">Division/Office:</td>
                <td class="val">{{ $office }}</td>
                <td class="lbl">1st Day of Service:</td>
                <td class="val">{{ $firstDay }}</td>
            </tr>
        </table>

        <table class="card">
            <thead>
                <tr>
                    <th rowspan="2" style="width:14%">PERIOD</th>
                    <th rowspan="2" style="width:20%">PARTICULARS</th>
                    <th colspan="4">VACATION LEAVE</th>
                    <th colspan="4">SICK LEAVE</th>
                    <th rowspan="2" style="width:10%">Date &amp; Action Taken</th>
                </tr>
                <tr>
                    <th>Earned</th><th>Abs. w/ pay</th><th>Balance</th><th>Abs. w/o pay</th>
                    <th>Earned</th><th>Abs. w/ pay</th><th>Balance</th><th>Abs. w/o pay</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="{{ $r['period'] ? 'period' : '' }}">
                        <td class="left">{{ $r['period'] }}</td>
                        <td class="left">{{ $r['particulars'] }}</td>
                        <td>{{ $num($r['vl_earned']) }}</td>
                        <td>{{ $num($r['vl_wpay']) }}</td>
                        <td>{{ $num($r['vl_bal']) }}</td>
                        <td>{{ $num($r['vl_wopay']) }}</td>
                        <td>{{ $num($r['sl_earned']) }}</td>
                        <td>{{ $num($r['sl_wpay']) }}</td>
                        <td>{{ $num($r['sl_bal']) }}</td>
                        <td>{{ $num($r['sl_wopay']) }}</td>
                        <td>{{ $num($r['total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" style="padding:16px">No leave-credit activity on record yet.</td></tr>
                @endforelse
                <tr class="totrow">
                    <td class="left" colspan="2">***nothing follows***</td>
                    <td colspan="2">VACATION LEAVE</td>
                    <td colspan="2">{{ $num($vlBalance) }}</td>
                    <td colspan="2">SICK LEAVE</td>
                    <td>{{ $num($slBalance) }}</td>
                    <td>{{ $num($vlBalance + $slBalance) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="sig">
            <tr>
                <td class="role">PREPARED BY:</td>
                <td class="role">CERTIFIED CORRECT BY:</td>
                <td class="role">NOTED BY:</td>
            </tr>
            <tr>
                <td class="name">{{ $sigName($certifier) ?: '&nbsp;' }}</td>
                <td class="name">{{ $sigName($certifier) ?: '&nbsp;' }}</td>
                <td class="name">{{ $sigName($approver) ?: '&nbsp;' }}</td>
            </tr>
            <tr>
                <td class="title">{{ optional($certifier?->employee)->designation }}</td>
                <td class="title">{{ optional($certifier?->employee)->designation }}</td>
                <td class="title">{{ optional($approver?->employee)->designation }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
