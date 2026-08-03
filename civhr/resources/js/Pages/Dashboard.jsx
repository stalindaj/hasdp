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

/** pending / approved / rejected chip for L&D rows. */
function LdStatus({ status }) {
    const styles = {
        pending: 'bg-amber-50 text-amber-700 ring-amber-200',
        approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        rejected: 'bg-red-50 text-red-700 ring-red-200',
    };
    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset ${styles[status] ?? styles.pending}`}
        >
            {status}
        </span>
    );
}

/** "Certificate · Photo" proof links for an L&D entry. */
function LdProofLinks({ entry }) {
    if (!entry.certificate && !entry.photo) return null;
    return (
        <span className="space-x-2 text-xs">
            {entry.certificate && (
                <a
                    href={entry.certificate}
                    target="_blank"
                    rel="noopener"
                    className="font-medium text-blue-600 underline-offset-2 hover:underline"
                >
                    Certificate
                </a>
            )}
            {entry.photo && (
                <a
                    href={entry.photo}
                    target="_blank"
                    rel="noopener"
                    className="font-medium text-blue-600 underline-offset-2 hover:underline"
                >
                    Photo
                </a>
            )}
        </span>
    );
}

/* ── Admin view ───────────────────────────────────────────────────────── */

function AdminDashboard({ year, ldTarget, rows, boxes, pendingLeaves, pendingLd }) {
    const decideLd = (entry, decision) => {
        let remarks = null;
        if (decision === 'rejected') {
            remarks = prompt(`Reason for rejecting "${entry.title}"?`);
            if (!remarks) return; // cancelled
        }
        router.patch(
            route('ld.decide', entry.id),
            { decision, remarks },
            { preserveScroll: true },
        );
    };
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
                    <div className="flex flex-wrap items-end gap-x-6 gap-y-3">
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
                    <div className="flex flex-wrap items-end gap-x-6 gap-y-3">
                        <Stat value={boxes.leave.pending} label="pending approval" />
                        <Stat
                            value={`${boxes.leave.used_days}d`}
                            label={`days used · ${year}`}
                        />
                    </div>
                </BigBox>
                <BigBox title="Learning & Development" accent="border-emerald-100">
                    <div className="flex flex-wrap items-end gap-x-6 gap-y-3">
                        <Stat
                            value={`${boxes.ld.total_hours}h`}
                            label={`hours approved · ${year}`}
                        />
                        <Stat
                            value={boxes.ld.behind}
                            label={`below ${ldTarget}h target`}
                        />
                        <Stat
                            value={boxes.ld.pending}
                            label="awaiting approval"
                        />
                    </div>
                </BigBox>
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_300px]">
                {/* Everyone's status — scrolls sideways on a phone rather
                    than squashing the columns. */}
                <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
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
                    {/* L&D submissions waiting for approval */}
                    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 className="mb-3 text-sm font-semibold text-slate-800">
                            Pending L&amp;D
                        </h3>
                        {pendingLd.length === 0 ? (
                            <p className="text-sm text-slate-400">
                                Nothing waiting.
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {pendingLd.map((l) => (
                                    <li
                                        key={l.id}
                                        className="rounded-md border border-slate-100 p-2.5"
                                    >
                                        <p className="text-sm font-medium text-slate-800">
                                            {l.employee}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            {l.title} · {l.hours}h · {l.date}
                                        </p>
                                        <div className="mt-1.5 flex items-center justify-between">
                                            <LdProofLinks entry={l} />
                                            <span className="space-x-2 text-xs font-medium">
                                                <button
                                                    onClick={() => decideLd(l, 'approved')}
                                                    className="text-emerald-600 hover:text-emerald-500"
                                                >
                                                    Approve
                                                </button>
                                                <button
                                                    onClick={() => decideLd(l, 'rejected')}
                                                    className="text-red-600 hover:text-red-500"
                                                >
                                                    Reject
                                                </button>
                                            </span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

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
    const [showLdForm, setShowLdForm] = useState(false);
    const ld = useForm({
        title: '',
        hours: '',
        date: '',
        certificate: null,
        photo: null,
    });

    const submitLd = (e) => {
        e.preventDefault();
        ld.post(route('ld.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                ld.reset();
                setShowLdForm(false);
            },
        });
    };

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
                    <Stat value={`${me.ld_hours}h`} label={`approved, of ${ldTarget}h · ${year}`} />
                    {me.ld_pending > 0 ? (
                        <p className="mt-1 text-xs text-slate-400">
                            {me.ld_pending}h still pending
                        </p>
                    ) : (
                        <p className="mt-1 text-xs font-medium text-emerald-600">
                            target met
                        </p>
                    )}
                    <button
                        type="button"
                        onClick={() => setShowLdForm(true)}
                        className="mt-3 inline-block text-sm font-medium text-blue-600 hover:text-blue-500"
                    >
                        Submit a training →
                    </button>
                </BigBox>
            </div>

            {me.ld_entries.length > 0 && (
                <div className="mt-8 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <p className="border-b border-slate-100 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        My trainings · {year}
                    </p>
                    <ul className="divide-y divide-slate-100 text-sm">
                        {me.ld_entries.map((l, i) => (
                            <li key={i} className="px-4 py-2.5">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-slate-700">{l.title}</span>
                                    <span className="flex shrink-0 items-center gap-2 text-slate-500">
                                        {l.hours}h · {l.date}
                                        <LdStatus status={l.status} />
                                    </span>
                                </div>
                                <div className="mt-0.5 flex items-center justify-between">
                                    <LdProofLinks entry={l} />
                                    {l.status === 'rejected' && l.remarks && (
                                        <p className="text-xs text-red-600">
                                            {l.remarks}
                                        </p>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Submit-a-training modal */}
            <Modal show={showLdForm} onClose={() => setShowLdForm(false)} maxWidth="md">
                <form onSubmit={submitLd} className="p-6">
                    <h3 className="text-lg font-semibold text-slate-800">
                        Submit a training
                    </h3>
                    <p className="mt-1 text-sm text-slate-500">
                        Attach the certificate, a photo taken during the
                        training, or both. The hours count once an admin
                        approves.
                    </p>
                    <div className="mt-5 space-y-4">
                        <div>
                            <InputLabel htmlFor="my_ld_title" value="Training title" />
                            <TextInput
                                id="my_ld_title"
                                className="mt-1 block w-full"
                                value={ld.data.title}
                                onChange={(e) => ld.setData('title', e.target.value)}
                            />
                            <InputError message={ld.errors.title} className="mt-1" />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="my_ld_hours" value="Hours" />
                                <TextInput
                                    id="my_ld_hours"
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
                                <InputLabel htmlFor="my_ld_date" value="Date" />
                                <TextInput
                                    id="my_ld_date"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={ld.data.date}
                                    onChange={(e) => ld.setData('date', e.target.value)}
                                />
                                <InputError message={ld.errors.date} className="mt-1" />
                            </div>
                        </div>
                        <div>
                            <InputLabel htmlFor="my_ld_cert" value="Certificate (photo/scan)" />
                            <input
                                id="my_ld_cert"
                                type="file"
                                accept="image/*"
                                className="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100"
                                onChange={(e) => ld.setData('certificate', e.target.files[0] ?? null)}
                            />
                            <InputError message={ld.errors.certificate} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="my_ld_photo" value="Photo during the training" />
                            <input
                                id="my_ld_photo"
                                type="file"
                                accept="image/*"
                                className="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100"
                                onChange={(e) => ld.setData('photo', e.target.files[0] ?? null)}
                            />
                            <InputError message={ld.errors.photo} className="mt-1" />
                        </div>
                        <p className="text-xs text-slate-400">
                            At least one image is required · JPG/PNG · max 5 MB each.
                        </p>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={() => setShowLdForm(false)}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={ld.processing}>
                            {ld.processing ? 'Submitting…' : 'Submit for approval'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </div>
    );
}

/* ── Page ─────────────────────────────────────────────────────────────── */

export default function Dashboard(props) {
    const page = usePage().props;
    const flash = page.flash;
    const { isAdmin, isSuperadmin } = page.auth;

    // Holidays (admin) and Audit (superadmin) used to be top-level nav; they
    // now live here as quiet quick-buttons beside the title.
    const quickLinks = props.mode === 'admin' && (
        <div className="flex flex-wrap items-center gap-2">
            {isAdmin && (
                <Link
                    href={route('admin.holidays.index')}
                    className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900"
                >
                    📅 Holidays
                </Link>
            )}
            {isSuperadmin && (
                <Link
                    href={route('admin.audit.index')}
                    className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900"
                >
                    🧾 Audit trail
                </Link>
            )}
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold text-slate-800">
                        Dashboard
                    </h2>
                    {quickLinks}
                </div>
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
