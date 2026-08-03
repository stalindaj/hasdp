<?php

namespace App\Support;

use App\Models\LeaveApplication;

/**
 * Box 7.A — the certification of leave credits.
 *
 * The figures are not something anyone should retype: the ledger already knows
 * the balances, and the application already knows how many days it asks for.
 * So 7.A is computed here from both, and the HR officer's job is to check it
 * and sign — not to do the arithmetic.
 *
 * A leave draws on one balance at most. The side it draws from shows the days
 * deducted; the other side has no "less" figure at all and prints as a dash,
 * carrying its earned total straight down to the balance.
 *
 * Whatever an admin has actually saved onto the application always wins over
 * the computed figure — see {@see merged()}.
 */
class LeaveCertification
{
    /** The seven 7.A fields, computed live from the ledger. */
    public static function computed(LeaveApplication $a): array
    {
        // No employee record means no ledger to read; fall back to the gross
        // length-of-service estimate so the block is still filled in.
        if (! $a->employee) {
            return LeaveCredits::certificationFor(
                $a->employee,
                $a->leaveType->code ?? '',
                (float) $a->working_days
            );
        }

        $balances = CreditLedger::balances($a->employee);
        $kind = $a->leaveType?->credit_kind;
        $days = (float) $a->working_days;

        $vlLess = $kind === 'vl' ? $days : null;
        $slLess = $kind === 'sl' ? $days : null;

        return [
            'cert_as_of' => now()->toDateString(),
            'vl_earned'  => $balances['vl'],
            'vl_less'    => $vlLess,
            'vl_balance' => round($balances['vl'] - (float) $vlLess, 2),
            'sl_earned'  => $balances['sl'],
            'sl_less'    => $slLess,
            'sl_balance' => round($balances['sl'] - (float) $slLess, 2),
        ];
    }

    /**
     * What 7.A should actually show: the admin's saved figures where they
     * exist, the computed ones everywhere else. This is what the printed form
     * and the on-screen summary both render, so an untouched application is
     * never blank and a corrected one is never overwritten.
     *
     * `vl_less` / `sl_less` are deliberately allowed to stay null — a null
     * there means "this leave does not draw on that balance" and prints as a
     * dash, which is different from a zero.
     */
    public static function merged(LeaveApplication $a): array
    {
        $computed = self::computed($a);

        // Once anything has been certified, the saved block is authoritative in
        // full — including a "less" the admin deliberately cleared.
        $certified = $a->cert_as_of !== null
            || $a->vl_earned !== null
            || $a->sl_earned !== null;

        if ($certified) {
            return [
                'cert_as_of' => optional($a->cert_as_of)->toDateString() ?? $computed['cert_as_of'],
                'vl_earned'  => $a->vl_earned  ?? $computed['vl_earned'],
                'vl_less'    => $a->vl_less,
                'vl_balance' => $a->vl_balance ?? $computed['vl_balance'],
                'sl_earned'  => $a->sl_earned  ?? $computed['sl_earned'],
                'sl_less'    => $a->sl_less,
                'sl_balance' => $a->sl_balance ?? $computed['sl_balance'],
            ];
        }

        return $computed;
    }
}
