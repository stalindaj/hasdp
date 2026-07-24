import { useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatusBadge from '@/Components/StatusBadge';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, Link, useForm, usePage, router } from '@inertiajs/react';

const DETAIL_LABELS = {
    within_philippines: 'Within the Philippines',
    abroad: 'Abroad',
    in_hospital: 'In Hospital',
    out_patient: 'Out Patient',
    masters: "Completion of Master's Degree",
    bar_board: 'BAR/Board Examination Review',
    monetization: 'Monetization of Leave Credits',
    terminal: 'Terminal Leave',
    requested: 'Requested',
    not_requested: 'Not Requested',
};

function Card({ title, children, actions }) {
    return (
        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
            <header className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-base font-semibold text-gray-900">{title}</h3>
                {actions}
            </header>
            {children}
        </section>
    );
}

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-gray-500">
                {label}
            </dt>
            <dd className="mt-0.5 text-sm text-gray-900">{children || '—'}</dd>
        </div>
    );
}

/* ── The wet-signed CS Form 6, filed digitally after approval ─────────── */
function SignedFormCard({ application, canUpload }) {
    const { data, setData, post, processing, errors, recentlySuccessful } =
        useForm({ signed_form: null });

    const submit = (e) => {
        e.preventDefault();
        post(route('leave.signed-form.store', application.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setData('signed_form', null),
        });
    };

    const uploaded = application.signed_form?.uploaded;

    return (
        <Card title="Signed form on file">
            {uploaded ? (
                <p className="text-sm text-gray-700">
                    ✅ Uploaded {application.signed_form.at} —{' '}
                    <a
                        href={route('leave.signed-form', application.id)}
                        target="_blank"
                        rel="noopener"
                        className="font-medium text-indigo-600 underline-offset-2 hover:underline"
                    >
                        view the signed form
                    </a>
                </p>
            ) : (
                <p className="text-sm text-gray-600">
                    {canUpload
                        ? 'Print the form, gather all the signatures, then upload a photo or scan of the completed form here so it stays on file.'
                        : 'The signed copy has not been uploaded yet.'}
                </p>
            )}

            {canUpload && (
                <form onSubmit={submit} className="mt-3 flex flex-wrap items-center gap-3">
                    <input
                        type="file"
                        accept="image/*,application/pdf"
                        className="block text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                        onChange={(e) => setData('signed_form', e.target.files[0] ?? null)}
                    />
                    <PrimaryButton disabled={processing || !data.signed_form}>
                        {processing
                            ? 'Uploading…'
                            : uploaded
                              ? 'Replace upload'
                              : 'Upload signed form'}
                    </PrimaryButton>
                    {recentlySuccessful && (
                        <span className="text-xs text-green-600">Saved.</span>
                    )}
                    <InputError message={errors.signed_form} />
                    <p className="w-full text-xs text-gray-400">
                        Photo or PDF · max 8 MB.
                    </p>
                </form>
            )}
        </Card>
    );
}

/* ── Admin: type the 7.B recommending officer, saved on its own ───────── */
function RecommenderCard({ application, signatories }) {
    const { data, setData, patch, processing, errors, recentlySuccessful } =
        useForm({
            recommender_name: signatories.recommender?.name ?? '',
            recommender_rank: signatories.recommender?.rank ?? '',
            recommender_office: signatories.recommender?.office ?? '',
        });

    const save = (e) => {
        e.preventDefault();
        patch(route('leave.recommender', application.id), { preserveScroll: true });
    };

    return (
        <Card title="Signatories on the printed form">
            <dl className="grid gap-4 sm:grid-cols-2">
                <Field label="7.A — Authorized officer">
                    {signatories.certifier}
                </Field>
                <Field label="7.C / 7.D — Authorized official">
                    {signatories.approver}
                </Field>
            </dl>

            <form
                onSubmit={save}
                className="mt-5 border-t border-gray-100 pt-4"
            >
                <p className="mb-2 text-xs uppercase tracking-wide text-gray-500">
                    7.B — Recommending officer
                </p>
                <div className="grid gap-4 sm:grid-cols-7">
                    <div className="sm:col-span-3">
                        <InputLabel
                            htmlFor="recommender_name"
                            value="Name"
                        />
                        <TextInput
                            id="recommender_name"
                            className="mt-1 block w-full uppercase"
                            placeholder="e.g. JUAN P DELA CRUZ"
                            value={data.recommender_name}
                            onChange={(e) =>
                                setData('recommender_name', e.target.value)
                            }
                        />
                        <InputError
                            message={errors.recommender_name}
                            className="mt-1"
                        />
                    </div>
                    <div className="sm:col-span-1">
                        <InputLabel htmlFor="recommender_rank" value="Rank" />
                        <TextInput
                            id="recommender_rank"
                            className="mt-1 block w-full"
                            placeholder="MAJ"
                            value={data.recommender_rank}
                            onChange={(e) =>
                                setData('recommender_rank', e.target.value)
                            }
                        />
                        <InputError
                            message={errors.recommender_rank}
                            className="mt-1"
                        />
                    </div>
                    <div className="sm:col-span-3">
                        <InputLabel
                            htmlFor="recommender_office"
                            value="Office / designation"
                        />
                        <TextInput
                            id="recommender_office"
                            className="mt-1 block w-full"
                            placeholder="e.g. Chief, MPMBR"
                            value={data.recommender_office}
                            onChange={(e) =>
                                setData('recommender_office', e.target.value)
                            }
                        />
                        <InputError
                            message={errors.recommender_office}
                            className="mt-1"
                        />
                    </div>
                </div>
                <div className="mt-3 flex items-center gap-3">
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Saving…' : 'Save 7.B'}
                    </PrimaryButton>
                    {recentlySuccessful && (
                        <p className="text-xs text-green-600">Saved.</p>
                    )}
                </div>
            </form>

            <p className="mt-3 text-xs text-gray-500">
                7.A and 7.C/7.D are fixed (set via the “HR Officer” / “Approving
                Official” roles under Admin → Users). 7.B prints exactly as
                typed: name centred, rank at the left with the branch opposite,
                office on the line below. Leave the name blank (and save) to
                clear 7.B.
            </p>
        </Card>
    );
}

/* ── Admin: fill 7.A credits + decide (7.C / 7.D) ─────────────────────── */
function ProcessForm({ application, prefill, balanceCheck }) {
    const c = application.certification ?? {};
    const d = application.decision ?? {};
    const decided = !!d.at;   // already approved/disapproved once

    // CSC rules: days beyond the available balance are granted without pay —
    // prefill the 7.C split straight from the credit check.
    const suggestedWithPay =
        balanceCheck?.kind != null
            ? balanceCheck.with_pay
            : (application.working_days ?? '');
    const suggestedWithoutPay =
        balanceCheck?.kind != null ? balanceCheck.without_pay || '' : '';

    const { data, setData, post, patch, processing, errors } = useForm({
        decision: d.value || 'approved',
        cert_as_of: c.as_of || prefill?.cert_as_of || new Date().toISOString().slice(0, 10),
        vl_earned: c.vl_earned ?? prefill?.vl_earned ?? '',
        vl_less: c.vl_less ?? prefill?.vl_less ?? '',
        vl_balance: c.vl_balance ?? prefill?.vl_balance ?? '',
        sl_earned: c.sl_earned ?? prefill?.sl_earned ?? '',
        sl_less: c.sl_less ?? prefill?.sl_less ?? '',
        sl_balance: c.sl_balance ?? prefill?.sl_balance ?? '',
        days_with_pay: String(d.days_with_pay ?? suggestedWithPay ?? ''),
        days_without_pay: String(d.days_without_pay ?? suggestedWithoutPay ?? ''),
        days_others: String(d.days_others ?? ''),
        days_others_specify: d.others_specify ?? '',
        disapproval_reason: d.reason ?? '',
    });

    const approved = data.decision === 'approved';

    const submit = (e) => {
        e.preventDefault();
        post(route('leave.decide', application.id), { preserveScroll: true });
    };

    // Save the figures as a draft, without approving/disapproving.
    const saveDraft = () => {
        patch(route('leave.save', application.id), { preserveScroll: true });
    };

    const grid = [
        ['Vacation Leave', 'vl_earned', 'vl_less', 'vl_balance'],
        ['Sick Leave', 'sl_earned', 'sl_less', 'sl_balance'],
    ];

    return (
        <Card title={decided ? 'Update this leave' : 'Process this leave'}>
            {decided && (
                <p className="mb-4 text-sm text-gray-600">
                    This leave was already {d.value}. You can change the
                    recommending officer, the credits, or the decision and save
                    again.
                </p>
            )}
            <form onSubmit={submit} className="space-y-6">
                {/* Credit check — CSC: certify credits before approval */}
                {balanceCheck && (
                    <div className="rounded-md border border-slate-200 p-4">
                        <p className="mb-2 text-sm font-medium text-slate-700">
                            Applicant's balances as of{' '}
                            {new Date().toLocaleString('en-US', {
                                month: 'long',
                                year: 'numeric',
                            })}
                        </p>
                        <table className="w-full max-w-md text-center text-sm">
                            <thead>
                                <tr className="text-xs uppercase tracking-wide text-slate-400">
                                    <th className="pb-1">VL</th>
                                    <th className="pb-1">SL</th>
                                    <th className="pb-1">Wellness</th>
                                    <th className="pb-1">SPL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr className="text-base font-semibold text-slate-900">
                                    <td>{balanceCheck.balances.vl}</td>
                                    <td>{balanceCheck.balances.sl}</td>
                                    <td>{balanceCheck.balances.wellness}</td>
                                    <td>{balanceCheck.balances.spl}</td>
                                </tr>
                            </tbody>
                        </table>

                        {balanceCheck.kind ? (
                            balanceCheck.sufficient ? (
                                <p className="mt-3 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-emerald-200">
                                    This {balanceCheck.type} draws{' '}
                                    <span className="font-semibold">
                                        {balanceCheck.days} day(s) from{' '}
                                        {balanceCheck.kind}
                                    </span>{' '}
                                    — sufficient. Balance after approval:{' '}
                                    <span className="font-semibold">
                                        {balanceCheck.after}
                                    </span>
                                    .
                                </p>
                            ) : (
                                <p className="mt-3 rounded bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200">
                                    Insufficient {balanceCheck.kind}: only{' '}
                                    <span className="font-semibold">
                                        {balanceCheck.balance}
                                    </span>{' '}
                                    available for{' '}
                                    <span className="font-semibold">
                                        {balanceCheck.days} day(s)
                                    </span>
                                    . Per CSC rules the excess may be granted{' '}
                                    <span className="font-semibold">
                                        without pay
                                    </span>{' '}
                                    — 7.C below is pre-split:{' '}
                                    {balanceCheck.with_pay} with pay /{' '}
                                    {balanceCheck.without_pay} without pay.
                                </p>
                            )
                        ) : (
                            <p className="mt-3 text-xs text-slate-500">
                                {balanceCheck.type} does not deduct from these
                                credit balances.
                            </p>
                        )}
                    </div>
                )}

                {/* 7.A credits */}
                <div>
                    <p className="mb-2 text-sm font-medium text-gray-700">
                        7.A — Certification of leave credits
                    </p>
                    <div className="mb-3 max-w-xs">
                        <InputLabel htmlFor="cert_as_of" value="As of" />
                        <TextInput
                            id="cert_as_of"
                            type="date"
                            className="mt-1 block w-full"
                            value={data.cert_as_of}
                            onChange={(e) => setData('cert_as_of', e.target.value)}
                        />
                    </div>
                    <table className="w-full max-w-2xl border border-gray-200 text-sm">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="border-b border-gray-200 px-3 py-2"></th>
                                <th className="border-b border-gray-200 px-3 py-2 text-left font-medium text-gray-600">
                                    Total Earned
                                </th>
                                <th className="border-b border-gray-200 px-3 py-2 text-left font-medium text-gray-600">
                                    Less this application
                                </th>
                                <th className="border-b border-gray-200 px-3 py-2 text-left font-medium text-gray-600">
                                    Balance
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {grid.map(([label, earned, less, balance]) => (
                                <tr key={label}>
                                    <td className="px-3 py-2 font-medium text-gray-700">
                                        {label}
                                    </td>
                                    {[earned, less, balance].map((f) => (
                                        <td key={f} className="px-2 py-2">
                                            <TextInput
                                                type="number"
                                                step="0.001"
                                                className="block w-full text-sm"
                                                value={data[f]}
                                                onChange={(e) =>
                                                    setData(f, e.target.value)
                                                }
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <p className="mt-1 text-xs text-gray-500">
                        Auto-filled from length of service (1.25 days/month). It
                        does not subtract leave already taken — adjust to the true
                        balance.
                    </p>
                </div>

                {/* Decision */}
                <div>
                    <p className="mb-2 text-sm font-medium text-gray-700">
                        Decision
                    </p>
                    <div className="space-y-1">
                        {[
                            ['approved', '7.C — Approve'],
                            ['disapproved', '7.D — Disapprove'],
                        ].map(([v, label]) => (
                            <label
                                key={v}
                                className="flex cursor-pointer items-center gap-2"
                            >
                                <input
                                    type="radio"
                                    name="decision"
                                    checked={data.decision === v}
                                    onChange={() => setData('decision', v)}
                                    className="border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span className="text-sm text-gray-700">
                                    {label}
                                </span>
                            </label>
                        ))}
                    </div>

                    {approved ? (
                        <div className="mt-3 grid gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel
                                    htmlFor="days_with_pay"
                                    value="Days with pay"
                                />
                                <TextInput
                                    id="days_with_pay"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    className="mt-1 block w-full"
                                    value={data.days_with_pay}
                                    onChange={(e) =>
                                        setData('days_with_pay', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="days_without_pay"
                                    value="Days without pay"
                                />
                                <TextInput
                                    id="days_without_pay"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    className="mt-1 block w-full"
                                    value={data.days_without_pay}
                                    onChange={(e) =>
                                        setData(
                                            'days_without_pay',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="days_others"
                                    value="Others"
                                />
                                <TextInput
                                    id="days_others"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    className="mt-1 block w-full"
                                    value={data.days_others}
                                    onChange={(e) =>
                                        setData('days_others', e.target.value)
                                    }
                                />
                            </div>
                            <div className="sm:col-span-3">
                                <InputLabel
                                    htmlFor="days_others_specify"
                                    value="Others — specify"
                                />
                                <TextInput
                                    id="days_others_specify"
                                    className="mt-1 block w-full"
                                    value={data.days_others_specify}
                                    onChange={(e) =>
                                        setData(
                                            'days_others_specify',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                    ) : (
                        <div className="mt-3">
                            <InputLabel
                                htmlFor="disapproval_reason"
                                value="Disapproved due to (printed on 7.D)"
                            />
                            <textarea
                                id="disapproval_reason"
                                rows={3}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.disapproval_reason}
                                onChange={(e) =>
                                    setData('disapproval_reason', e.target.value)
                                }
                            />
                            <InputError
                                message={errors.disapproval_reason}
                                className="mt-1"
                            />
                        </div>
                    )}
                </div>

                <div className="flex items-center justify-end gap-3">
                    {!decided && (
                        <SecondaryButton
                            type="button"
                            disabled={processing}
                            onClick={saveDraft}
                        >
                            Save draft
                        </SecondaryButton>
                    )}
                    <PrimaryButton disabled={processing}>
                        {processing
                            ? 'Saving…'
                            : decided
                              ? 'Save changes'
                              : approved
                                ? 'Approve leave'
                                : 'Disapprove leave'}
                    </PrimaryButton>
                </div>
                {!decided && (
                    <p className="text-right text-xs text-gray-500">
                        “Save draft” keeps the leave pending; “Approve / Disapprove”
                        finalises it.
                    </p>
                )}
            </form>
        </Card>
    );
}

export default function Show({ application: a, can, signatories, creditPrefill, balanceCheck }) {
    const flash = usePage().props.flash;

    const cancel = () => {
        if (confirm('Cancel this leave application? This cannot be undone.')) {
            router.post(route('leave.cancel', a.id), {}, { preserveScroll: true });
        }
    };

    const hasDetails = useMemo(
        () =>
            a.details.vacation ||
            a.details.sick ||
            a.details.women_illness ||
            a.details.study ||
            a.details.other_purpose,
        [a.details],
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {a.type}
                        </h2>
                        <StatusBadge status={a.status} label={a.status_label} />
                    </div>
                    <div className="flex items-center gap-3">
                        {can.cancel && (
                            <button
                                onClick={cancel}
                                className="text-sm font-medium text-red-600 hover:text-red-500"
                            >
                                Cancel application
                            </button>
                        )}
                        {can.print && (
                            <a
                                href={route('leave.print', a.id)}
                                target="_blank"
                                rel="noopener"
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                            >
                                View / print CS Form No. 6
                            </a>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`Leave — ${a.type}`} />

            <div className="py-8">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800 ring-1 ring-green-200">
                            {flash.success}
                        </div>
                    )}

                    {a.status === 'approved' && !can.process && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800 ring-1 ring-green-200">
                            Your leave is <span className="font-semibold">approved</span>.
                            Use <span className="font-medium">View / print CS Form No. 6</span> above,
                            print it, sign by pen — then upload the completed
                            form below so it stays on file.
                        </div>
                    )}
                    {a.status === 'disapproved' && !can.process && (
                        <div className="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200">
                            Your leave was disapproved.
                            {a.decision.reason ? ` Reason: ${a.decision.reason}` : ''}
                        </div>
                    )}

                    <Card title="Application">
                        <dl className="grid gap-4 sm:grid-cols-3">
                            <Field label="Applicant">{a.applicant}</Field>
                            <Field label="Office / Department">
                                {a.office_department}
                            </Field>
                            <Field label="Position">{a.position}</Field>
                            <Field label="Date of filing">{a.date_filing}</Field>
                            <Field label="Salary grade">{a.salary}</Field>
                            <Field label="Working days">{a.working_days}</Field>
                            <Field label="Inclusive dates">{a.inclusive}</Field>
                            <Field label="Commutation">
                                {DETAIL_LABELS[a.commutation]}
                            </Field>
                        </dl>

                        {hasDetails && (
                            <dl className="mt-5 grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-3">
                                {a.details.vacation && (
                                    <Field label="Vacation / SPL">
                                        {DETAIL_LABELS[a.details.vacation]}
                                        {a.details.vacation_location
                                            ? ` — ${a.details.vacation_location}`
                                            : ''}
                                    </Field>
                                )}
                                {a.details.sick && (
                                    <Field label="Sick leave">
                                        {DETAIL_LABELS[a.details.sick]}
                                        {a.details.sick_illness
                                            ? ` — ${a.details.sick_illness}`
                                            : ''}
                                    </Field>
                                )}
                                {a.details.women_illness && (
                                    <Field label="Special leave (women)">
                                        {a.details.women_illness}
                                    </Field>
                                )}
                                {a.details.study && (
                                    <Field label="Study leave">
                                        {DETAIL_LABELS[a.details.study]}
                                    </Field>
                                )}
                                {a.details.other_purpose && (
                                    <Field label="Other purpose">
                                        {DETAIL_LABELS[a.details.other_purpose]}
                                    </Field>
                                )}
                            </dl>
                        )}
                    </Card>

                    {(can.upload_form ||
                        (a.status === 'approved' && can.process)) && (
                        <SignedFormCard
                            application={a}
                            canUpload={can.upload_form}
                        />
                    )}

                    {can.process && signatories && (
                        <RecommenderCard
                            application={a}
                            signatories={signatories}
                        />
                    )}

                    {can.process && (
                        <ProcessForm
                            application={a}
                            prefill={creditPrefill}
                            balanceCheck={balanceCheck}
                        />
                    )}

                    {a.decision.at && !can.process && (
                        <Card title="Action taken">
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field label="Decision">
                                    {a.decision.value === 'approved'
                                        ? 'Approved'
                                        : 'Disapproved'}
                                </Field>
                                <Field label="Authorized officer">
                                    {a.officer}
                                </Field>
                                {a.decision.value === 'approved' ? (
                                    <>
                                        <Field label="Days with pay">
                                            {a.decision.days_with_pay}
                                        </Field>
                                        <Field label="Days without pay">
                                            {a.decision.days_without_pay}
                                        </Field>
                                    </>
                                ) : (
                                    <Field label="Reason">
                                        {a.decision.reason}
                                    </Field>
                                )}
                            </dl>
                        </Card>
                    )}

                    <Card title="History">
                        <ol className="space-y-3">
                            {a.history.map((h) => (
                                <li
                                    key={h.id}
                                    className="flex gap-3 text-sm text-gray-700"
                                >
                                    <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gray-400" />
                                    <div>
                                        <span className="font-medium capitalize text-gray-900">
                                            {h.action}
                                        </span>{' '}
                                        by {h.by}
                                        <span className="text-gray-500">
                                            {' '}
                                            &middot; {h.at}
                                        </span>
                                        {h.remarks && (
                                            <p className="mt-0.5 text-gray-600">
                                                {h.remarks}
                                            </p>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </Card>

                    <div>
                        <Link
                            href={route('leave.index')}
                            className="text-sm text-gray-600 underline hover:text-gray-900"
                        >
                            &larr; Back to leave
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
