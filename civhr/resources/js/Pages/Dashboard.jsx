import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

/* ── Small pieces ─────────────────────────────────────────────────────── */

function BigBox({ title, accent, children }) {
    return (
        <div className={`rounded-2xl border bg-white p-6 shadow-sm ${accent}`}>
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                {title}
            </p>
            <div className="mt-3">{children}</div>
        </div>
    );
}

function Stat({ value, label }) {
    return (
        <div>
            <p className="text-3xl font-bold text-slate-900">{value}</p>
            <p className="text-xs text-slate-500">{label}</p>
        </div>
    );
}

function SemBadge({ done, onClick, clickable }) {
    const base =
        'inline-flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-xs font-bold ring-1 ring-inset transition';
    const style = done
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
        : 'bg-slate-50 text-slate-400 ring-slate-200';
    if (!clickable) {
        return <span className={`${base} ${style}`}>{done ? '✓' : '—'}</span>;
    }
    return (
        <button
            type="button"
            onClick={onClick}
            title="Click to toggle"
            className={`${base} ${style} cursor-pointer hover:ring-2`}
        >
            {done ? '✓' : '—'}
        </button>
    );
}

/* ── Admin view ───────────────────────────────────────────────────────── */

function AdminDashboard({ year, ldTarget, rows, boxes, pendingLeaves }) {
    const [ldFor, setLdFor] = useState(null); // employee row for the L&D modal
    const ld = useForm({
        title: '',
        hours: '',
        date: new Date().toISOString().slice(0, 10),
    });

    const toggleSem = (row, sem) =>
        router.patch(
            route('dashboard.ipcr', row.id),
            { sem },
            { preserveScroll: true },
        );

    const submitLd = (e) => {
        e.preventDefault();
        ld.post(route('dashboard.ld', ldFor.id), {
            preserveScroll: true,
            onSuccess: () => {
                ld.reset();
                setLdFor(null);
            },
        });
    };

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {/* The big view — three boxes */}
            <div className="grid gap-5 sm:grid-cols-3">
                <BigBox title={`IPCR status · ${year}`} accent="border-blue-100">
                    <div className="flex items-end gap-6">
                        <Stat
                            value={`${boxes.ipcr.sem1_done}/${boxes.ipcr.total}`}
                            label="1st semester submitted"
                        />
                        <Stat
                            value={`${boxes.ipcr.sem2_done}/${boxes.ipcr.total}`}
                            label="2nd semester submitted"
                        />
                    </div>
                </BigBox>
                <BigBox title="Leave" accent="border-amber-100">
                    <div className="flex items-end gap-6">
                        <Stat value={boxes.leave.pending} label="pending approval" />
                        <Stat
                            value={`${boxes.leave.used_days}d`}
                            label={`days used · ${year}`}
                        />
                    </div>
                </BigBox>
                <BigBox title="Learning & Development" accent="border-emerald-100">
                    <div className="flex items-end gap-6">
                        <Stat
                            value={`${boxes.ld.total_hours}h`}
                            label={`hours logged · ${year}`}
                        />
                        <Stat
                            value={boxes.ld.behind}
                            label={`below ${ldTarget}h target`}
                        />
                    </div>
                </BigBox>
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_300px]">
                {/* Everyone's status */}
                <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3">Employee</th>
                                <th className="px-3 py-3 text-center">
                                    IPCR 1st
                                </th>
                                <th className="px-3 py-3 text-center">
                                    IPCR 2nd
                                </th>
                                <th className="px-4 py-3">Leave</th>
                                <th className="px-4 py-3">L&amp;D</th>
                                <th className="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.map((r) => (
                                <tr key={r.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={route('dashboard.employee', r.id)}
                                            className="font-medium text-slate-800 hover:text-blue-600"
                                        >
                                            {r.name}
                                        </Link>
                                        <p className="text-xs text-slate-400">
                                            #{r.emp_no}
                                        </p>
                                    </td>
                                    <td className="px-3 py-3 text-center">
                                        <SemBadge
                                            done={r.sem1}
                                            clickable
                                            onClick={() => toggleSem(r, 1)}
                                        />
                                    </td>
                                    <td className="px-3 py-3 text-center">
                                        <SemBadge
                                            done={r.sem2}
                                            clickable
                                            onClick={() => toggleSem(r, 2)}
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link
                                            href={route('dashboard.employee', r.id)}
                                            className="text-slate-700 underline-offset-2 hover:text-blue-600 hover:underline"
                                            title="View balances & ledger"
                                        >
                                            {r.leave_used}d used
                                        </Link>
                                        {r.leave_pending > 0 && (
                                            <p className="text-xs font-medium text-amber-600">
                                                {r.leave_pending} pending
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="text-slate-700">
                                            {r.ld_hours}h
                                        </p>
                                        {r.ld_pending > 0 ? (
                                            <p className="text-xs text-slate-400">
                                                {r.ld_pending}h pending
                                            </p>
                                        ) : (
                                            <p className="text-xs font-medium text-emerald-600">
                                                target met
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <button
                                            onClick={() => setLdFor(r)}
                                            className="text-xs font-medium text-blue-600 hover:text-blue-500"
                                        >
                                            + L&amp;D
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pending sidebar */}
                <aside className="space-y-4">
                    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-slate-800">
                                Pending leave
                            </h3>
                            <Link
                                href={route('leave.requests')}
                                className="text-xs font-medium text-blue-600 hover:text-blue-500"
                            >
                                View all
                            </Link>
                        </div>
                        {pendingLeaves.length === 0 ? (
                            <p className="text-sm text-slate-400">
                                Nothing waiting.
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {pendingLeaves.map((p) => (
                                    <li key={p.id}>
                                        <Link
                                            href={route('leave.show', p.id)}
                                            className="block rounded-md border border-slate-100 p-2.5 transition hover:border-blue-200 hover:bg-blue-50/40"
                                        >
                                            <p className="text-sm font-medium text-slate-800">
                                                {p.applicant}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {p.type} · {p.days}d ·{' '}
                                                {p.inclusive}
                                            </p>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </aside>
            </div>

            {/* Log L&D modal */}
            <Modal show={!!ldFor} onClose={() => setLdFor(null)} maxWidth="md">
                {ldFor && (
                    <form onSubmit={submitLd} className="p-6">
                        <h3 className="text-lg font-semibold text-slate-800">
                            Log L&amp;D — {ldFor.name}
                        </h3>
                        <div className="mt-5 space-y-4">
                            <div>
                                <InputLabel htmlFor="ld_title" value="Training title" />
                                <TextInput
                                    id="ld_title"
                                    className="mt-1 block w-full"
                                    value={ld.data.title}
                                    onChange={(e) => ld.setData('title', e.target.value)}
                                />
                                <InputError message={ld.errors.title} className="mt-1" />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel htmlFor="ld_hours" value="Hours" />
                                    <TextInput
                                        id="ld_hours"
                                        type="number"
                                        step="0.5"
                                        min="0.5"
                                        className="mt-1 block w-full"
                                        value={ld.data.hours}
                                        onChange={(e) => ld.setData('hours', e.target.value)}
                                    />
                                    <InputError message={ld.errors.hours} className="mt-1" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="ld_date" value="Date" />
                                    <TextInput
                                        id="ld_date"
                                        type="date"
                                        className="mt-1 block w-full"
                                        value={ld.data.date}
                                        onChange={(e) => ld.setData('date', e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end gap-3">
                            <SecondaryButton type="button" onClick={() => setLdFor(null)}>
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton disabled={ld.processing}>
                                Log training
                            </PrimaryButton>
                        </div>
                    </form>
                )}
            </Modal>
        </div>
    );
}

/* ── Employee view ────────────────────────────────────────────────────── */

function EmployeeDashboard({ year, ldTarget, me }) {
    return (
        <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <div className="grid gap-5 sm:grid-cols-3">
                <BigBox title={`My IPCR · ${year}`} accent="border-blue-100">
                    <div className="space-y-2 text-sm">
                        <p className="flex items-center justify-between">
                            <span className="text-slate-600">1st semester</span>
                            <SemBadge done={me.sem1} />
                        </p>
                        <p className="flex items-center justify-between">
                            <span className="text-slate-600">2nd semester</span>
                            <SemBadge done={me.sem2} />
                        </p>
                    </div>
                </BigBox>
                <BigBox title="My leave balances" accent="border-amber-100">
                    {me.balances ? (
                        <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            <p className="flex justify-between">
                                <span className="text-slate-500">VL</span>
                                <span className="font-semibold text-slate-900">{me.balances.vl}</span>
                            </p>
                            <p className="flex justify-between">
                                <span className="text-slate-500">SL</span>
                                <span className="font-semibold text-slate-900">{me.balances.sl}</span>
                            </p>
                            <p className="flex justify-between">
                                <span className="text-slate-500">Wellness</span>
                                <span className="font-semibold text-slate-900">{me.balances.wellness}</span>
                            </p>
                            <p className="flex justify-between">
                                <span className="text-slate-500">SPL</span>
                                <span className="font-semibold text-slate-900">{me.balances.spl}</span>
                            </p>
                        </div>
                    ) : (
                        <p className="text-sm text-slate-400">No employee record linked.</p>
                    )}
                    {me.leave_pending > 0 && (
                        <p className="mt-2 text-xs font-medium text-amber-600">
                            {me.leave_pending} application(s) pending
                        </p>
                    )}
                    <Link
                        href={route('leave.create')}
                        className="mt-3 inline-block text-sm font-medium text-blue-600 hover:text-blue-500"
                    >
                        File leave →
                    </Link>
                </BigBox>
                <BigBox title="My L&D" accent="border-emerald-100">
                    <Stat value={`${me.ld_hours}h`} label={`of ${ldTarget}h · ${year}`} />
                    {me.ld_pending > 0 ? (
                        <p className="mt-1 text-xs text-slate-400">
                            {me.ld_pending}h still pending
                        </p>
                    ) : (
                        <p className="mt-1 text-xs font-medium text-emerald-600">
                            target met
                        </p>
                    )}
                </BigBox>
            </div>

            {me.ld_entries.length > 0 && (
                <div className="mt-8 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <p className="border-b border-slate-100 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        My trainings · {year}
                    </p>
                    <ul className="divide-y divide-slate-100 text-sm">
                        {me.ld_entries.map((l, i) => (
                            <li key={i} className="flex items-center justify-between px-4 py-2.5">
                                <span className="text-slate-700">{l.title}</span>
                                <span className="text-slate-500">
                                    {l.hours}h · {l.date}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

/* ── Page ─────────────────────────────────────────────────────────────── */

export default function Dashboard(props) {
    const flash = usePage().props.flash;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-slate-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />
            {flash?.success && (
                <div className="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">
                        {flash.success}
                    </div>
                </div>
            )}
            {props.mode === 'admin' ? (
                <AdminDashboard {...props} />
            ) : (
                <EmployeeDashboard {...props} />
            )}
        </AuthenticatedLayout>
    );
}
