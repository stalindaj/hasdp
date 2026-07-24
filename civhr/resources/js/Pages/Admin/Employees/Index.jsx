import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, useForm, usePage } from '@inertiajs/react';

/* The whole plantilla record, grouped the way My Profile displays it. */
const SECTIONS = [
    [
        'Identity',
        [
            ['last_name', 'Last name', 'text', '', 'sm:col-span-2'],
            ['first_name', 'First name', 'text', '', 'sm:col-span-2'],
            ['middle_name', 'Middle name', 'text', '', 'sm:col-span-1'],
            ['suffix', 'Suffix', 'text', 'Jr, III…', 'sm:col-span-1'],
            ['sex', 'Sex', 'select', '', 'sm:col-span-2'],
            ['date_of_birth', 'Date of birth', 'date', 'The age is computed from this', 'sm:col-span-2'],
        ],
    ],
    [
        'Plantilla (PSIPOP)',
        [
            ['emp_no', 'Employee no.', 'text', 'Also the login username — changing it updates the account', 'sm:col-span-2'],
            ['item_no', 'Item no.', 'text', 'e.g. ADAS1-30-2013', 'sm:col-span-2'],
            ['psipop_placement', 'PSIPOP placement', 'text', '', 'sm:col-span-2'],
            ['level', 'Level', 'text', '', 'sm:col-span-2'],
            ['salary_grade', 'Salary grade', 'number', '1–33', 'sm:col-span-1'],
            ['step_increment', 'Step', 'number', '1–8', 'sm:col-span-1'],
        ],
    ],
    [
        'Appointment & office',
        [
            ['position', 'Position', 'text', 'Plantilla title, e.g. Admin Officer IV (HRMO II)', 'sm:col-span-2'],
            ['designation', 'Designation', 'text', 'Signing title, e.g. Director for Personnel', 'sm:col-span-2'],
            ['rank', 'Rank', 'text', 'TSg, 1LT, LTC — blank for civilians', 'sm:col-span-2'],
            ['office_department', 'Office / department', 'text', 'Prints on box 1 of CS Form 6', 'sm:col-span-2'],
            ['date_orig_appt', 'Date of original appointment', 'date', 'Drives the leave-credit estimate', 'sm:col-span-2'],
            ['date_assumption', 'Date of assumption to duty', 'date', '', 'sm:col-span-2'],
            ['date_of_promotion', 'Date of promotion', 'date', '', 'sm:col-span-2'],
        ],
    ],
    [
        'Contact',
        [
            ['email', 'Email', 'email', '', 'sm:col-span-2'],
            ['contact_no', 'Contact no.', 'text', '', 'sm:col-span-2'],
        ],
    ],
];

const FIELD_KEYS = SECTIONS.flatMap(([, fields]) => fields.map(([k]) => k));

function EditModal({ employee, onClose }) {
    const { data, setData, patch, processing, errors, reset } = useForm(
        Object.fromEntries(FIELD_KEYS.map((k) => [k, employee[k] ?? ''])),
    );

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

    return (
        <Modal show={!!employee} onClose={onClose} maxWidth="2xl">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-base font-semibold text-gray-900">
                    Edit {employee.name}
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                    The employee&rsquo;s official record. They see these on My
                    Profile but cannot change them — only admins can.
                </p>

                <div className="mt-5 max-h-[60vh] space-y-6 overflow-y-auto pr-1">
                    {SECTIONS.map(([section, fields]) => (
                        <div key={section}>
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                {section}
                            </p>
                            <div className="grid gap-4 sm:grid-cols-4">
                                {fields.map(([field, label, type, hint, span]) => (
                                    <div key={field} className={span}>
                                        <InputLabel htmlFor={field} value={label} />
                                        {type === 'select' ? (
                                            <select
                                                id={field}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                value={data[field]}
                                                onChange={(e) => setData(field, e.target.value)}
                                            >
                                                <option value="">—</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                        ) : (
                                            <TextInput
                                                id={field}
                                                type={type}
                                                className="mt-1 block w-full"
                                                value={data[field]}
                                                onChange={(e) => setData(field, e.target.value)}
                                            />
                                        )}
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
                        </div>
                    ))}
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={onClose}>
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Saving…' : 'Save record'}
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
