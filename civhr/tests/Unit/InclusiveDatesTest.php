<?php

namespace Tests\Unit;

use App\Models\LeaveApplication;
use Tests\TestCase;

/**
 * 6.C inclusive dates, written the way the office writes them by hand.
 */
class InclusiveDatesTest extends TestCase
{
    private function text(string $from, string $to): string
    {
        $a = new LeaveApplication();
        $a->date_from = $from;
        $a->date_to = $to;

        return $a->inclusive_dates_text;
    }

    public function test_a_single_day_prints_one_date(): void
    {
        $this->assertSame('21 July 2026', $this->text('2026-07-21', '2026-07-21'));
    }

    public function test_a_range_inside_one_month_says_the_month_once(): void
    {
        $this->assertSame('20-22 July 2026', $this->text('2026-07-20', '2026-07-22'));
        $this->assertSame('27-28 August 2026', $this->text('2026-08-27', '2026-08-28'));
    }

    public function test_a_range_across_months_says_the_year_once(): void
    {
        $this->assertSame('30 July - 2 August 2026', $this->text('2026-07-30', '2026-08-02'));
    }

    public function test_a_range_across_years_spells_both_out(): void
    {
        $this->assertSame(
            '30 December 2026 - 2 January 2027',
            $this->text('2026-12-30', '2027-01-02')
        );
    }

    public function test_an_incomplete_range_prints_nothing(): void
    {
        $a = new LeaveApplication();
        $this->assertSame('', $a->inclusive_dates_text);
    }
}
