<?php

namespace App\Support;

use App\Models\LeaveApplication;
use App\Models\LeaveApplicationAction;
use App\Models\User;

/**
 * The (deliberately simple) leave workflow.
 *
 * An employee files a CS Form No. 6. It waits as PENDING until an admin (the
 * supervisor or assistant supervisor) fills in the certification of leave
 * credits (7.A) and either approves (7.C) or disapproves (7.D). Once approved,
 * the employee is told it is ready to print for wet signing.
 *
 * There is no multi-step routing and no recommender — 7.B is left blank on the
 * printout for optional pen signing.
 */
class LeaveWorkflow
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const DISAPPROVED = 'disapproved';
    public const CANCELLED = 'cancelled';

    public static function label(string $status): string
    {
        return match ($status) {
            self::PENDING      => 'Pending',
            self::APPROVED     => 'Approved',
            self::DISAPPROVED  => 'Disapproved',
            self::CANCELLED    => 'Cancelled',
            default            => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function isAdmin(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->hasRole('admin');
    }

    /**
     * The applicant and any admin may process a leave — fill the 7.A credit
     * certification, name the signatories, and record the 7.C/7.D decision —
     * and revise it all afterwards, for as long as it is not cancelled. The
     * applicant self-serves the whole CS Form 6; an admin can step in on any.
     */
    public static function canProcess(LeaveApplication $app, User $user): bool
    {
        if ($app->status === self::CANCELLED) {
            return false;
        }

        return self::isAdmin($user) || (int) $app->user_id === (int) $user->id;
    }

    /** The applicant may withdraw while it is still pending. */
    public static function canCancel(LeaveApplication $app, User $user): bool
    {
        return $app->status === self::PENDING && (int) $app->user_id === (int) $user->id;
    }

    /**
     * The printable form is meant for wet signing after approval. Admins may
     * open it any time (to preview); the applicant only once approved.
     */
    public static function canPrint(LeaveApplication $app, User $user): bool
    {
        if ($app->status === self::CANCELLED) {
            return false;
        }

        return $app->status === self::APPROVED || self::isAdmin($user);
    }

    public static function log(
        LeaveApplication $app,
        ?User $user,
        string $action,
        ?string $from,
        ?string $to,
        ?string $remarks = null
    ): LeaveApplicationAction {
        return LeaveApplicationAction::create([
            'leave_application_id' => $app->id,
            'user_id'              => $user?->id,
            'action'               => $action,
            'from_status'          => $from,
            'to_status'            => $to,
            'remarks'              => $remarks,
        ]);
    }
}
