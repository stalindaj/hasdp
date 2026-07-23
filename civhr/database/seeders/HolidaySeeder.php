<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

/**
 * The 2026 nationwide non-working days:
 *   - Proclamation No. 1006, s. 2025 (regular holidays + special non-working
 *     days; Feb 25 is a special WORKING day, so it is NOT listed here)
 *   - Eid'l Fitr (Mar 20) and Eid'l Adha (May 27, Proclamation No. 1264),
 *     proclaimed separately per R.A. 9849
 *
 * Idempotent — matches on the date. Later years are added by the admins under
 * Admin → Holidays once the proclamation is out (or here, on a deploy).
 */
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            // ── Regular holidays ──
            ['2026-01-01', "New Year's Day"],
            ['2026-03-20', "Eid'l Fitr"],
            ['2026-04-02', 'Maundy Thursday'],
            ['2026-04-03', 'Good Friday'],
            ['2026-04-09', 'Araw ng Kagitingan'],
            ['2026-05-01', 'Labor Day'],
            ['2026-05-27', "Eid'l Adha"],
            ['2026-06-12', 'Independence Day'],
            ['2026-08-31', 'National Heroes Day'],
            ['2026-11-30', 'Bonifacio Day'],
            ['2026-12-25', 'Christmas Day'],
            ['2026-12-30', 'Rizal Day'],

            // ── Special (non-working) days ──
            ['2026-02-17', 'Chinese New Year'],
            ['2026-04-04', 'Black Saturday'],
            ['2026-08-21', 'Ninoy Aquino Day'],
            ['2026-11-01', "All Saints' Day"],
            ['2026-11-02', "All Souls' Day"],
            ['2026-12-08', 'Feast of the Immaculate Conception of Mary'],
            ['2026-12-24', 'Christmas Eve'],
            ['2026-12-31', 'Last Day of the Year'],
        ];

        foreach ($holidays as [$date, $name]) {
            Holiday::updateOrCreate(['date' => $date], ['name' => $name]);
        }
    }
}
