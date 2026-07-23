<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * The 6.A checkbox list of CS Form No. 6 (Revised 2020), in printed order.
     * `legal_basis` is the fine print beside each label and is reproduced
     * verbatim on the printed form, so do not reword it.
     */
    public function run(): void
    {
        // [code, name, legal basis, 6.B detail group, credit kind, official?]
        // credit kind = which balance an approval deducts (vl/sl/spl/wellness).
        // Unofficial types print under "Others:" on the CSC form.
        $types = [
            ['vacation',          'Vacation Leave',                    '(Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',        'vacation', 'vl',       true],
            ['forced',            'Mandatory/Forced Leave',            '(Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',        null,       'vl',       true],
            ['sick',              'Sick Leave',                        '(Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',        'sick',     'sl',       true],
            ['maternity',         'Maternity Leave',                   '(R.A. No. 11210 / IRR issued by CSC, DOLE and SSS)',                  null,       null,       true],
            ['paternity',         'Paternity Leave',                   '(R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended)',                null,       null,       true],
            ['special_privilege', 'Special Privilege Leave',           '(Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',        'vacation', 'spl',      true],
            ['solo_parent',       'Solo Parent Leave',                 '(RA No. 8972 / CSC MC No. 8, s. 2004)',                               null,       null,       true],
            ['study',             'Study Leave',                       '(Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',        'study',    null,       true],
            ['vawc',              '10-Day VAWC Leave',                 '(RA No. 9262 / CSC MC No. 15, s. 2005)',                              null,       null,       true],
            ['rehabilitation',    'Rehabilitation Privilege',          '(Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',        null,       null,       true],
            ['women',             'Special Leave Benefits for Women',  '(RA No. 9710 / CSC MC No. 25, s. 2010)',                              'women',    null,       true],
            ['calamity',          'Special Emergency (Calamity) Leave', '(CSC MC No. 2, s. 2012, as amended)',                                null,       null,       true],
            ['adoption',          'Adoption Leave',                    '(R.A. No. 8552)',                                                     null,       null,       true],
            ['wellness',          'Wellness Leave',                    '(MC No. 01, s. 2026)',                                                null,       'wellness', false],
            ['others',            'Others',                            null,                                                                  null,       null,       true],
        ];

        foreach ($types as $i => [$code, $name, $basis, $group, $creditKind, $official]) {
            LeaveType::updateOrCreate(
                ['code' => $code],
                [
                    'name'         => $name,
                    'legal_basis'  => $basis,
                    'detail_group' => $group,
                    'credit_kind'  => $creditKind,
                    'is_official'  => $official,
                    'sort_order'   => $i + 1,
                    'is_active'    => true,
                ]
            );
        }
    }
}
