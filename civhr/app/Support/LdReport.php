<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LdEntry;
use App\Models\ReportCell;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The L&D part of the HR accomplishment report: Tables 1–5 and the L&D
 * Monitoring sheet, computed live from the approved filings of the active
 * roster.
 *
 * Every table comes out in one shape — columns, rows (each with a stable
 * key) and an optional total — so the report page and the Excel export
 * render them the same way. HR may hand-edit any editable cell; the edit is a
 * ReportCell for that period and replaces the live value, and derived columns
 * (quarter totals, percentages, the TOTAL row) are recomputed from the edited
 * figures. Deleting the edit brings the live value back.
 *
 * The period is a year, optionally cut off at a quarter ("as of Q3" counts
 * January to September), matching how the quarterly report is written.
 */
class LdReport
{
    public const REPORTS = ['t1', 't2', 't3', 't4', 't5', 'monitoring'];

    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    /** Table 5 groups the app's competency tracks into the report's parameters. */
    private const PARAMETERS = [
        'leadership' => ['Leadership Development', ['supervisory', 'managerial']],
        'core'       => ['Core Development', ['foundational']],
        'technical'  => ['Technical Development', ['technical']],
    ];

    private Collection $roster;

    private Collection $trainings;

    private Collection $courses;

    /** report => row_key => col => value */
    private array $edits = [];

    public function __construct(public readonly int $year, public readonly int $quarter = 0)
    {
    }

    public function period(): string
    {
        return $this->quarter ? "{$this->year}-Q{$this->quarter}" : (string) $this->year;
    }

    public function periodLabel(): string
    {
        return $this->quarter
            ? "CY {$this->year} as of ".['', '1st', '2nd', '3rd', '4th'][$this->quarter].' Quarter'
            : "CY {$this->year}";
    }

    /** All six tables, in report order. */
    public function tables(): array
    {
        $this->load();

        return [
            $this->table1(),
            $this->courseTable('t2', 'Table 2. Civilian Human Resources Sent to Local Career Courses and Seminars', false),
            $this->courseTable('t3', 'Table 3. Civilian Human Resources Sent to Courses/Training outside PAF', true),
            $this->table4(),
            $this->table5(),
            $this->monitoring(),
        ];
    }

    // ── Data ──────────────────────────────────────────────────────────

    private function load(): void
    {
        $from = Carbon::create($this->year, 1, 1)->startOfDay();
        $to = $this->quarter
            ? Carbon::create($this->year, $this->quarter * 3, 1)->endOfMonth()
            : Carbon::create($this->year, 12, 31)->endOfDay();

        // The same roster the L&D page tracks: people with a working login.
        $this->roster = Employee::query()
            ->whereHas('user', fn ($u) => $u->where('is_active', true))
            ->whereNotNull('emp_no')
            ->where('emp_no', '!=', 'mission')
            ->orderBy('last_name')
            ->get();

        $entries = LdEntry::approved()
            ->whereIn('employee_id', $this->roster->pluck('id'))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $this->trainings = $entries->where('kind', LdEntry::TRAINING)->values();
        $this->courses = $entries->where('kind', LdEntry::COURSE)->values();

        $this->edits = [];
        ReportCell::where('period', $this->period())->get()
            ->each(function (ReportCell $c) {
                $this->edits[$c->report][$c->row_key][$c->col] = $c->value;
            });
    }

    // ── The tables ────────────────────────────────────────────────────

    private function table1(): array
    {
        $order = array_flip(array_keys(LdEntry::COMPETENCIES));

        $rows = $this->trainings
            ->groupBy(fn (LdEntry $l) => ($l->competency ?? '').'|'.mb_strtolower(trim($l->title)))
            // Grouped by track, in the tracks' own order.
            ->sortBy(fn (Collection $g) => $order[$g->first()->competency ?? 'other'] ?? 99)
            ->map(fn (Collection $g) => [
                'key' => $this->key($g->first()->competency.'|'.mb_strtolower(trim($g->first()->title))),
                'cells' => [
                    'competency'   => $g->first()->competencyLabel(),
                    'title'        => $g->first()->title,
                    'dates'        => $this->span($g),
                    // The hours of the intervention itself, not summed over
                    // the people who attended it.
                    'hours'        => (float) $g->max('hours'),
                    'participants' => $g->pluck('employee_id')->unique()->count(),
                ],
            ])
            ->values()
            ->all();

        return $this->build('t1', 'Table 1. Conducted Unit Training/Seminars and L&D Intervention', [
            $this->col('competency', 'Competency'),
            $this->col('title', 'Training/ Seminars/ Intervention Title'),
            $this->col('dates', 'Inclusive dates'),
            $this->col('hours', 'Nr of HRs', sum: true),
            $this->col('participants', 'Nr of Civ HR participants', sum: true),
        ], $rows, totalLabel: ['dates', 'TOTAL']);
    }

    private function courseTable(string $report, string $title, bool $outside): array
    {
        $rows = $this->courses
            ->filter(fn (LdEntry $l) => (bool) $l->outside_paf === $outside)
            ->groupBy(fn (LdEntry $l) => mb_strtolower(trim($l->title)).'|'.mb_strtolower(trim((string) $l->batch)))
            ->map(fn (Collection $g, $group) => [
                'key' => $this->key($group),
                'cells' => [
                    'title'        => $g->first()->title,
                    'batch'        => $g->first()->batch,
                    'dates'        => $this->span($g),
                    'graduates'    => $g->pluck('employee_id')->unique()->count(),
                    'conducted_by' => $g->pluck('conducted_by')->filter()->first(),
                ],
            ])
            ->values()
            ->all();

        return $this->build($report, $title, [
            $this->col('title', 'Course title'),
            $this->col('batch', 'Batch'),
            $this->col('dates', 'Inclusive dates'),
            $this->col('graduates', 'Nr of graduates', sum: true),
            $this->col('conducted_by', 'Conducted by'),
        ], $rows, totalLabel: ['dates', 'TOTAL']);
    }

    /**
     * Table 4 — L&D by number of hours, per position level. Actual hours are
     * the hours that went toward each person's target (capped at it); what a
     * person logged beyond their target is the additional accomplishment.
     */
    private function table4(): array
    {
        $supervisorSg = (int) config('agency.ld_supervisor_sg_min');
        $levels = [
            'second_sup'    => '2nd Level Supervisory (SG '.$supervisorSg.' and above)',
            'second_nonsup' => '2nd Level Non-Supervisory',
            'first'         => '1st Level (SG 1 to 14)',
        ];

        $hoursBy = $this->trainings->groupBy('employee_id')->map(fn ($g) => (float) $g->sum('hours'));

        $rows = collect($levels)->map(function ($label, $level) use ($supervisorSg, $hoursBy) {
            $people = $this->roster->filter(fn (Employee $e) => match ($level) {
                'second_sup'    => (int) $e->salary_grade >= $supervisorSg,
                'second_nonsup' => false, // no field tells them apart yet — edit the row
                default         => (int) $e->salary_grade < $supervisorSg,
            });

            $target = $actual = $excess = 0.0;
            foreach ($people as $e) {
                $t = LdTarget::hoursFor($e);
                $h = $hoursBy[$e->id] ?? 0.0;
                $target += $t;
                $actual += min($h, $t);
                $excess += max(0, $h - $t);
            }

            return [
                'key' => $level,
                'cells' => [
                    'level'  => $label,
                    'filled' => $people->count(),
                    'target' => $target,
                    'actual' => $actual,
                    'pct'    => null,
                    'excess' => $excess,
                ],
            ];
        })->values()->all();

        return $this->build('t4', 'Table 4. L&D Based on Nr of HRs', [
            $this->col('level', 'Level'),
            $this->col('filled', 'Actual Filled Positions (a)', sum: true),
            $this->col('target', 'Target Nr of Hours (b)', sum: true),
            $this->col('actual', 'Actual Nr of Hours (c)', sum: true),
            $this->col('pct', '% Accomp (d = c/b)', edit: false),
            $this->col('excess', 'Additional Accomplishment (Excess Nr of Hours)', sum: true),
        ], $rows, totalLabel: ['level', 'TOTAL'], derive: function (array $c) {
            $c['pct'] = $this->pct($c['actual'], $c['target']);

            return $c;
        });
    }

    /**
     * Table 5 — L&D by parameter. The actual courses and participants come
     * from the approved trainings; the targets are HR's own plan, so they
     * start blank and are typed in on the report.
     */
    private function table5(): array
    {
        $rows = collect(self::PARAMETERS)->map(function ($def, $key) {
            [$label, $tracks] = $def;
            $done = $this->trainings->whereIn('competency', $tracks);

            return [
                'key' => $key,
                'cells' => [
                    'parameter'     => $label,
                    'target_course' => null,
                    'target_pax'    => null,
                    'adj_course'    => null,
                    'adj_pax'       => null,
                    'actual_course' => $done->map(fn ($l) => mb_strtolower(trim($l->title)))->unique()->count(),
                    'actual_pax'    => $done->pluck('employee_id')->unique()->count(),
                    'pct_course'    => null,
                    'pct_pax'       => null,
                    'additional'    => null,
                ],
            ];
        })->values()->all();

        return $this->build('t5', 'Table 5. L&D Based on the Parameter/Competencies', [
            $this->col('parameter', 'Measurement Parameter'),
            $this->col('target_course', 'Target · Course/ Program', sum: true),
            $this->col('target_pax', 'Target · No. of Pax', sum: true),
            $this->col('adj_course', 'Adjusted Target · Course/ Program (A)', sum: true),
            $this->col('adj_pax', 'Adjusted Target · No. of Pax (B)', sum: true),
            $this->col('actual_course', 'Actual · Course/ Program (C)', sum: true),
            $this->col('actual_pax', 'Actual · No. of Pax (D)', sum: true),
            $this->col('pct_course', '% Accomp · Course/ Program (E = C/A)', edit: false),
            $this->col('pct_pax', '% Accomp · No. of Pax (F = D/B)', edit: false),
            $this->col('additional', 'Additional Accomplishment'),
        ], $rows, totalLabel: ['parameter', 'Total'], derive: function (array $c) {
            // With no adjusted target, measure against the original one.
            $c['pct_course'] = $this->pct($c['actual_course'], $c['adj_course'] ?: $c['target_course']);
            $c['pct_pax'] = $this->pct($c['actual_pax'], $c['adj_pax'] ?: $c['target_pax']);

            return $c;
        });
    }

    /** The per-person L&D Monitoring sheet: hours by month and quarter against the target. */
    private function monitoring(): array
    {
        $supervisorSg = (int) config('agency.ld_supervisor_sg_min');
        $byPerson = $this->trainings->groupBy('employee_id');

        $rows = $this->roster->values()->map(function (Employee $e, $i) use ($byPerson, $supervisorSg) {
            $mine = $byPerson[$e->id] ?? collect();
            $cells = [
                'nr'       => $i + 1,
                'name'     => trim($e->last_name.', '.$e->first_name),
                'sg'       => $e->salary_grade,
                'required' => LdTarget::hoursFor($e),
                'level'    => (int) $e->salary_grade >= $supervisorSg ? '2nd' : '1st',
                'trainings' => $mine->pluck('title')->unique()->implode("\n"),
            ];
            foreach (self::MONTHS as $m => $col) {
                $cells[$col] = (float) $mine->filter(fn ($l) => $l->date->month === $m + 1)->sum('hours');
            }

            return ['key' => 'e'.$e->id, 'cells' => $cells + ['remarks' => null]];
        })->all();

        $columns = [
            $this->col('nr', 'Nr', edit: false),
            $this->col('name', 'Name'),
            $this->col('sg', 'SG'),
            $this->col('required', 'Required Nr of L&D Hours', sum: true),
            $this->col('level', 'Level'),
            $this->col('trainings', 'Trainings'),
        ];
        foreach (array_chunk(self::MONTHS, 3) as $q => $months) {
            foreach ($months as $col) {
                $columns[] = $this->col($col, ucfirst($col), sum: true);
            }
            $columns[] = $this->col('q'.($q + 1), 'Q'.($q + 1).' Total', sum: true, edit: false);
        }
        array_push($columns,
            $this->col('total', 'Total Nr of Hrs', sum: true, edit: false),
            $this->col('complied_hrs', 'Total Complied Nr of L&D Hrs', sum: true, edit: false),
            $this->col('additional', 'Additional Accomplishment', sum: true, edit: false),
            $this->col('complied', 'Complied Yes/No', edit: false),
            $this->col('remarks', 'Remarks'),
        );

        return $this->build('monitoring', 'Learning and Development Intervention Monitoring Report', $columns, $rows,
            totalLabel: ['name', 'TOTAL'],
            derive: function (array $c, bool $isTotal) {
                foreach (array_chunk(self::MONTHS, 3) as $q => $months) {
                    $c['q'.($q + 1)] = array_sum(array_map(fn ($m) => $this->num($c[$m]), $months));
                }
                $c['total'] = $c['q1'] + $c['q2'] + $c['q3'] + $c['q4'];
                if (! $isTotal) {
                    $required = $this->num($c['required']);
                    $c['complied_hrs'] = min($c['total'], $required);
                    $c['additional'] = max(0, $c['total'] - $required);
                    $c['complied'] = $c['total'] >= $required && $required > 0 ? 'YES' : 'NO';
                }

                return $c;
            });
    }

    // ── Assembly ──────────────────────────────────────────────────────

    /**
     * Apply the period's hand edits, recompute derived columns, and add the
     * TOTAL row (sums of the summable columns, then derived again).
     */
    private function build(string $report, string $title, array $columns, array $rows, ?array $totalLabel = null, ?\Closure $derive = null): array
    {
        $derive ??= fn (array $c) => $c;
        $edits = $this->edits[$report] ?? [];

        foreach ($rows as &$row) {
            $row['edited'] = [];
            foreach ($edits[$row['key']] ?? [] as $col => $value) {
                if (array_key_exists($col, $row['cells'])) {
                    $row['edited'][$col] = $row['cells'][$col];   // the live value, for "reset"
                    $row['cells'][$col] = $value;
                }
            }
            $row['cells'] = $derive($row['cells'], false);
        }
        unset($row);

        $total = null;
        if ($totalLabel) {
            $total = array_fill_keys(array_column($columns, 'key'), null);
            foreach ($columns as $col) {
                if ($col['sum']) {
                    $total[$col['key']] = array_sum(array_map(fn ($r) => $this->num($r['cells'][$col['key']] ?? 0), $rows));
                }
            }
            $total = $derive($total, true);
            $total[$totalLabel[0]] = $totalLabel[1];
        }

        return compact('report', 'title', 'columns', 'rows', 'total');
    }

    private function col(string $key, string $label, bool $sum = false, bool $edit = true): array
    {
        return compact('key', 'label', 'sum', 'edit');
    }

    private function key(string $group): string
    {
        return substr(md5($group), 0, 16);
    }

    /** "24 February - 28 July 2026" across a group of filings. */
    private function span(Collection $group): string
    {
        return (new LdEntry([
            'date'    => $group->min('date'),
            'date_to' => $group->map(fn ($l) => $l->date_to ?? $l->date)->max(),
        ]))->inclusive_dates_text;
    }

    private function num($v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    private function pct($part, $whole): string
    {
        $whole = $this->num($whole);

        return $whole > 0 ? round($this->num($part) / $whole * 100).'%' : '-';
    }
}
