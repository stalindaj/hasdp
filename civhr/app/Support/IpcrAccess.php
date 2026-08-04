<?php

namespace App\Support;

use App\Models\IpcrForm;
use App\Models\User;

/**
 * Who may do what with IPCR forms.
 *
 * Managers (admin / superadmin / HR officer / approver) act as the original
 * app's admin + rating officer: they see everyone's forms and may create,
 * edit, rate and delete. Everyone else is a ratee: they see and edit their own.
 *
 * IPCR obeys the same "one hat at a time" rule as Leave: while an admin is in
 * employee mode they are just a ratee — they see only their own IPCR, cannot
 * pick someone else to rate, and cannot approve. Switching back to the admin
 * hat restores all of it.
 */
class IpcrAccess
{
    private const MANAGER_ROLES = ['admin', 'superadmin', 'hr_officer', 'approver'];

    public static function isManager(User $user): bool
    {
        return self::hasManagerRole($user) && ! ViewMode::isEmployee();
    }

    /** The role itself, regardless of which hat is on. */
    public static function hasManagerRole(User $user): bool
    {
        return $user->roles()->whereIn('name', self::MANAGER_ROLES)->exists();
    }

    public static function canView(User $user, IpcrForm $form): bool
    {
        return self::isManager($user) || $form->user_id === $user->id;
    }

    /** Managers may edit anything; a ratee may edit their own while it is open. */
    public static function canEdit(User $user, IpcrForm $form): bool
    {
        if (self::isManager($user)) {
            return true;
        }

        return $form->user_id === $user->id
            && in_array($form->status, [IpcrForm::DRAFT, IpcrForm::RETURNED], true);
    }

    public static function canDelete(User $user, IpcrForm $form): bool
    {
        return self::isManager($user);
    }

    /** The ratee (or a manager) submits a draft for approval. */
    public static function canSubmit(User $user, IpcrForm $form): bool
    {
        if (! in_array($form->status, [IpcrForm::DRAFT, IpcrForm::RETURNED], true)) {
            return false;
        }

        return self::isManager($user) || $form->user_id === $user->id;
    }

    /**
     * A manager (or the named approver) approves / returns a submitted form.
     * Nobody approves their own IPCR — the same rule Leave holds to.
     */
    public static function canDecide(User $user, IpcrForm $form): bool
    {
        if ($form->status !== IpcrForm::SUBMITTED || $form->user_id === $user->id) {
            return false;
        }

        return self::isManager($user)
            || ($form->approver_id === $user->id && ! ViewMode::isEmployee());
    }

    /**
     * Who may put ink on which Form E block. The ratee signs their own two
     * (the commitment and "Discussed with"); the four supervisor blocks belong
     * to a manager. Nobody signs on someone else's behalf.
     */
    public static function canSignBlock(User $user, IpcrForm $form, string $slot): bool
    {
        if (in_array($slot, ['ratee', 'discussed'], true)) {
            return self::isManager($user) || $form->user_id === $user->id;
        }

        return self::isManager($user);
    }

    /** Once approved, the ratee or a manager uploads the scanned wet-signed copy. */
    public static function canUploadScan(User $user, IpcrForm $form): bool
    {
        return $form->status === IpcrForm::APPROVED
            && (self::isManager($user) || $form->user_id === $user->id);
    }
}
