import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const STATUS_STYLES = {
    draft: 'bg-gray-100 text-gray-600',
    submitted: 'bg-amber-100 text-amber-800',
    reviewed: 'bg-sky-100 text-sky-800',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
};

function StatusPill({ status }) {
    const cls = STATUS_STYLES[status] ?? 'bg-gray-100 text-gray-600';
    return (
        <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${cls}`}>
            {status}
        </span>
    );
}

export default function Index({ forms, isManager }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        IPCR {isManager && <span className="text-gray-400">· all personnel</span>}
                    </h2>
                    <Link
                        href={route('ipcr.create')}
                        className="rounded-md bg-[#0b2a52] px-4 py-2 text-sm font-medium text-white hover:bg-[#071b35]"
                    >
                        + New IPCR
                    </Link>
                </div>
            }
        >
            <Head title="IPCR" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {forms.length === 0 ? (
                            <div className="px-6 py-16 text-center">
                                <p className="text-sm text-gray-600">No IPCR records yet.</p>
                                <Link
                                    href={route('ipcr.create')}
                                    className="mt-4 inline-block rounded-md bg-[#0b2a52] px-4 py-2 text-sm font-medium text-white hover:bg-[#071b35]"
                                >
                                    Create the first one
                                </Link>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                        <tr>
                                            {isManager && <th className="px-6 py-3">Ratee</th>}
                                            <th className="px-6 py-3">Rating period</th>
                                            <th className="px-6 py-3">Status</th>
                                            <th className="px-6 py-3">Rating</th>
                                            <th className="px-6 py-3">Updated</th>
                                            <th className="px-6 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {forms.map((f) => (
                                            <tr key={f.id} className="hover:bg-gray-50">
                                                {isManager && (
                                                    <td className="px-6 py-3 font-medium text-gray-800">
                                                        {f.ratee}
                                                    </td>
                                                )}
                                                <td className="px-6 py-3 text-gray-700">{f.rating_period}</td>
                                                <td className="px-6 py-3">
                                                    <StatusPill status={f.status} />
                                                </td>
                                                <td className="px-6 py-3 text-gray-700">
                                                    {f.overall_rating
                                                        ? `${f.overall_rating} · ${f.adjectival ?? ''}`
                                                        : '—'}
                                                </td>
                                                <td className="px-6 py-3 text-gray-500">{f.updated_at}</td>
                                                <td className="px-6 py-3 text-right">
                                                    <Link
                                                        href={route('ipcr.show', f.id)}
                                                        className="font-medium text-[#0b2a52] hover:underline"
                                                    >
                                                        View
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
