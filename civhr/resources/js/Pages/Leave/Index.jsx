import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatusBadge from '@/Components/StatusBadge';
import { Head, Link } from '@inertiajs/react';

export default function Index({ applications }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        My Leave
                    </h2>
                    <Link
                        href={route('leave.create')}
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                    >
                        File leave (CS Form No. 6)
                    </Link>
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
                            <div className="overflow-x-auto">
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
                                                <span className="space-x-3">
                                                    {/* The form is readable at
                                                        every stage, not only
                                                        once approved. */}
                                                    {a.status !== 'cancelled' && (
                                                        <a
                                                            href={route('leave.print', a.id)}
                                                            target="_blank"
                                                            rel="noopener"
                                                            className="font-medium text-indigo-600 hover:text-indigo-500"
                                                        >
                                                            {a.status === 'approved'
                                                                ? 'Print form'
                                                                : 'View form'}
                                                        </a>
                                                    )}
                                                    <Link
                                                        href={route('leave.show', a.id)}
                                                        className="font-medium text-indigo-600 hover:text-indigo-500"
                                                    >
                                                        {a.status === 'approved' &&
                                                        !a.signed_form
                                                            ? 'Upload signed copy'
                                                            : 'View'}
                                                    </Link>
                                                    {a.signed_form && (
                                                        <span
                                                            className="text-emerald-600"
                                                            title="Signed form on file"
                                                        >
                                                            ✓
                                                        </span>
                                                    )}
                                                </span>
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
