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
    // The signature image sits ABOVE the printed name, in its own band, so the
    // name stays readable and the ink does not fall onto the table above.
    // $sigHeight is the band's height; $sigGapAbove is the clear space left
    // between the ink and the name. Tight cells (e.g. 7.A) pass a smaller band.
    $sigH   = $sigHeight ?? 20;
    $sigGap = $sigGapAbove ?? 2;

    $rank   = $sig['rank'] ?? '';
    // Some blocks (e.g. 6.D applicant) print no name — just a signature space.
    $name   = ($hideName ?? false) ? '' : ($sig['name'] ?? '');
    $branch = $sig['branch'] ?? '';

    // Tight cells can pass a smaller line pitch to make room above for a
    // larger signature while still fitting the caption inside the cell.
    $nameGap = $nameGapArg ?? 7.6;  // extra room below the (taller) name line
    $step = $stepArg ?? 6.3;        // vertical pitch of each stacked line
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

    // The signature normally sits above the name; when the name is hidden it
    // sits just above the signature rule instead, so it reads as signed on
    // the line rather than floating in empty space.
    $sigAnchor = ($hideName ?? false) ? $ruleTop : $nameTop;

    // Rank and branch align with the ENDS OF THE NAME itself — the rank under
    // the name's first letter, the branch under its last — however long the
    // name is. Done by anchoring both to an inline-block wrapped around the
    // name. The wrapper's line-height is pinned to $nameGap so top:100% puts
    // the rank row exactly where the old fixed layout had it (no drift from
    // the page's default 8.4pt line box).
@endphp

@if (! empty($sig['signature']))
    {{-- The e-signature sits over the printed name, the way a pen signature
         does: centred on the block, its baseline overlapping the name's top
         so the two read as one. --}}
    <img src="{{ $sig['signature'] }}" alt=""
         onerror="this.style.display='none'"
         style="position:absolute;
                left:{{ $left }}pt;
                top:{{ $sigAnchor - $sigGap - $sigH }}pt;
                width:{{ $width }}pt;
                height:{{ $sigH }}pt;
                border:0;
                object-fit:contain;
                object-position:center bottom;
                mix-blend-mode:multiply;">
@endif

<div class="t c" style="left:{{ $left }}pt; top:{{ $nameTop }}pt; width:{{ $width }}pt; line-height:{{ $nameGap }}pt;">
    <span class="v b f75" style="position:relative; display:inline-block; vertical-align:top; line-height:{{ $nameGap }}pt; white-space:nowrap;">{{ $name }}
        @if ($rank !== '')
            <span class="b f7" style="position:absolute; left:0; top:100%; line-height:{{ $step }}pt;">{{ $rank }}</span>
        @endif
        @if ($branch !== '')
            <span class="b f7" style="position:absolute; right:0; top:100%; line-height:{{ $step }}pt;">{{ $branch }}</span>
        @endif
    </span>
</div>

@foreach ($titleLines as $i => $line)
    <div class="t f7 c" style="left:{{ $left }}pt; top:{{ $titleTops[$i] }}pt; width:{{ $width }}pt; overflow:hidden;">{{ $line }}</div>
@endforeach

<div class="ln" style="left:{{ $left }}pt; top:{{ $ruleTop }}pt; width:{{ $width }}pt;"></div>
<div class="t f7 c" style="left:{{ $left }}pt; top:{{ $captionTop }}pt; width:{{ $width }}pt;">{{ $caption }}</div>
