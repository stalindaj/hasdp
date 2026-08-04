<?php

namespace App\Support;

use App\Models\IwotForm;
use App\Models\User;

/**
 * Who may do what with IWOT sheets — the same rules as IPCR: managers
 * (admin / superadmin / HR officer / approver) work on everyone's sheets, an
 * employee works on their own, and an admin wearing the employee hat is just
 * an employee (see IpcrAccess::isManager).
 */
class IwotAccess
{
    public static function isManager(User $user): bool
    {
        return IpcrAccess::isManager($user);
    }

    public static function canView(User $user, IwotForm $form): bool
    {
        return self::isManager($user) || $form->user_id === $user->id;
    }

    /** Managers may edit anything; the owner while it is a draft or returned. */
    public static function canEdit(User $user, IwotForm $form): bool
    {
        if (self::isManager($user)) {
            return true;
        }

        return $form->user_id === $user->id
            && in_array($form->status, [IwotForm::DRAFT, IwotForm::RETURNED], true);
    }

    public static function canDelete(User $user, IwotForm $form): bool
    {
        return self::isManager($user);
    }

    public static function canSubmit(User $user, IwotForm $form): bool
    {
        if (! in_array($form->status, [IwotForm::DRAFT, IwotForm::RETURNED], true)) {
            return false;
        }

        return self::isManager($user) || $form->user_id === $user->id;
    }

    /** Nobody approves their own targets. */
    public static function canDecide(User $user, IwotForm $form): bool
    {
        return $form->status === IwotForm::SUBMITTED
            && $form->user_id !== $user->id
            && self::isManager($user);
    }

    /**
     * Who may put ink on which block.
     *
     * Both blocks take the same rule: the employee whose IWOT it is, or a
     * manager. The NCOIC named under "Approved by" is typed in by hand rather
     * than picked from an account, so they cannot sign for themselves — in
     * practice the employee carries the paper copy their NCOIC signed and
     * uploads the scan of it, and HR can do the same.
     */
    public static function canSignBlock(User $user, IwotForm $form, string $slot): bool
    {
        return self::isManager($user) || $form->user_id === $user->id;
    }
}
