<?php

namespace Tests\Unit;

use App\Support\RatingPeriod;
use PHPUnit\Framework\TestCase;

class RatingPeriodTest extends TestCase
{
    public function test_a_semester_prints_its_full_dates(): void
    {
        $this->assertSame('01 January to 30 June 2026', RatingPeriod::label(2026, 1));
        $this->assertSame('01 July to 31 December 2026', RatingPeriod::label(2026, 2));
    }

    public function test_display_relabels_what_it_generated_and_keeps_what_was_typed(): void
    {
        // Blank and the older generated wording both follow the semester.
        $this->assertSame('01 July to 31 December 2026', RatingPeriod::display('', 2026, 2));
        $this->assertSame('01 July to 31 December 2026', RatingPeriod::display('July - December 2026', 2026, 2));
        $this->assertSame('01 January to 30 June 2022', RatingPeriod::display('January - June 2022', 2022, 1));

        // Wording someone typed themselves stands.
        $this->assertSame('CY 2026 (2nd sem)', RatingPeriod::display('CY 2026 (2nd sem)', 2026, 2));

        // With no semester to go on, the stored text is all there is.
        $this->assertSame('July - December 2026', RatingPeriod::display('July - December 2026', null, null));
    }
}
