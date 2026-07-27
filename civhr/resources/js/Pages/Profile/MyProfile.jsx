import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SignatureUploader from '@/Components/SignatureUploader';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Transition } from '@headlessui/react';

/** One read-only cell of the official PSIPOP record. */
function Field({ label, value }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-gray-500">
                {label}
            </dt>
            <dd className="mt-0.5 text-sm text-gray-900">{value || '—'}</dd>
        </div>
    );
}

export default function MyProfile({ employee, signature }) {
    const flash = usePage().props.flash;

    const { data, setData, patch, processing, errors, recentlySuccessful } =
        useForm({
            office_department: employee?.office_department ?? '',
            date_of_birth: employee?.date_of_birth ?? '',
            contact_no: employee?.contact_no ?? '',
            tin_no: employee?.tin_no ?? '',
            philhealth_no: employee?.philhealth_no ?? '',
            pagibig_mid: employee?.pagibig_mid ?? '',
        });

    const submit = (e) => {
        e.preventDefault();
        patch(route('my-profile.update'), { preserveScroll: true });
    };

    if (!employee) {
        return (
            <AuthenticatedLayout
                header={
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        My Profile
                    </h2>
                }
            >
                <Head title="My Profile" />
                <div className="py-8">
                    <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <p className="text-sm text-gray-600">
                                Your account is not linked to an employee record
                                yet, so there is nothing to show here. Ask an
                                administrator to link your account to your
                                plantilla record.
                            </p>
                        </div>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    const fullName = [employee.first_name, employee.middle_name, employee.last_name]
        .filter(Boolean)
        .join(' ');

    const editable = [
        ['office_department', 'Office / Department', 'text'],
        ['date_of_birth', 'Date of birth', 'date'],
        ['contact_no', 'Contact number', 'text'],
        ['tin_no', 'TIN', 'text'],
        ['philhealth_no', 'PhilHealth number', 'text'],
        ['pagibig_mid', 'Pag-IBIG MID', 'text'],
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    My Profile
                </h2>
            }
        >
            <Head title="My Profile" />

            <div className="py-8">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800 ring-1 ring-green-200">
                            {flash.success}
                        </div>
                    )}

                    {/* Official record — maintained by HR, read-only here. */}
                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <header className="mb-4">
                            <h3 className="text-base font-semibold text-gray-900">
                                Official record
                            </h3>
                            <p className="mt-1 text-sm text-gray-600">
                                From your plantilla (PSIPOP) record. Ask HR if
                                anything here needs correcting &mdash; it cannot
                                be edited from this page.
                            </p>
                        </header>

                        <dl className="grid gap-4 sm:grid-cols-3">
                            <Field label="Name" value={fullName} />
                            <Field label="Rank" value={employee.rank} />
                            <Field label="Employee no." value={employee.emp_no} />
                            <Field label="Item no." value={employee.item_no} />
                            <Field label="Position" value={employee.position} />
                            <Field
                                label="PSIPOP placement"
                                value={employee.psipop_placement}
                            />
                            <Field label="Level" value={employee.level} />
                            <Field
                                label="Salary grade / step"
                                value={
                                    employee.salary_grade
                                        ? `SG ${employee.salary_grade}${
                                              employee.step_increment
                                                  ? ` — Step ${employee.step_increment}`
                                                  : ''
                                          }`
                                        : null
                                }
                            />
                            <Field label="Sex" value={employee.sex} />
                            <Field label="Age" value={employee.age} />
                            <Field
                                label="Date of original appointment"
                                value={employee.date_orig_appt}
                            />
                            <Field
                                label="Date of assumption to duty"
                                value={employee.date_assumption}
                            />
                        </dl>
                    </section>

                    {/* Personal details — the employee maintains these. */}
                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <header className="mb-4">
                            <h3 className="text-base font-semibold text-gray-900">
                                Personal details
                            </h3>
                            <p className="mt-1 text-sm text-gray-600">
                                Keep these up to date &mdash; HR uses them for
                                your records and benefits.
                            </p>
                        </header>

                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                {editable.map(([field, label, type]) => (
                                    <div key={field}>
                                        <InputLabel htmlFor={field} value={label} />
                                        <TextInput
                                            id={field}
                                            type={type}
                                            className="mt-1 block w-full"
                                            value={data[field]}
                                            onChange={(e) =>
                                                setData(field, e.target.value)
                                            }
                                        />
                                        <InputError
                                            message={errors[field]}
                                            className="mt-1"
                                        />
                                    </div>
                                ))}
                            </div>

                            <div className="flex items-center gap-4">
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Saving…' : 'Save'}
                                </PrimaryButton>

                                <Transition
                                    show={recentlySuccessful}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm text-gray-600">Saved.</p>
                                </Transition>
                            </div>
                        </form>
                    </section>

                    {/* The e-signature printed over your name on CS Form 6. */}
                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <SignatureUploader
                            userId={signature.user_id}
                            url={signature.url}
                            label="My signature"
                        />
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
