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
 * Unlike Leave, IPCR is not part of the admin/employee "one hat" toggle — a
 * rating officer must be able to rate others regardless of view mode.
 */
class IpcrAccess
{
    private const MANAGER_ROLES = ['admin', 'superadmin', 'hr_officer', 'approver'];

    public static function isManager(User $user): bool
    {
        return $user->roles()->whereIn('name', self::MANAGER_ROLES)->exists();
    }

    public static function canView(User $user, IpcrForm $form): bool
    {
        return self::isManager($user) || $form->user_id === $user->id;
    }

    /** Managers may edit anything; a ratee may edit their own while it is a draft. */
    public static function canEdit(User $user, IpcrForm $form): bool
    {
        if (self::isManager($user)) {
            return true;
        }

        return $form->user_id === $user->id && $form->status === 'draft';
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

    /** A manager (or the named approver) approves / returns a submitted form. */
    public static function canDecide(User $user, IpcrForm $form): bool
    {
        if ($form->status !== IpcrForm::SUBMITTED) {
            return false;
        }

        return self::isManager($user) || $form->approver_id === $user->id;
    }

    /** Once approved, the ratee or a manager uploads the scanned wet-signed copy. */
    public static function canUploadScan(User $user, IpcrForm $form): bool
    {
        return $form->status === IpcrForm::APPROVED
            && (self::isManager($user) || $form->user_id === $user->id);
    }
}
