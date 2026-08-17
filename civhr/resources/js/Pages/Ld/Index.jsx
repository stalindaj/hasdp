import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

/**
 * Admin → L&D: the whole roster's Learning & Development standing for a year.
 *
 * Each employee's target is set by salary grade (8h for SG 1–14, 40h for
 * SG 15 and up). Approved hours count; the queue up top clears pending
 * submissions. Logging a training for someone is done from their employee card.
 */

/** "Certificate · Photo" proof links for a pending submission. */
function ProofLinks({ entry }) {
    if (!entry.certificate && !entry.photo) return null;
    return (
        <span className="space-x-3 text-xs">
            {entry.certificate && (
                <a href={entry.certificate} target="_blank" rel="noopener"
                   className="font-medium text-blue-600 underline-offset-2 hover:underline">
                    Certificate
                </a>
            )}
            {entry.photo && (
                <a href={entry.photo} target="_blank" rel="noopener"
                   className="font-medium text-blue-600 underline-offset-2 hover:underline">
                    Photo
                </a>
            )}
        </span>
    );
}

function Stat({ value, label, tone }) {
    const tones = {
        default: 'text-slate-900',
        good: 'text-emerald-600',
        warn: 'text-amber-600',
        info: 'text-blue-600',
    };
    return (
        <div className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p className={`text-2xl font-bold tabular-nums ${tones[tone] ?? tones.default}`}>{value}</p>
            <p className="text-xs text-slate-500">{label}</p>
        </div>
    );
}

export default function Index({ year, years, rows, pending, summary }) {
    const flash = usePage().props.flash;

    const changeYear = (y) =>
        router.get(route('ld.index', { year: y }), {}, { preserveScroll: true, preserveState: true });

    const decide = (entry, decision) => {
        let remarks = null;
        if (decision === 'rejected') {
            remarks = prompt(`Reason for rejecting "${entry.title}"?`);
            if (!remarks) return; // cancelled
        }
        router.patch(route('ld.decide', entry.id), { decision, remarks }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Learning &amp; Development
                    </h2>
                    <label className="flex items-center gap-2 text-sm text-slate-600">
                        Year
                        <select
                            value={year}
                            onChange={(e) => changeYear(e.target.value)}
                            className="rounded-md border-slate-300 py-1 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            {years.map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                    </label>
                </div>
            }
        >
            <Head title="Learning & Development" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-5 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">
                            {flash.success}
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Stat value={summary.total} label="employees" />
                        <Stat value={summary.met} label={`met target · ${year}`} tone="good" />
                        <Stat value={summary.behind} label="below target" tone="warn" />
                        <Stat value={summary.pending} label="awaiting approval" tone="info" />
                    </div>

                    {/* Approval queue */}
                    {pending.length > 0 && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
                            <h3 className="text-sm font-semibold text-amber-900">
                                Pending approval ({pending.length})
                            </h3>
                            <ul className="mt-3 divide-y divide-amber-100">
                                {pending.map((l) => (
                                    <li key={l.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-slate-800">{l.employee}</p>
                                            <p className="text-xs text-slate-500">
                                                {l.title} · {l.hours}h · {l.date}
                                            </p>
                                            <ProofLinks entry={l} />
                                        </div>
                                        <div className="flex shrink-0 gap-3 text-sm font-medium">
                                            <button onClick={() => decide(l, 'approved')}
                                                    className="text-emerald-700 hover:underline">
                                                Approve
                                            </button>
                                            <button onClick={() => decide(l, 'rejected')}
                                                    className="text-red-600 hover:underline">
                                                Reject
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Roster */}
                    <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 text-left">Employee</th>
                                    <th className="px-4 py-3 text-center">SG</th>
                                    <th className="px-4 py-3 text-center">Target</th>
                                    <th className="px-4 py-3 text-center">Approved</th>
                                    <th className="px-4 py-3 text-center">Remaining</th>
                                    <th className="px-4 py-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rows.map((r) => (
                                    <tr key={r.id} className="hover:bg-slate-50">
                                        <td className="px-4 py-2.5">
                                            <Link
                                                href={route('dashboard.employee', r.id)}
                                                className="font-medium text-slate-800 hover:text-blue-600"
                                            >
                                                {r.name}
                                            </Link>
                                            <span className="ml-2 text-xs text-slate-400">#{r.emp_no}</span>
                                            {r.pending > 0 && (
                                                <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800">
                                                    {r.pending} pending
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5 text-center tabular-nums text-slate-500">
                                            {r.sg ?? '—'}
                                        </td>
                                        <td className="px-4 py-2.5 text-center tabular-nums text-slate-600">
                                            {r.target}h
                                        </td>
                                        <td className="px-4 py-2.5 text-center font-semibold tabular-nums text-slate-900">
                                            {r.hours}h
                                        </td>
                                        <td className="px-4 py-2.5 text-center tabular-nums text-slate-600">
                                            {r.remaining > 0 ? `${r.remaining}h` : '—'}
                                        </td>
                                        <td className="px-4 py-2.5 text-center">
                                            {r.met ? (
                                                <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">
                                                    met
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-200">
                                                    behind
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-6 text-center text-sm text-slate-400">
                                            No employees on the roster.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <p className="text-xs text-slate-400">
                        Target follows salary grade — 8 hours a year for SG 1–14,
                        40 for SG 15 and up. Only approved hours count. To log a
                        training for someone, open their card from the roster.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
