import { Link } from '@inertiajs/react';

/**
 * The two faces of a person's file, under one "Personnel" section:
 *   Accounts — the login, roles, and printed e-signature (Users)
 *   Records  — the plantilla / PSIPOP HR record (Employees)
 *
 * Rendered at the top of both admin pages so switching between them is one
 * click, and the navbar carries a single "Personnel" item instead of two.
 */
export default function PersonnelTabs({ active }) {
    const tabs = [
        { key: 'accounts', label: 'Accounts', href: route('admin.users.index') },
        { key: 'records', label: 'Records', href: route('admin.employees.index') },
    ];

    return (
        <nav className="flex gap-1 rounded-lg bg-slate-100 p-1">
            {tabs.map((t) => {
                const isActive = t.key === active;
                return (
                    <Link
                        key={t.key}
                        href={t.href}
                        className={
                            'rounded-md px-4 py-1.5 text-sm font-medium transition ' +
                            (isActive
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-500 hover:text-slate-800')
                        }
                        aria-current={isActive ? 'page' : undefined}
                    >
                        {t.label}
                    </Link>
                );
            })}
        </nav>
    );
}
