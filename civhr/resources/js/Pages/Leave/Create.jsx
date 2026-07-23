import { useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, Link, useForm } from '@inertiajs/react';

const SELECT =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

function Section({ title, description, children }) {
    return (
        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
            <header className="mb-4">
                <h3 className="text-base font-semibold text-gray-900">{title}</h3>
                {description && (
                    <p className="mt-1 text-sm text-gray-600">{description}</p>
                )}
            </header>
            {children}
        </section>
    );
}

/** A read-only, profile-sourced field on the filing form. */
function LockedField({ label, value, full = false }) {
    return (
        <div className={full ? 'sm:col-span-2' : ''}>
            <dt className="text-xs uppercase tracking-wide text-gray-500">
                {label}
            </dt>
            <dd className="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200">
                {value || <span className="text-gray-400">— not set —</span>}
            </dd>
        </div>
    );
}

/** A radio row styled like the paper form's checkbox lines. */
function Radio({ name, value, checked, onChange, children }) {
    return (
        <label className="flex cursor-pointer items-center gap-2 py-1">
            <input
                type="radio"
                name={name}
                value={value}
                checked={checked}
                onChange={() => onChange(value)}
                className="border-gray-300 text-indigo-600 focus:ring-indigo-500"
            />
            <span className="text-sm text-gray-700">{children}</span>
        </label>
    );
}

/** 'YYYY-MM-DD' in local time — never via toISOString (UTC would shift PH dates). */
function isoDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/**
 * Mirror of App\Support\WorkingDays::count() — the server recomputes on
 * submit; this is only the live preview. Returns null while the range is
 * incomplete or invalid.
 */
function countDays(fromStr, toStr, basis, holidays) {
    if (!fromStr || !toStr) return null;
    const from = new Date(`${fromStr}T00:00:00`);
    const to = new Date(`${toStr}T00:00:00`);
    if (isNaN(from) || isNaN(to) || to < from) return null;

    let days = 0;
    const skipped = []; // holidays actually inside the range, for the note
    for (
        let d = new Date(from), guard = 0;
        d <= to && guard < 1000;
        d.setDate(d.getDate() + 1), guard++
    ) {
        const iso = isoDate(d);
        const holiday = holidays[iso];
        const weekend = d.getDay() === 0 || d.getDay() === 6;

        if (basis === 'calendar') {
            days++;
            continue;
        }
        if (holiday && !weekend) skipped.push({ date: iso, name: holiday });
        if (!weekend && !holiday) days++;
    }

    return { days, skipped };
}

export default function Create({
    leaveTypes,
    prefill,
    holidays,
    hasEmployeeRecord,
}) {
    const { data, setData, post, processing, errors } = useForm({
        leave_type_id: '',
        other_leave_type: '',

        office_department: prefill.office_department ?? '',
        applicant_last_name: prefill.applicant_last_name ?? '',
        applicant_first_name: prefill.applicant_first_name ?? '',
        applicant_middle_name: prefill.applicant_middle_name ?? '',
        date_filing: prefill.date_filing ?? '',
        position: prefill.position ?? '',
        salary: prefill.salary ?? '',

        detail_vacation: '',
        detail_vacation_location: '',
        detail_sick: '',
        detail_sick_illness: '',
        detail_women_illness: '',
        detail_study: '',
        detail_study_other: '',
        detail_other_purpose: '',

        date_from: '',
        date_to: '',
        commutation: 'not_requested',
    });

    const selectedType = useMemo(
        () => leaveTypes.find((t) => String(t.id) === String(data.leave_type_id)),
        [leaveTypes, data.leave_type_id],
    );

    const group = selectedType?.detail_group ?? null;
    const dayBasis = selectedType?.day_basis ?? 'working';

    // 6.C — computed from the inclusive dates; the server recomputes on file.
    const computed = useMemo(
        () => countDays(data.date_from, data.date_to, dayBasis, holidays),
        [data.date_from, data.date_to, dayBasis, holidays],
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('leave.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    File leave &mdash; CS Form No. 6
                </h2>
            }
        >
            <Head title="File leave" />

            <div className="py-8">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    {!hasEmployeeRecord && (
                        <div className="rounded-md bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
                            Your account is not linked to an employee record, so
                            boxes 1&ndash;5 are blank. Ask an administrator to
                            link your account and set your details.
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-6">
                        {/* 1–5 — locked, from the profile */}
                        <Section
                            title="Applicant details"
                            description="Boxes 1 to 5 come from your profile and can't be edited here. To change them, update My Profile (office) or ask HR (name, position, salary grade). Only the date of filing is set below."
                        >
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <LockedField label="1. Office / Department" value={data.office_department} full />
                                <LockedField
                                    label="2. Name"
                                    value={[data.applicant_last_name, data.applicant_first_name, data.applicant_middle_name].filter(Boolean).join(', ')}
                                    full
                                />
                                <LockedField label="4. Position" value={data.position} />
                                <LockedField label="5. Salary grade" value={data.salary} />
                            </dl>

                            {/* hidden values submitted with the form */}
                            {['office_department', 'applicant_last_name', 'applicant_first_name', 'applicant_middle_name', 'position', 'salary'].map((f) => (
                                <input key={f} type="hidden" name={f} value={data[f] ?? ''} readOnly />
                            ))}

                            <div className="mt-4 max-w-xs">
                                <InputLabel htmlFor="date_filing" value="3. Date of filing" />
                                <TextInput
                                    id="date_filing"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.date_filing}
                                    onChange={(e) => setData('date_filing', e.target.value)}
                                />
                                <InputError message={errors.date_filing} className="mt-1" />
                            </div>

                            <p className="mt-4 text-xs text-gray-500">
                                Wrong details?{' '}
                                <Link href={route('my-profile.edit')} className="text-indigo-600 underline">
                                    Update My Profile
                                </Link>
                                .
                            </p>

                            <InputError message={errors.office_department} className="mt-1" />
                            <InputError message={errors.applicant_last_name} className="mt-1" />
                            <InputError message={errors.applicant_first_name} className="mt-1" />
                            <InputError message={errors.position} className="mt-1" />
                        </Section>

                        {/* 6.A */}
                        <Section
                            title="6.A — Type of leave to be availed of"
                            description="Exactly one box is ticked on the printed form."
                        >
                            <select
                                className={SELECT}
                                value={data.leave_type_id}
                                onChange={(e) =>
                                    setData('leave_type_id', e.target.value)
                                }
                            >
                                <option value="">Select a leave type…</option>
                                {leaveTypes.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={errors.leave_type_id}
                                className="mt-1"
                            />

                            {selectedType?.legal_basis && (
                                <p className="mt-2 text-xs italic text-gray-500">
                                    {selectedType.legal_basis}
                                </p>
                            )}

                            {selectedType?.code === 'others' && (
                                <div className="mt-4">
                                    <InputLabel
                                        htmlFor="other_leave_type"
                                        value="Specify the leave"
                                    />
                                    <TextInput
                                        id="other_leave_type"
                                        className="mt-1 block w-full"
                                        value={data.other_leave_type}
                                        onChange={(e) =>
                                            setData(
                                                'other_leave_type',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.other_leave_type}
                                        className="mt-1"
                                    />
                                </div>
                            )}
                        </Section>

                        {/* 6.B — only the block matching the chosen type */}
                        <Section
                            title="6.B — Details of leave"
                            description="The paper form only asks for the block that matches your leave type."
                        >
                            {!selectedType && (
                                <p className="text-sm text-gray-500">
                                    Choose a leave type above to see the details
                                    this form asks for.
                                </p>
                            )}

                            {group === 'vacation' && (
                                <div>
                                    <p className="mb-1 text-sm italic text-gray-600">
                                        In case of Vacation/Special Privilege
                                        Leave:
                                    </p>
                                    <Radio
                                        name="detail_vacation"
                                        value="within_philippines"
                                        checked={
                                            data.detail_vacation ===
                                            'within_philippines'
                                        }
                                        onChange={(v) =>
                                            setData('detail_vacation', v)
                                        }
                                    >
                                        Within the Philippines
                                    </Radio>
                                    <Radio
                                        name="detail_vacation"
                                        value="abroad"
                                        checked={data.detail_vacation === 'abroad'}
                                        onChange={(v) =>
                                            setData('detail_vacation', v)
                                        }
                                    >
                                        Abroad (Specify)
                                    </Radio>
                                    <InputError
                                        message={errors.detail_vacation}
                                        className="mt-1"
                                    />

                                    <div className="mt-3">
                                        <InputLabel
                                            htmlFor="detail_vacation_location"
                                            value={
                                                data.detail_vacation === 'abroad'
                                                    ? 'Country / destination'
                                                    : 'Location'
                                            }
                                        />
                                        <TextInput
                                            id="detail_vacation_location"
                                            className="mt-1 block w-full"
                                            value={data.detail_vacation_location}
                                            onChange={(e) =>
                                                setData(
                                                    'detail_vacation_location',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            )}

                            {group === 'sick' && (
                                <div>
                                    <p className="mb-1 text-sm italic text-gray-600">
                                        In case of Sick Leave:
                                    </p>
                                    <Radio
                                        name="detail_sick"
                                        value="in_hospital"
                                        checked={
                                            data.detail_sick === 'in_hospital'
                                        }
                                        onChange={(v) => setData('detail_sick', v)}
                                    >
                                        In Hospital
                                    </Radio>
                                    <Radio
                                        name="detail_sick"
                                        value="out_patient"
                                        checked={
                                            data.detail_sick === 'out_patient'
                                        }
                                        onChange={(v) => setData('detail_sick', v)}
                                    >
                                        Out Patient
                                    </Radio>
                                    <InputError
                                        message={errors.detail_sick}
                                        className="mt-1"
                                    />

                                    <div className="mt-3">
                                        <InputLabel
                                            htmlFor="detail_sick_illness"
                                            value="Specify illness"
                                        />
                                        <TextInput
                                            id="detail_sick_illness"
                                            className="mt-1 block w-full"
                                            value={data.detail_sick_illness}
                                            onChange={(e) =>
                                                setData(
                                                    'detail_sick_illness',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            )}

                            {group === 'women' && (
                                <div>
                                    <p className="mb-1 text-sm italic text-gray-600">
                                        In case of Special Leave Benefits for
                                        Women:
                                    </p>
                                    <InputLabel
                                        htmlFor="detail_women_illness"
                                        value="Specify illness"
                                    />
                                    <TextInput
                                        id="detail_women_illness"
                                        className="mt-1 block w-full"
                                        value={data.detail_women_illness}
                                        onChange={(e) =>
                                            setData(
                                                'detail_women_illness',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.detail_women_illness}
                                        className="mt-1"
                                    />
                                </div>
                            )}

                            {group === 'study' && (
                                <div>
                                    <p className="mb-1 text-sm italic text-gray-600">
                                        In case of Study Leave:
                                    </p>
                                    <Radio
                                        name="detail_study"
                                        value="masters"
                                        checked={data.detail_study === 'masters'}
                                        onChange={(v) => setData('detail_study', v)}
                                    >
                                        Completion of Master's Degree
                                    </Radio>
                                    <Radio
                                        name="detail_study"
                                        value="bar_board"
                                        checked={data.detail_study === 'bar_board'}
                                        onChange={(v) => setData('detail_study', v)}
                                    >
                                        BAR/Board Examination Review
                                    </Radio>
                                    <InputError
                                        message={errors.detail_study}
                                        className="mt-1"
                                    />
                                </div>
                            )}

                            {selectedType && (
                                <div className="mt-5 border-t border-gray-100 pt-4">
                                    <p className="mb-1 text-sm italic text-gray-600">
                                        Other purpose (optional):
                                    </p>
                                    <Radio
                                        name="detail_other_purpose"
                                        value="monetization"
                                        checked={
                                            data.detail_other_purpose ===
                                            'monetization'
                                        }
                                        onChange={(v) =>
                                            setData('detail_other_purpose', v)
                                        }
                                    >
                                        Monetization of Leave Credits
                                    </Radio>
                                    <Radio
                                        name="detail_other_purpose"
                                        value="terminal"
                                        checked={
                                            data.detail_other_purpose ===
                                            'terminal'
                                        }
                                        onChange={(v) =>
                                            setData('detail_other_purpose', v)
                                        }
                                    >
                                        Terminal Leave
                                    </Radio>
                                    {data.detail_other_purpose && (
                                        <button
                                            type="button"
                                            className="mt-1 text-xs text-gray-500 underline"
                                            onClick={() =>
                                                setData('detail_other_purpose', '')
                                            }
                                        >
                                            Clear
                                        </button>
                                    )}
                                </div>
                            )}
                        </Section>

                        {/* 6.C / 6.D */}
                        <Section
                            title="6.C / 6.D — Working days and commutation"
                            description="Pick the inclusive dates — the number of working days is counted for you."
                        >
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <InputLabel
                                        htmlFor="date_from"
                                        value="Inclusive from"
                                    />
                                    <TextInput
                                        id="date_from"
                                        type="date"
                                        className="mt-1 block w-full"
                                        value={data.date_from}
                                        onChange={(e) =>
                                            setData('date_from', e.target.value)
                                        }
                                    />
                                    <InputError
                                        message={errors.date_from}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <InputLabel htmlFor="date_to" value="To" />
                                    <TextInput
                                        id="date_to"
                                        type="date"
                                        className="mt-1 block w-full"
                                        value={data.date_to}
                                        onChange={(e) =>
                                            setData('date_to', e.target.value)
                                        }
                                    />
                                    <InputError
                                        message={errors.date_to}
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <dt className="text-xs uppercase tracking-wide text-gray-500">
                                        Number of {dayBasis === 'calendar' ? 'calendar' : 'working'} days
                                    </dt>
                                    <dd className="mt-1 rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                                        <span className="text-lg font-semibold text-gray-900">
                                            {computed ? computed.days : '—'}
                                        </span>
                                    </dd>
                                </div>
                            </div>

                            {computed && dayBasis !== 'calendar' && (
                                <div className="mt-3 text-xs text-gray-500">
                                    <p>
                                        Weekends
                                        {computed.skipped.length > 0
                                            ? ' and the holidays below are'
                                            : ' and holidays are'}{' '}
                                        excluded automatically.
                                    </p>
                                    {computed.skipped.length > 0 && (
                                        <ul className="mt-1 list-inside list-disc text-amber-700">
                                            {computed.skipped.map((h) => (
                                                <li key={h.date}>
                                                    {new Date(
                                                        `${h.date}T00:00:00`,
                                                    ).toLocaleDateString('en-US', {
                                                        month: 'long',
                                                        day: 'numeric',
                                                    })}{' '}
                                                    — {h.name}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            )}
                            {computed && dayBasis === 'calendar' && (
                                <p className="mt-3 text-xs text-gray-500">
                                    {selectedType?.name} is counted in calendar
                                    days — weekends and holidays are included.
                                </p>
                            )}
                            {computed && dayBasis !== 'calendar' && computed.days === 0 && (
                                <p className="mt-2 rounded bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200">
                                    These dates fall entirely on weekends or
                                    holidays — there are no working days to
                                    apply for.
                                </p>
                            )}

                            <div className="mt-5">
                                <p className="mb-1 text-sm font-medium text-gray-700">
                                    6.D Commutation
                                </p>
                                <Radio
                                    name="commutation"
                                    value="not_requested"
                                    checked={data.commutation === 'not_requested'}
                                    onChange={(v) => setData('commutation', v)}
                                >
                                    Not Requested
                                </Radio>
                                <Radio
                                    name="commutation"
                                    value="requested"
                                    checked={data.commutation === 'requested'}
                                    onChange={(v) => setData('commutation', v)}
                                >
                                    Requested
                                </Radio>
                                <InputError
                                    message={errors.commutation}
                                    className="mt-1"
                                />
                            </div>
                        </Section>


                        <div className="flex items-center justify-end gap-4">
                            <Link
                                href={route('leave.index')}
                                className="text-sm text-gray-600 underline hover:text-gray-900"
                            >
                                Cancel
                            </Link>
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Filing…' : 'File application'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
