import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatusBadge from '@/Components/StatusBadge';
import { Head, Link } from '@inertiajs/react';

export default function Index({ applications, isAdmin, pendingCount }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        My Leave
                    </h2>
                    <div className="flex items-center gap-3">
                        {isAdmin && (
                            <Link
                                href={route('leave.requests')}
                                className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Leave requests
                                {pendingCount > 0 && (
                                    <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1.5 text-xs font-semibold text-white">
                                        {pendingCount}
                                    </span>
                                )}
                            </Link>
                        )}
                        <Link
                            href={route('leave.create')}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        >
                            File leave (CS Form No. 6)
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="My Leave" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {applications.length === 0 ? (
                            <div className="px-6 py-16 text-center">
                                <p className="text-sm text-gray-600">
                                    You have not filed any leave applications yet.
                                </p>
                                <Link
                                    href={route('leave.create')}
                                    className="mt-4 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                                >
                                    File your first application
                                </Link>
                            </div>
                        ) : (
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        {['Filed', 'Type', 'Inclusive dates', 'Days', 'Status', ''].map(
                                            (h) => (
                                                <th
                                                    key={h}
                                                    className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                                >
                                                    {h}
                                                </th>
                                            ),
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {applications.map((a) => (
                                        <tr key={a.id} className="hover:bg-gray-50">
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {a.date_filing}
                                            </td>
                                            <td className="px-6 py-4 text-sm font-medium text-gray-900">
                                                {a.type}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {a.inclusive}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                                {a.working_days}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <StatusBadge
                                                    status={a.status}
                                                    label={a.status_label}
                                                />
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                {a.status === 'approved' ? (
                                                    <a
                                                        href={route('leave.print', a.id)}
                                                        target="_blank"
                                                        rel="noopener"
                                                        className="font-medium text-indigo-600 hover:text-indigo-500"
                                                    >
                                                        Print form
                                                    </a>
                                                ) : (
                                                    <Link
                                                        href={route('leave.show', a.id)}
                                                        className="font-medium text-indigo-600 hover:text-indigo-500"
                                                    >
                                                        View
                                                    </Link>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
