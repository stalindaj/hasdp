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
 *
 * Applicant and admin are strictly separate roles here: the applicant owns
 * boxes 1–6 and signs 6.D; the admin owns 7.A, 7.B and 7.C/7.D. Nobody
 * certifies or decides their own leave. An admin who wants to file switches to
 * employee mode (see {@see ViewMode}), which strips their admin powers first.
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

    /**
     * Acting as an admin *right now* — the admin role plus the admin hat. An
     * admin who has switched to employee mode is treated as a plain employee
     * everywhere, which is what makes the two roles genuinely separate.
     */
    public static function isAdmin(User $user): bool
    {
        return self::hasAdminRole($user) && ! ViewMode::isEmployee();
    }

    /** The role itself, regardless of which hat is on. */
    public static function hasAdminRole(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->hasRole('admin');
    }

    /**
     * Filing a CS Form No. 6 is an employee act. An admin must switch to
     * employee mode first, so an application is never both filed and decided
     * by the same hat.
     */
    public static function canFile(User $user): bool
    {
        return ! self::isAdmin($user);
    }

    /**
     * The 7.A credit certification and the 7.C/7.D decision. Admin-only — an
     * applicant may never certify or decide their own leave.
     */
    public static function canDecide(LeaveApplication $app, User $user): bool
    {
        return $app->status !== self::CANCELLED && self::isAdmin($user);
    }

    /**
     * Naming who signs the form (7.A / 7.B / 7.C-D). This is not deciding the
     * leave — it just fills in the blocks — so the applicant may set them while
     * their application is still pending, in case the admin asks for changes
     * before approving. Once a decision is made only an admin may adjust them.
     */
    public static function canEditSignatories(LeaveApplication $app, User $user): bool
    {
        if ($app->status === self::CANCELLED) {
            return false;
        }

        if (self::isAdmin($user)) {
            return true;
        }

        return $app->status === self::PENDING && (int) $app->user_id === (int) $user->id;
    }

    /**
     * Who may drop a signature image onto a block of the printed form. The
     * applicant signs 6.D and nothing else; an admin signs (or pastes in) any
     * of the four blocks.
     */
    public static function canSignBlock(LeaveApplication $app, User $user, string $slot): bool
    {
        if ($app->status === self::CANCELLED) {
            return false;
        }

        return self::isAdmin($user)
            || ($slot === 'applicant' && (int) $app->user_id === (int) $user->id);
    }

    /** The applicant may withdraw while it is still pending. */
    public static function canCancel(LeaveApplication $app, User $user): bool
    {
        return $app->status === self::PENDING && (int) $app->user_id === (int) $user->id;
    }

    /**
     * The printable CS Form No. 6. Anyone who can see the application can open
     * it at any stage: the applicant needs to read back and check the form they
     * are filing — not merely be handed one after approval — and an admin
     * previews anyone's. It is the same sheet either way; an undecided form
     * simply prints with 7.C/7.D still blank. Only a cancelled application has
     * nothing worth printing.
     */
    public static function canPrint(LeaveApplication $app, User $user): bool
    {
        if ($app->status === self::CANCELLED) {
            return false;
        }

        return self::isAdmin($user) || (int) $app->user_id === (int) $user->id;
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
