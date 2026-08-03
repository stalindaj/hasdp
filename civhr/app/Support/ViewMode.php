<?php

namespace App\Support;

/**
 * Admin and employee are two separate hats, never worn at once.
 *
 * An account with an admin role acts as an admin by default: it processes
 * other people's leave and cannot file its own. To file, the admin switches
 * to employee mode — and for as long as they are in it they have no admin
 * powers at all, on screen or on the server.
 *
 * The switch lives in the session, so it survives a page load but never
 * outlives the login.
 */
class ViewMode
{
    public const ADMIN = 'admin';
    public const EMPLOYEE = 'employee';

    public static function current(): string
    {
        return session('view_mode') === self::EMPLOYEE ? self::EMPLOYEE : self::ADMIN;
    }

    public static function isEmployee(): bool
    {
        return self::current() === self::EMPLOYEE;
    }

    /** Flip the hat, and return the mode now in force. */
    public static function toggle(): string
    {
        $mode = self::isEmployee() ? self::ADMIN : self::EMPLOYEE;
        session(['view_mode' => $mode]);

        return $mode;
    }
}
