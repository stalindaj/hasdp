import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
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
            <header className="mb-4 flex items-center justify-between">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    {title}
                </h3>
                {actions}
            </header>
            {children}
        </section>
    );
}

export default function EmployeeCard({ year, ldTarget, employee, ipcr, balances, ledger, ld, leaves }) {
    const flash = usePage().props.flash;
    const [adjusting, setAdjusting] = useState(null); // kind being adjusted
    const [ldOpen, setLdOpen] = useState(false);

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
                <div className="flex items-center justify-between">
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

                {/* Balances — click a tile to adjust it */}
                <Card
                    title="Leave balances"
                    actions={
                        <span className="text-xs text-slate-400">
                            VL/SL accrue +1.25 monthly · click a balance to adjust
                        </span>
                    }
                >
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {KINDS.map(([kind, label]) => (
                            <button
                                key={kind}
                                type="button"
                                onClick={() => openAdjust(kind)}
                                className="rounded-xl border border-slate-200 p-4 text-left transition hover:border-blue-300 hover:shadow"
                                title="Adjust this balance"
                            >
                                <p className="text-2xl font-bold text-slate-900">
                                    {balances[kind]}
                                </p>
                                <p className="text-xs text-slate-500">{label}</p>
                            </button>
                        ))}
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
                            <table className="w-full text-sm">
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
                        )}
                    </Card>
                </div>

                {/* L&D */}
                <Card
                    title={`Learning & Development · ${year}`}
                    actions={
                        <div className="flex items-center gap-3">
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
                                <li key={l.id} className="flex items-center justify-between py-2">
                                    <span className="text-slate-700">{l.title}</span>
                                    <span className="text-slate-500">
                                        {l.hours}h · {l.date}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                {/* Leave applications */}
                <Card title="Leave applications">
                    {leaves.length === 0 ? (
                        <p className="text-sm text-slate-400">None filed yet.</p>
                    ) : (
                        <ul className="divide-y divide-slate-100 text-sm">
                            {leaves.map((a) => (
                                <li key={a.id} className="flex items-center justify-between py-2">
                                    <Link
                                        href={route('leave.show', a.id)}
                                        className="text-slate-700 hover:text-blue-600"
                                    >
                                        {a.type} · {a.days}d · {a.when}
                                    </Link>
                                    <StatusBadge status={a.status} label={a.status_label} />
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

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
