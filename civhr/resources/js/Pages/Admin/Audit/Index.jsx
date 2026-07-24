import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import TextInput from '@/Components/TextInput';
import { Head, Link } from '@inertiajs/react';

const AREAS = ['All', 'Leave', 'L&D', 'Credits'];

function AreaChip({ area }) {
    const styles = {
        Leave: 'bg-amber-50 text-amber-700 ring-amber-200',
        'L&D': 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        Credits: 'bg-blue-50 text-blue-700 ring-blue-200',
    };
    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset ${styles[area] ?? ''}`}
        >
            {area}
        </span>
    );
}

/**
 * The audit trail — superadmin only. Read-only by design: the underlying
 * records are append-only, so nothing here can be edited or deleted.
 */
export default function Index({ events, total, counts }) {
    const [area, setArea] = useState('All');
    const [q, setQ] = useState('');

    const rows = useMemo(() => {
        const needle = q.trim().toLowerCase();
        return events.filter(
            (e) =>
                (area === 'All' || e.area === area) &&
                (!needle ||
                    [e.by, e.subject, e.action, e.details]
                        .join(' ')
                        .toLowerCase()
                        .includes(needle)),
        );
    }, [events, area, q]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Audit trail
                        </h2>
                        <p className="text-sm text-slate-500">
                            Every approval, adjustment and upload · visible to
                            you alone
                        </p>
                    </div>
                    <a
                        href={route('admin.audit.export')}
                        className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Download CSV
                    </a>
                </div>
            }
        >
            <Head title="Audit trail" />

            <div className="py-8">
                <div className="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="flex flex-wrap gap-1.5">
                            {AREAS.map((a) => (
                                <button
                                    key={a}
                                    type="button"
                                    onClick={() => setArea(a)}
                                    className={
                                        'rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset transition ' +
                                        (area === a
                                            ? 'bg-blue-900 text-white ring-blue-900'
                                            : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50')
                                    }
                                >
                                    {a}
                                    {a !== 'All' && (
                                        <span className="ml-1 opacity-60">
                                            {a === 'Leave'
                                                ? counts.leave
                                                : a === 'L&D'
                                                  ? counts.ld
                                                  : counts.credit}
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>
                        <TextInput
                            className="w-full max-w-xs text-sm"
                            placeholder="Search name, action, details…"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                        <span className="text-xs text-slate-400">
                            {rows.length} of {total} events
                        </span>
                    </div>

                    <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="whitespace-nowrap px-4 py-3">When</th>
                                    <th className="px-3 py-3">Area</th>
                                    <th className="px-4 py-3">Action</th>
                                    <th className="px-4 py-3">By</th>
                                    <th className="px-4 py-3">Subject</th>
                                    <th className="px-4 py-3">Details</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rows.map((e, i) => (
                                    <tr key={i} className="hover:bg-slate-50">
                                        <td className="whitespace-nowrap px-4 py-2.5 text-xs tabular-nums text-slate-500">
                                            {e.at}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <AreaChip area={e.area} />
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2.5 font-medium capitalize text-slate-800">
                                            {e.link ? (
                                                <Link
                                                    href={e.link}
                                                    className="hover:text-blue-600"
                                                >
                                                    {e.action}
                                                </Link>
                                            ) : (
                                                e.action
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-slate-700">
                                            {e.by}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-slate-700">
                                            {e.subject}
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-500">
                                            {e.details}
                                        </td>
                                    </tr>
                                ))}
                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-10 text-center text-sm text-slate-400"
                                        >
                                            Nothing matches that filter.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <p className="text-xs text-slate-400">
                        Read-only. The monthly +1.25 accruals are omitted — only
                        manual credit adjustments appear here. The CSV carries
                        the full stream.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
