import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatusBadge from '@/Components/StatusBadge';
import { Head, Link } from '@inertiajs/react';

export default function Requests({ applications }) {
    const pending = applications.filter((a) => a.status === 'pending');
    const decided = applications.filter((a) => a.status !== 'pending');

    const Table = ({ rows, emptyText, actionLabel }) =>
        rows.length === 0 ? (
            <p className="px-6 py-10 text-center text-sm text-gray-600">
                {emptyText}
            </p>
        ) : (
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        {['Filed', 'Applicant', 'Type', 'Inclusive dates', 'Days', 'Status', ''].map(
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
                    {rows.map((a) => (
                        <tr key={a.id} className="hover:bg-gray-50">
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {a.date_filing}
                            </td>
                            <td className="px-6 py-4 text-sm font-medium text-gray-900">
                                {a.applicant}
                            </td>
                            <td className="px-6 py-4 text-sm text-gray-500">
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
                                <Link
                                    href={route('leave.show', a.id)}
                                    className="font-medium text-indigo-600 hover:text-indigo-500"
                                >
                                    {actionLabel}
                                </Link>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Leave requests
                    </h2>
                    <Link
                        href={route('leave.index')}
                        className="text-sm text-gray-600 underline hover:text-gray-900"
                    >
                        My leave
                    </Link>
                </div>
            }
        >
            <Head title="Leave requests" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
                    <section>
                        <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                            Awaiting action
                        </h3>
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <Table
                                rows={pending}
                                emptyText="Nothing is waiting for approval."
                                actionLabel="Process"
                            />
                        </div>
                    </section>

                    <section>
                        <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                            Recently decided
                        </h3>
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <Table
                                rows={decided}
                                emptyText="No decided applications yet."
                                actionLabel="View"
                            />
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
