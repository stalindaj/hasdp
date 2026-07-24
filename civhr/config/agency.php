<?php

/**
 * Printed in the header of official forms (e.g. CS Form No. 6).
 * Set these in .env rather than editing this file, so local and production
 * can differ.
 */
return [
    'name'     => env('AGENCY_NAME', 'PHILIPPINE AIR FORCE'),
    'address'  => env('AGENCY_ADDRESS', 'Col Jesus Villamor Air Base'),
    'address2' => env('AGENCY_ADDRESS_2', 'Pasay City, Metro Manila'),

    // The unit the website itself fronts. The name/address above stay for the
    // official form header; the site shows only the unit.
    'unit' => env('AGENCY_UNIT', '15TH STRIKE WING'),

    // Header seals, paths under public/. The form carries two: the service
    // seal on the left and the unit seal on the right.
    'logo_left'  => env('AGENCY_LOGO_LEFT', 'images/paf-logo.png'),
    'logo_right' => env('AGENCY_LOGO_RIGHT', 'images/agency-logo.png'),

    // Appended after a signatory's name on the printed form, e.g. "… PAF".
    // Blank it out for a civilian agency.
    'branch_suffix' => env('AGENCY_BRANCH_SUFFIX', 'PAF'),

    // Annual Learning & Development target per employee, in hours. The
    // dashboard shows how many hours each person still has pending.
    'ld_target_hours' => (float) env('LD_TARGET_HOURS', 8),

    // Annual leave entitlements that reset each January (days per year).
    'wellness_days' => (float) env('WELLNESS_DAYS', 5),
    'spl_days'      => (float) env('SPL_DAYS', 3),

    // CSC Sec. 25, Rule XVI: five VL days a year are mandatory/forced —
    // unused days are forfeited at year-end (any VL availment counts).
    'forced_days'   => (float) env('FORCED_LEAVE_DAYS', 5),
];
