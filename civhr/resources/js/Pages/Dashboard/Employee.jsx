import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const KINDS = [
    ['vl', 'Vacation Leave'],
    ['sl', 'Sick Leave'],
    ['wellness', 'Wellness Leave'],
    ['spl', 'Special Privilege'],
];

function Card({ title, children, actions }) {
    return (
        <section className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <header className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    {title}
                </h3>
                {actions}
            </header>
            {children}
        </section>
    );
}

/** 'YYYY-MM-DD' in local time — toISOString would shift the PH date. */
function isoDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** Mirrors App\Support\WorkingDays::count() for the prefilled day count. */
function countDays(fromStr, toStr, basis, holidays) {
    if (!fromStr || !toStr) return null;
    const from = new Date(`${fromStr}T00:00:00`);
    const to = new Date(`${toStr}T00:00:00`);
    if (isNaN(from) || isNaN(to) || to < from) return null;

    let days = 0;
    for (let d = new Date(from), guard = 0; d <= to && guard < 1000; d.setDate(d.getDate() + 1), guard++) {
        if (basis === 'calendar') { days++; continue; }
        const weekend = d.getDay() === 0 || d.getDay() === 6;
        if (!weekend && !holidays[isoDate(d)]) days++;
    }
    return days;
}

export default function EmployeeCard({ year, ldTarget, employee, ipcr, balances, forced, ledger, ld, leaves, leaveTypes, holidays }) {
    const flash = usePage().props.flash;
    const [adjusting, setAdjusting] = useState(null); // kind being adjusted
    const [ldOpen, setLdOpen] = useState(false);
    const [recordOpen, setRecordOpen] = useState(false);

    const record = useForm({
        leave_type_id: '',
        date_from: '',
        date_to: '',
        working_days: '',
        remarks: '',
    });

    const recordType = leaveTypes?.find(
        (t) => String(t.id) === String(record.data.leave_type_id),
    );

    // Prefill the day count from the dates; the admin can still override it
    // to match the paper record.
    const syncDays = (from, to, typeId) => {
        const type = leaveTypes?.find((t) => String(t.id) === String(typeId));
        const n = countDays(from, to, type?.day_basis ?? 'working', holidays ?? {});
        if (n !== null) record.setData('working_days', String(n));
    };

    const submitRecord = (e) => {
        e.preventDefault();
        record.post(route('dashboard.record-leave', employee.id), {
            preserveScroll: true,
            onSuccess: () => {
                record.reset();
                setRecordOpen(false);
            },
        });
    };

    const adjust = useForm({ kind: 'vl', amount: '', note: '' });
    const ldForm = useForm({
        title: '',
        hours: '',
        date: new Date().toISOString().slice(0, 10),
    });

    const openAdjust = (kind) => {
        adjust.setData({ kind, amount: '', note: '' });
        setAdjusting(kind);
    };

    // One click pre-fills the CSC year-end forfeiture of unused forced leave.
    const openForfeiture = () => {
        adjust.setData({
            kind: 'vl',
            amount: String(-forced.remaining),
            note: `Forfeiture of unused mandatory/forced leave ${year} (CSC Sec. 25, Rule XVI)`,
        });
        setAdjusting('vl');
    };

    const submitAdjust = (e) => {
        e.preventDefault();
        adjust.post(route('dashboard.credit', employee.id), {
            preserveScroll: true,
            onSuccess: () => setAdjusting(null),
        });
    };

    const submitLd = (e) => {
        e.preventDefault();
        ldForm.post(route('dashboard.ld', employee.id), {
            preserveScroll: true,
            onSuccess: () => {
                ldForm.reset();
                setLdOpen(false);
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold text-slate-800">
                            {employee.name}
                        </h2>
                        <p className="text-sm text-slate-500">
                            #{employee.emp_no}
                            {employee.position ? ` · ${employee.position}` : ''}
                        </p>
                    </div>
                    <Link
                        href={route('dashboard')}
                        className="text-sm text-slate-600 underline hover:text-slate-900"
                    >
                        ← Dashboard
                    </Link>
                </div>
            }
        >
            <Head title={employee.name} />

            <div className="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">
                        {flash.success}
                    </div>
                )}

                {/* Balances table — click a number to adjust it */}
                <Card
                    title={`Total leave balances as of ${new Date().toLocaleString('en-US', { month: 'long', year: 'numeric' })}`}
                    actions={
                        <span className="text-xs text-slate-400">
                            VL/SL accrue +1.25 monthly · click a number to adjust
                        </span>
                    }
                >
                    <div className="overflow-x-auto">
                        <table className="w-full max-w-xl text-center">
                            <thead>
                                <tr className="border-b border-slate-200 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {KINDS.map(([kind, label]) => (
                                        <th key={kind} className="px-4 py-2">
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    {KINDS.map(([kind]) => (
                                        <td key={kind} className="px-4 py-3">
                                            <button
                                                type="button"
                                                onClick={() => openAdjust(kind)}
                                                title="Adjust this balance"
                                                className="rounded-md px-3 py-1 text-2xl font-bold text-slate-900 transition hover:bg-blue-50 hover:text-blue-700"
                                            >
                                                {balances[kind]}
                                            </button>
                                        </td>
                                    ))}
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* CSC Sec. 25, Rule XVI — 5 VL days must be used yearly */}
                    <div
                        className={
                            'mt-4 flex flex-wrap items-center justify-between gap-2 rounded-md px-3 py-2 text-sm ring-1 ' +
                            (forced.remaining > 0
                                ? 'bg-amber-50 text-amber-800 ring-amber-200'
                                : 'bg-emerald-50 text-emerald-800 ring-emerald-200')
                        }
                    >
                        <span>
                            Mandatory/forced leave {year}:{' '}
                            <span className="font-semibold">
                                {forced.used} of {forced.required} days used
                            </span>
                            {forced.remaining > 0
                                ? ` — ${forced.remaining} day(s) still to take; unused days are forfeited at year-end.`
                                : ' — requirement met.'}
                        </span>
                        {forced.remaining > 0 && (
                            <button
                                type="button"
                                onClick={openForfeiture}
                                className="rounded-md border border-amber-300 bg-white px-2.5 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100"
                                title="Pre-fills a VL adjustment of the unused forced days — use at year-end"
                            >
                                Apply year-end forfeiture…
                            </button>
                        )}
                    </div>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Credit ledger */}
                    <Card title="Credit history">
                        {ledger.length === 0 ? (
                            <p className="text-sm text-slate-400">No entries yet.</p>
                        ) : (
                            <ul className="max-h-80 divide-y divide-slate-100 overflow-y-auto text-sm">
                                {ledger.map((l) => (
                                    <li key={l.id} className="flex items-center justify-between py-2">
                                        <div>
                                            <p className="text-slate-700">{l.note}</p>
                                            <p className="text-xs text-slate-400">
                                                {l.kind} · {l.date}
                                            </p>
                                        </div>
                                        <span
                                            className={
                                                'font-semibold ' +
                                                (l.amount >= 0
                                                    ? 'text-emerald-600'
                                                    : 'text-red-600')
                                            }
                                        >
                                            {l.amount >= 0 ? '+' : ''}
                                            {l.amount}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    {/* IPCR — status only (the IPCR system is separate) */}
                    <Card
                        title={`IPCR compliance`}
                        actions={
                            <span className="text-xs text-slate-400">
                                tracked here, filed in the IPCR system
                            </span>
                        }
                    >
                        {ipcr.length === 0 ? (
                            <p className="text-sm text-slate-400">
                                No IPCR records yet — tick semesters from the dashboard.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                            <table className="w-full min-w-[18rem] text-sm">
                                <thead className="text-left text-xs uppercase tracking-wide text-slate-400">
                                    <tr>
                                        <th className="py-1">Year</th>
                                        <th className="py-1">1st sem</th>
                                        <th className="py-1">2nd sem</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {ipcr.map((r) => (
                                        <tr key={r.year}>
                                            <td className="py-2 font-medium text-slate-700">
                                                {r.year}
                                            </td>
                                            <td className="py-2">{r.sem1 ? '✓ passed' : '—'}</td>
                                            <td className="py-2">{r.sem2 ? '✓ passed' : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            </div>
                        )}
                    </Card>
                </div>

                {/* L&D */}
                <Card
                    title={`Learning & Development · ${year}`}
                    actions={
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="text-xs text-slate-500">
                                {ld.hours}h of {ldTarget}h
                                {ld.pending > 0 ? ` · ${ld.pending}h pending` : ' · target met'}
                            </span>
                            <button
                                onClick={() => setLdOpen(true)}
                                className="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-500"
                            >
                                + Log training
                            </button>
                        </div>
                    }
                >
                    {ld.entries.length === 0 ? (
                        <p className="text-sm text-slate-400">No trainings logged this year.</p>
                    ) : (
                        <ul className="divide-y divide-slate-100 text-sm">
                            {ld.entries.map((l) => (
                                <li key={l.id} className="py-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-slate-700">{l.title}</span>
                                        <span className="flex shrink-0 items-center gap-2 text-slate-500">
                                            {l.hours}h · {l.date}
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset ${
                                                    {
                                                        pending: 'bg-amber-50 text-amber-700 ring-amber-200',
                                                        approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                                        rejected: 'bg-red-50 text-red-700 ring-red-200',
                                                    }[l.status] ?? ''
                                                }`}
                                            >
                                                {l.status}
                                            </span>
                                        </span>
                                    </div>
                                    <div className="mt-0.5 flex items-center justify-between">
                                        <span className="space-x-2 text-xs">
                                            {l.certificate && (
                                                <a href={l.certificate} target="_blank" rel="noopener" className="font-medium text-blue-600 underline-offset-2 hover:underline">
                                                    Certificate
                                                </a>
                                            )}
                                            {l.photo && (
                                                <a href={l.photo} target="_blank" rel="noopener" className="font-medium text-blue-600 underline-offset-2 hover:underline">
                                                    Photo
                                                </a>
                                            )}
                                        </span>
                                        {l.status === 'pending' && (
                                            <span className="space-x-2 text-xs font-medium">
                                                <button
                                                    onClick={() =>
                                                        router.patch(route('ld.decide', l.id), { decision: 'approved' }, { preserveScroll: true })
                                                    }
                                                    className="text-emerald-600 hover:text-emerald-500"
                                                >
                                                    Approve
                                                </button>
                                                <button
                                                    onClick={() => {
                                                        const remarks = prompt(`Reason for rejecting "${l.title}"?`);
                                                        if (remarks)
                                                            router.patch(route('ld.decide', l.id), { decision: 'rejected', remarks }, { preserveScroll: true });
                                                    }}
                                                    className="text-red-600 hover:text-red-500"
                                                >
                                                    Reject
                                                </button>
                                            </span>
                                        )}
                                        {l.status === 'rejected' && l.remarks && (
                                            <p className="text-xs text-red-600">{l.remarks}</p>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                {/* Leave applications */}
                <Card
                    title="Leave applications"
                    actions={
                        <button
                            onClick={() => setRecordOpen(true)}
                            className="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-500"
                            title="Key in a leave already taken (paper form, or before go-live)"
                        >
                            + Record leave used
                        </button>
                    }
                >
                    {leaves.length === 0 ? (
                        <p className="text-sm text-slate-400">None filed yet.</p>
                    ) : (
                        <ul className="divide-y divide-slate-100 text-sm">
                            {leaves.map((a) => (
                                <li key={a.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <Link
                                        href={route('leave.show', a.id)}
                                        className="text-slate-700 hover:text-blue-600"
                                    >
                                        {a.type} · {a.days}d · {a.when}
                                    </Link>
                                    <span className="flex items-center gap-2">
                                        {a.recorded && (
                                            <span
                                                className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500"
                                                title="Keyed in by an admin, not filed through the system"
                                            >
                                                recorded
                                            </span>
                                        )}
                                        <StatusBadge status={a.status} label={a.status_label} />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            {/* Record a leave already taken */}
            <Modal show={recordOpen} onClose={() => setRecordOpen(false)} maxWidth="lg">
                <form onSubmit={submitRecord} className="p-6">
                    <h3 className="text-lg font-semibold text-slate-800">
                        Record leave used — {employee.name}
                    </h3>
                    <p className="mt-1 text-sm text-slate-500">
                        For leaves taken on paper or before the system went
                        live. It is filed as approved, deducts the right
                        balance, and appears in the logs marked{' '}
                        <span className="font-medium">recorded</span>.
                    </p>
                    <div className="mt-5 space-y-4">
                        <div>
                            <InputLabel htmlFor="rec_type" value="Leave type" />
                            <select
                                id="rec_type"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                value={record.data.leave_type_id}
                                onChange={(e) => {
                                    record.setData('leave_type_id', e.target.value);
                                    syncDays(record.data.date_from, record.data.date_to, e.target.value);
                                }}
                            >
                                <option value="">Select a leave type…</option>
                                {(leaveTypes ?? []).map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={record.errors.leave_type_id} className="mt-1" />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel htmlFor="rec_from" value="From" />
                                <TextInput
                                    id="rec_from"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={record.data.date_from}
                                    onChange={(e) => {
                                        record.setData('date_from', e.target.value);
                                        syncDays(e.target.value, record.data.date_to, record.data.leave_type_id);
                                    }}
                                />
                                <InputError message={record.errors.date_from} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="rec_to" value="To" />
                                <TextInput
                                    id="rec_to"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={record.data.date_to}
                                    onChange={(e) => {
                                        record.setData('date_to', e.target.value);
                                        syncDays(record.data.date_from, e.target.value, record.data.leave_type_id);
                                    }}
                                />
                                <InputError message={record.errors.date_to} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="rec_days" value="Days used" />
                                <TextInput
                                    id="rec_days"
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    className="mt-1 block w-full"
                                    value={record.data.working_days}
                                    onChange={(e) => record.setData('working_days', e.target.value)}
                                />
                                <InputError message={record.errors.working_days} className="mt-1" />
                            </div>
                        </div>
                        <p className="text-xs text-slate-400">
                            Days are counted from the dates
                            {recordType?.day_basis === 'calendar'
                                ? ' as calendar days'
                                : ', skipping weekends and holidays'}{' '}
                            — edit the number if the paper record differs.
                        </p>

                        <div>
                            <InputLabel htmlFor="rec_remarks" value="Remarks (kept in the audit trail)" />
                            <TextInput
                                id="rec_remarks"
                                className="mt-1 block w-full"
                                placeholder="e.g. Paper CS Form 6 dated 12 Mar 2026"
                                value={record.data.remarks}
                                onChange={(e) => record.setData('remarks', e.target.value)}
                            />
                            <InputError message={record.errors.remarks} className="mt-1" />
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={() => setRecordOpen(false)}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={record.processing}>
                            {record.processing ? 'Recording…' : 'Record leave'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Adjust balance modal */}
            <Modal show={!!adjusting} onClose={() => setAdjusting(null)} maxWidth="md">
                {adjusting && (
                    <form onSubmit={submitAdjust} className="p-6">
                        <h3 className="text-lg font-semibold text-slate-800">
                            Adjust {KINDS.find(([k]) => k === adjusting)?.[1]} — {employee.name}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Positive adds credits, negative removes them (e.g.
                            <span className="font-mono"> 10.5</span> or
                            <span className="font-mono"> -2</span>). Every change is
                            kept in the history.
                        </p>
                        <div className="mt-5 space-y-4">
                            <div>
                                <InputLabel htmlFor="adj_amount" value="Amount (+/-)" />
                                <TextInput
                                    id="adj_amount"
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={adjust.data.amount}
                                    onChange={(e) => adjust.setData('amount', e.target.value)}
                                />
                                <InputError message={adjust.errors.amount} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="adj_note" value="Reason (kept in history)" />
                                <TextInput
                                    id="adj_note"
                                    className="mt-1 block w-full"
                                    placeholder="e.g. Opening balance per 201 file"
                                    value={adjust.data.note}
                                    onChange={(e) => adjust.setData('note', e.target.value)}
                                />
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end gap-3">
                            <SecondaryButton type="button" onClick={() => setAdjusting(null)}>
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton disabled={adjust.processing}>
                                Save adjustment
                            </PrimaryButton>
                        </div>
                    </form>
                )}
            </Modal>

            {/* Log L&D modal */}
            <Modal show={ldOpen} onClose={() => setLdOpen(false)} maxWidth="md">
                <form onSubmit={submitLd} className="p-6">
                    <h3 className="text-lg font-semibold text-slate-800">
                        Log L&amp;D — {employee.name}
                    </h3>
                    <div className="mt-5 space-y-4">
                        <div>
                            <InputLabel htmlFor="ldp_title" value="Training title" />
                            <TextInput
                                id="ldp_title"
                                className="mt-1 block w-full"
                                value={ldForm.data.title}
                                onChange={(e) => ldForm.setData('title', e.target.value)}
                            />
                            <InputError message={ldForm.errors.title} className="mt-1" />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="ldp_hours" value="Hours" />
                                <TextInput
                                    id="ldp_hours"
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    className="mt-1 block w-full"
                                    value={ldForm.data.hours}
                                    onChange={(e) => ldForm.setData('hours', e.target.value)}
                                />
                                <InputError message={ldForm.errors.hours} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="ldp_date" value="Date" />
                                <TextInput
                                    id="ldp_date"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={ldForm.data.date}
                                    onChange={(e) => ldForm.setData('date', e.target.value)}
                                />
                            </div>
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={() => setLdOpen(false)}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={ldForm.processing}>
                            Log training
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
