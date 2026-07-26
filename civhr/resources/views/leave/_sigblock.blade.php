{{--
    A signature block, laid out exactly like the office CS Form 6:

                  ADRIAN LEE G MISSION      <- $sig['name'], centred, bold
         LTC                        PAF     <- rank left, branch right
                 Director for Personnel     <- position / designation lines
         ────────────────────────────────   <- the signature rule (BOTTOM)
                  (Authorized Official)      <- $caption, below the rule

    The officer signs over their printed name; the rule sits above the caption.
    A civilian signatory has no rank/branch and may carry two title lines
    (e.g. "Admin Officer IV (HRMO II)" then "Wing Civilian Supervisor").

    Expects: $sig (['rank','name','branch','position','designation']),
             $left, $width, $top, $caption.
--}}
@php
    $rank   = $sig['rank'] ?? '';
    $name   = $sig['name'] ?? '';
    $branch = $sig['branch'] ?? '';

    $nameGap = 7.6;              // extra room below the (taller) name line
    $step = 6.3;                 // vertical pitch of each stacked line
    $y = $top;                   // running cursor

    // Name (bold), centred.
    $nameTop = $y;
    $y += $nameGap;

    // Rank flanks left, branch flanks right, on the row under the name.
    $hasRankRow = ($rank !== '' || $branch !== '');
    $rankTop = $y;
    if ($hasRankRow) {
        $y += $step;
    }

    // Centred title lines: whatever the admin set.
    $titleLines = array_values(array_filter([
        $sig['position'] ?? '',
        $sig['designation'] ?? '',
    ], fn ($l) => $l !== '' && $l !== null));

    $titleTops = [];
    foreach ($titleLines as $line) {
        $titleTops[] = $y;
        $y += $step;
    }

    // The signature rule sits just below the last line of text…
    $ruleTop = $y + 1.2;
    // …and the caption below the rule.
    $captionTop = $ruleTop + 1.4;

    // Rank and branch flank the name at the block's edges, matching the office
    // form: rank sits just inside the left margin, the branch right-aligns to
    // the right margin — both a small inset in from the very edge.
    $edgeInset  = $width * 0.10;
    $rankLeft   = $left + $edgeInset;
    $branchWidth = $width - $edgeInset;   // branch right-aligned within this
@endphp

<div class="t v b f75 c" style="left:{{ $left }}pt; top:{{ $nameTop }}pt; width:{{ $width }}pt; overflow:hidden;">{{ $name }}</div>

@if ($rank !== '')
    <div class="t b f7" style="left:{{ $rankLeft }}pt; top:{{ $rankTop }}pt;">{{ $rank }}</div>
@endif
@if ($branch !== '')
    <div class="t b f7" style="left:{{ $left }}pt; top:{{ $rankTop }}pt; width:{{ $branchWidth }}pt; text-align:right;">{{ $branch }}</div>
@endif

@foreach ($titleLines as $i => $line)
    <div class="t f7 c" style="left:{{ $left }}pt; top:{{ $titleTops[$i] }}pt; width:{{ $width }}pt; overflow:hidden;">{{ $line }}</div>
@endforeach

<div class="ln" style="left:{{ $left }}pt; top:{{ $ruleTop }}pt; width:{{ $width }}pt;"></div>
<div class="t f7 c" style="left:{{ $left }}pt; top:{{ $captionTop }}pt; width:{{ $width }}pt;">{{ $caption }}</div>
