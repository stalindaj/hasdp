<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A rating period is a semester, and there is exactly one IWOT and one IPCR
 * per semester — two of each a year, never more. The period lived only in the
 * free-text `rating_period` ("January - June 2026"), which can be typed any
 * which way and cannot be counted on.
 *
 * So both forms gain an explicit `year` + `semester` (1 or 2), unique per
 * person, and approving an IPCR can now tick the right semester on the
 * compliance tracker (`ipcr_records.sem1_done` / `sem2_done`).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['ipcr_forms', 'iwot_forms'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedSmallInteger('year')->nullable()->after('user_id');
                $t->unsignedTinyInteger('semester')->nullable()->after('year'); // 1 | 2
            });
        }

        $this->backfill('ipcr_forms');
        $this->backfill('iwot_forms');

        foreach (['ipcr_forms', 'iwot_forms'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                // One per person per semester. Rows left without a period
                // (legacy, more than two in a year) keep NULL, which a unique
                // index allows more than once.
                $t->unique(['user_id', 'year', 'semester'], $table.'_period_unique');
            });
        }
    }

    /**
     * Give existing rows a period: read the year and half from the typed
     * rating period where we can, otherwise fall back to the first free
     * semester for that person so the unique index can be added.
     */
    private function backfill(string $table): void
    {
        $taken = []; // "user-year" => [semesters already used]

        foreach (DB::table($table)->orderBy('id')->get() as $row) {
            $period = (string) ($row->rating_period ?? '');

            preg_match('/(20\d{2})/', $period, $m);
            $year = (int) ($m[1] ?? date('Y', strtotime($row->created_at ?? 'now')));

            // "January - June" style halves; anything mentioning the second
            // half of the year is semester 2.
            $semester = preg_match('/jul|aug|sep|oct|nov|dec|2nd|second/i', $period) ? 2 : 1;

            $key = $row->user_id.'-'.$year;
            $used = $taken[$key] ?? [];

            if (in_array($semester, $used, true)) {
                $semester = $semester === 1 ? 2 : 1;      // try the other half
            }
            if (in_array($semester, $used, true)) {
                $semester = null;                          // both taken already
            }

            if ($semester !== null) {
                $taken[$key][] = $semester;
            }

            DB::table($table)->where('id', $row->id)->update([
                'year' => $year,
                'semester' => $semester,
            ]);
        }
    }

    public function down(): void
    {
        foreach (['ipcr_forms', 'iwot_forms'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique($table.'_period_unique');
                $t->dropColumn(['year', 'semester']);
            });
        }
    }
};
