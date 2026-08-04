import Matrix from '@/Components/Ipcr/Matrix';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const navy = '#0b2a52';

const STATUS_STYLES = {
    draft: 'bg-gray-100 text-gray-600',
    submitted: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    returned: 'bg-rose-100 text-rose-800',
};

function Field({ label, children }) {
    return (
        <div>
            <div className="text-xs font-semibold uppercase tracking-wide text-gray-400">{label}</div>
            <div className="text-sm text-gray-800">{children || '—'}</div>
        </div>
    );
}

export default function Show({ form, canEdit, canDelete, canSubmit, canDecide }) {
    const del = () => {
        if (confirm('Delete this IWOT? This cannot be undone.')) {
            router.delete(route('iwot.destroy', form.id));
        }
    };
    const submitForApproval = () => router.post(route('iwot.submit', form.id));
    const decide = (decision) => router.post(route('iwot.decide', form.id), { decision });

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        IWOT · {form.employee}
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        <a
                            href={route('iwot.print', form.id)}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-md bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600"
                        >
                            Print / sign IWOT
                        </a>
                        {canEdit && (
                            <Link
                                href={route('iwot.edit', form.id)}
                                className="rounded-md bg-[#0b2a52] px-4 py-2 text-sm font-medium text-white hover:bg-[#071b35]"
                            >
                                Edit
                            </Link>
                        )}
                        {canDelete && (
                            <button
                                onClick={del}
                                className="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50"
                            >
                                Delete
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`IWOT · ${form.employee}`} />

            <div className="py-8">
                <div className="mx-auto max-w-[100rem] space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white p-4 shadow-sm">
                        <div className="flex flex-wrap items-center gap-3">
                            <span
                                className={`inline-block rounded-full px-3 py-1 text-xs font-medium capitalize ${
                                    STATUS_STYLES[form.status] ?? 'bg-gray-100 text-gray-600'
                                }`}
                            >
                                {form.status}
                            </span>
                            {form.submitted_at && (
                                <span className="text-xs text-gray-500">Submitted {form.submitted_at}</span>
                            )}
                            {form.approved_at && (
                                <span className="text-xs text-gray-500">Approved {form.approved_at}</span>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {canSubmit && (
                                <button
                                    onClick={submitForApproval}
                                    className="rounded-md bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600"
                                >
                                    Submit for approval
                                </button>
                            )}
                            {canDecide && (
                                <>
                                    <button
                                        onClick={() => decide('approve')}
                                        className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        onClick={() => decide('return')}
                                        className="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50"
                                    >
                                        Return
                                    </button>
                                </>
                            )}
                        </div>
                    </section>

                    <section className="rounded-2xl bg-white p-6 shadow-sm">
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <Field label="Personnel">{form.employee}</Field>
                            <Field label="Position">{form.position_title}</Field>
                            <Field label="Office / Unit">{form.office_unit}</Field>
                            <Field label="Period covered">{form.rating_period}</Field>
                            <Field label="Prepared by">{form.prepared_by}</Field>
                            <Field label="Approved by">{form.approved_by}</Field>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl bg-white shadow-sm">
                        <div className="px-6 py-3 text-sm font-semibold text-white" style={{ background: navy }}>
                            IWOT Matrix
                        </div>
                        <div className="overflow-x-auto p-4">
                            <Matrix data={form} rateeName={form.employee} readOnly />
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
