import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Modal from '@/Components/Modal';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const KINDS = [
    ['vl', 'VL'],
    ['sl', 'SL'],
    ['wellness', 'Wellness'],
    ['spl', 'SPL'],
];

/**
 * Admin → Balances: the whole roster's leave credits in one grid.
 * Click any number to set it — the change is stored as a ledger adjustment,
 * so the audit trail (who, when, why) is preserved.
 */
export default function Index({ rows, totals, showArchived, archivedCount }) {
    const flash = usePage().props.flash;

    // {row, kind} currently being edited, or null.
    const [editing, setEditing] = useState(null);
    const { data, setData, patch, processing, errors, reset } = useForm({
        kind: '',
        value: '',
        note: '',
    });

    const open = (row, kind) => {
        setEditing({ row, kind });
        setData({ kind, value: String(row[kind]), note: '' });
    };

    const close = () => {
        setEditing(null);
        reset();
    };

    const save = (e) => {
        e.preventDefault();
        patch(route('admin.balances.update', editing.row.id), {
            preserveScroll: true,
            onSuccess: close,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Leave balances
                </h2>
            }
        >
            <Head title="Leave balances" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">
                            {flash.success}
                        </div>
                    )}

                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <p className="text-sm text-gray-600">
                            Everyone&rsquo;s current credits. Click a number to
                            set it (e.g. the opening balance from the 201 file)
                            — every change is recorded in the employee&rsquo;s
                            ledger with your reason.
                        </p>
                        <Link
                            href={route('admin.balances.index', showArchived ? {} : { archived: 1 })}
                            className="shrink-0 text-xs text-gray-400 underline-offset-2 hover:text-indigo-600 hover:underline"
                        >
                            {showArchived
                                ? 'hide archived'
                                : `show archived${archivedCount ? ` (${archivedCount})` : ''}`}
                        </Link>
                    </div>

                    <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 text-left">Employee</th>
                                    {KINDS.map(([, label]) => (
                                        <th key={label} className="px-4 py-3 text-center">
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rows.map((r) => (
                                    <tr key={r.id} className={`hover:bg-slate-50 ${r.archived ? 'bg-slate-50/60' : ''}`}>
                                        <td className="px-4 py-2.5">
                                            <Link
                                                href={route('dashboard.employee', r.id)}
                                                className="font-medium text-slate-800 hover:text-blue-600"
                                            >
                                                {r.name}
                                            </Link>
                                            <span className="ml-2 text-xs text-slate-400">
                                                #{r.emp_no}
                                            </span>
                                            {r.archived && (
                                                <span className="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                                    archived
                                                </span>
                                            )}
                                        </td>
                                        {KINDS.map(([kind]) => (
                                            <td key={kind} className="px-4 py-2.5 text-center">
                                                <button
                                                    type="button"
                                                    onClick={() => open(r, kind)}
                                                    title="Click to set this balance"
                                                    className="rounded-md px-2 py-1 font-semibold tabular-nums text-slate-900 hover:bg-blue-50 hover:text-blue-700"
                                                >
                                                    {r[kind]}
                                                </button>
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-slate-50 text-xs font-semibold text-slate-600">
                                <tr>
                                    <td className="px-4 py-2.5">Total · {rows.length} employees</td>
                                    {KINDS.map(([kind]) => (
                                        <td key={kind} className="px-4 py-2.5 text-center tabular-nums">
                                            {totals[kind]}
                                        </td>
                                    ))}
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {/* Set-balance dialog */}
            <Modal show={!!editing} onClose={close} maxWidth="sm">
                {editing && (
                    <form onSubmit={save} className="p-6">
                        <h3 className="text-lg font-semibold text-slate-800">
                            Set {editing.kind.toUpperCase()} — {editing.row.name}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Current balance:{' '}
                            <span className="font-semibold">{editing.row[editing.kind]}</span>.
                            The difference is posted to the ledger.
                        </p>
                        <div className="mt-4 space-y-4">
                            <div>
                                <InputLabel htmlFor="bal_value" value="New balance" />
                                <TextInput
                                    id="bal_value"
                                    type="number"
                                    step="0.001"
                                    className="mt-1 block w-full"
                                    value={data.value}
                                    onChange={(e) => setData('value', e.target.value)}
                                    autoFocus
                                />
                                <InputError message={errors.value} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="bal_note"
                                    value="Reason (kept in the ledger)"
                                />
                                <TextInput
                                    id="bal_note"
                                    className="mt-1 block w-full"
                                    placeholder="e.g. Opening balance per 201 file"
                                    value={data.note}
                                    onChange={(e) => setData('note', e.target.value)}
                                />
                                <InputError message={errors.note} className="mt-1" />
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end gap-3">
                            <SecondaryButton type="button" onClick={close}>
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving…' : 'Save balance'}
                            </PrimaryButton>
                        </div>
                    </form>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}
