import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, useForm, usePage } from '@inertiajs/react';

function EditModal({ employee, onClose }) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        rank: employee.rank ?? '',
        position: employee.position ?? '',
        designation: employee.designation ?? '',
        date_orig_appt: employee.date_orig_appt ?? '',
        date_assumption: employee.date_assumption ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('admin.employees.update', employee.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    const fields = [
        ['rank', 'Rank', 'text', 'e.g. TSg, 1LT, LTC — leave blank for civilians'],
        ['position', 'Position', 'text', 'Plantilla title, e.g. Admin Officer IV (HRMO II)'],
        ['designation', 'Designation', 'text', 'Signing title, e.g. Director for Personnel'],
        ['date_orig_appt', 'Date of original appointment', 'date', 'Drives the leave-credit estimate'],
        ['date_assumption', 'Date of assumption to duty', 'date', ''],
    ];

    return (
        <Modal show={!!employee} onClose={onClose}>
            <form onSubmit={submit} className="p-6">
                <h2 className="text-base font-semibold text-gray-900">
                    Edit {employee.name}
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                    These fields print on the CS Form No. 6 signature block and
                    drive the leave-credit estimate.
                </p>

                <div className="mt-4 space-y-4">
                    {fields.map(([field, label, type, hint]) => (
                        <div key={field}>
                            <InputLabel htmlFor={field} value={label} />
                            <TextInput
                                id={field}
                                type={type}
                                className="mt-1 block w-full"
                                value={data[field]}
                                onChange={(e) => setData(field, e.target.value)}
                            />
                            {hint && (
                                <p className="mt-1 text-xs text-gray-500">
                                    {hint}
                                </p>
                            )}
                            <InputError
                                message={errors[field]}
                                className="mt-1"
                            />
                        </div>
                    ))}
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={onClose}>
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Saving…' : 'Save'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}

export default function Index({ employees }) {
    const flash = usePage().props.flash;
    const [editing, setEditing] = useState(null);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Employees
                </h2>
            }
        >
            <Head title="Employees" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800 ring-1 ring-green-200">
                            {flash.success}
                        </div>
                    )}

                    <div className="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        {employees.length === 0 ? (
                            <p className="px-6 py-16 text-center text-sm text-gray-600">
                                No employee records yet. They arrive with the
                                roster import.
                            </p>
                        ) : (
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        {[
                                            'Name',
                                            'Rank',
                                            'Position',
                                            'Designation',
                                            'Orig. appt.',
                                            'Est. credits',
                                            '',
                                        ].map((h) => (
                                            <th
                                                key={h}
                                                className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {employees.map((e) => (
                                        <tr key={e.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900">
                                                {e.name}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-500">
                                                {e.rank || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-500">
                                                {e.position || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-500">
                                                {e.designation || '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                {e.date_orig_appt || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-500">
                                                {e.date_orig_appt
                                                    ? `${e.credit_estimate} days`
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right text-sm">
                                                <button
                                                    onClick={() => setEditing(e)}
                                                    className="font-medium text-indigo-600 hover:text-indigo-500"
                                                >
                                                    Edit
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            </div>

            {editing && (
                <EditModal
                    employee={editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
