import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, router, usePage } from '@inertiajs/react';

export default function AuthenticatedLayout({ header, children }) {
    const { user, isAdmin, isSuperadmin, canSwitchView, viewMode } =
        usePage().props.auth;

    const switchView = () => router.post(route('view-mode.toggle'));

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    return (
        <div className="min-h-screen bg-gray-100">
            {/* PAF-blue brand stripe */}
            <div className="h-1 bg-gradient-to-r from-blue-950 via-blue-800 to-sky-600" />
            <nav className="border-b border-gray-200 bg-white shadow-sm">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <ApplicationLogo />
                                </Link>
                            </div>

                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Dashboard
                                </NavLink>
                                {/* My Profile is employee self-service; an
                                    admin edits records under Employees. */}
                                {!isAdmin && (
                                    <NavLink
                                        href={route('my-profile.edit')}
                                        active={route().current('my-profile.*')}
                                    >
                                        My Profile
                                    </NavLink>
                                )}
                                {/* Admins have no "my leave" — Leave is the
                                    requests queue until they switch hats. */}
                                <NavLink
                                    href={route(
                                        isAdmin ? 'leave.requests' : 'leave.index',
                                    )}
                                    active={route().current('leave.*')}
                                >
                                    {isAdmin ? 'Leave requests' : 'My Leave'}
                                </NavLink>

                                {isAdmin && (
                                    <NavLink
                                        href={route('admin.users.index')}
                                        active={route().current('admin.users.*')}
                                    >
                                        Users
                                    </NavLink>
                                )}
                                {isAdmin && (
                                    <NavLink
                                        href={route('admin.employees.index')}
                                        active={route().current(
                                            'admin.employees.*',
                                        )}
                                    >
                                        Employees
                                    </NavLink>
                                )}
                                {isAdmin && (
                                    <NavLink
                                        href={route('admin.balances.index')}
                                        active={route().current(
                                            'admin.balances.*',
                                        )}
                                    >
                                        Balances
                                    </NavLink>
                                )}
                                {isAdmin && (
                                    <NavLink
                                        href={route('admin.holidays.index')}
                                        active={route().current(
                                            'admin.holidays.*',
                                        )}
                                    >
                                        Holidays
                                    </NavLink>
                                )}
                                {isSuperadmin && (
                                    <NavLink
                                        href={route('admin.audit.index')}
                                        active={route().current('admin.audit.*')}
                                    >
                                        Audit
                                    </NavLink>
                                )}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            {canSwitchView && (
                                <button
                                    onClick={switchView}
                                    className={
                                        'mr-2 rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-inset transition ' +
                                        (viewMode === 'employee'
                                            ? 'bg-amber-50 text-amber-700 ring-amber-200 hover:bg-amber-100'
                                            : 'bg-slate-50 text-slate-600 ring-slate-200 hover:bg-slate-100')
                                    }
                                    title="Admins can preview the app exactly as an employee sees it"
                                >
                                    {viewMode === 'employee'
                                        ? '← Back to admin'
                                        : 'View as employee'}
                                </button>
                            )}
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Account settings
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        {canSwitchView && (
                            <button
                                onClick={switchView}
                                className="block w-full px-4 py-2 text-start text-base font-medium text-amber-700"
                            >
                                {viewMode === 'employee'
                                    ? '← Back to admin'
                                    : 'View as employee'}
                            </button>
                        )}
                        {!isAdmin && (
                            <ResponsiveNavLink
                                href={route('my-profile.edit')}
                                active={route().current('my-profile.*')}
                            >
                                My Profile
                            </ResponsiveNavLink>
                        )}
                        <ResponsiveNavLink
                            href={route(
                                isAdmin ? 'leave.requests' : 'leave.index',
                            )}
                            active={route().current('leave.*')}
                        >
                            {isAdmin ? 'Leave requests' : 'My Leave'}
                        </ResponsiveNavLink>

                        {isAdmin && (
                            <ResponsiveNavLink
                                href={route('admin.users.index')}
                                active={route().current('admin.users.*')}
                            >
                                Users
                            </ResponsiveNavLink>
                        )}
                        {isAdmin && (
                            <ResponsiveNavLink
                                href={route('admin.employees.index')}
                                active={route().current('admin.employees.*')}
                            >
                                Employees
                            </ResponsiveNavLink>
                        )}
                        {isAdmin && (
                            <ResponsiveNavLink
                                href={route('admin.balances.index')}
                                active={route().current('admin.balances.*')}
                            >
                                Balances
                            </ResponsiveNavLink>
                        )}
                        {isAdmin && (
                            <ResponsiveNavLink
                                href={route('admin.holidays.index')}
                                active={route().current('admin.holidays.*')}
                            >
                                Holidays
                            </ResponsiveNavLink>
                        )}
                        {isSuperadmin && (
                            <ResponsiveNavLink
                                href={route('admin.audit.index')}
                                active={route().current('admin.audit.*')}
                            >
                                Audit
                            </ResponsiveNavLink>
                        )}
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-gray-500">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            {/* The admin's view switch lives in the top bar on
                                desktop; phones only have this menu. */}
                            {canSwitchView && (
                                <ResponsiveNavLink
                                    method="post"
                                    href={route('view-mode.toggle')}
                                    as="button"
                                >
                                    {viewMode === 'employee'
                                        ? '← Back to admin'
                                        : 'View as employee'}
                                </ResponsiveNavLink>
                            )}
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Account settings
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {/* Which hat is on, said plainly — an admin in employee mode has
                no admin powers at all, so it must never be a guess. */}
            {canSwitchView && viewMode === 'employee' && (
                <div className="bg-amber-100 px-4 py-2 text-center text-sm text-amber-900">
                    You are in{' '}
                    <span className="font-semibold">employee mode</span> — filing
                    and viewing your own records only.{' '}
                    <button
                        onClick={switchView}
                        className="font-semibold underline hover:text-amber-950"
                    >
                        Back to admin
                    </button>
                </div>
            )}

            {header && (
                <header className="bg-white shadow">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}