import FormE from '@/Components/Ipcr/FormE';
import Matrix from '@/Components/Ipcr/Matrix';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

const navy = '#0b2a52';

const STATUS_STYLES = {
    draft: 'bg-gray-100 text-gray-600',
    submitted: 'bg-amber-100 text-amber-800',
    reviewed: 'bg-sky-100 text-sky-800',
    approved: 'bg-emerald-100 text-emerald-800',
    returned: 'bg-rose-100 text-rose-800',
    rejected: 'bg-rose-100 text-rose-800',
};

function Card({ title, children }) {
    return (
        <section className="overflow-hidden rounded-2xl bg-white shadow-sm">
            <div
                className="px-6 py-3 text-sm font-semibold text-white"
                style={{ background: navy }}
            >
                {title}
            </div>
            <div className="overflow-x-auto p-4">{children}</div>
        </section>
    );
}

export default function Show({ form, canEdit, canDelete, canSubmit, canDecide, canUploadScan, hasScan }) {
    const scan = useForm({ scan: null });

    const del = () => {
        if (confirm('Delete this IPCR? This cannot be undone.')) {
            router.delete(route('ipcr.destroy', form.id));
        }
    };
    const submitForApproval = () => router.post(route('ipcr.submit', form.id));
    const decide = (decision) => router.post(route('ipcr.decide', form.id), { decision });
    const uploadScan = (e) => {
        e.preventDefault();
        scan.post(route('ipcr.scan.store', form.id), { forceFormData: true, onSuccess: () => scan.reset() });
    };

    // The generated sheets are read-only here — they are what the print view
    // puts on paper.
    const sheet = { data: form, rateeName: form.ratee, readOnly: true };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">IPCR · {form.ratee}</h2>
                    <div className="flex flex-wrap gap-2">
                        <a
                            href={route('ipcr.print', form.id)}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-md bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600"
                        >
                            Print forms (Matrix + Form E)
                        </a>
                        {canEdit && (
                            <Link
                                href={route('ipcr.edit', form.id)}
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
            <Head title={`IPCR · ${form.ratee}`} />

            <div className="py-8">
                <div className="mx-auto max-w-[100rem] space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Status + workflow */}
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
                            {form.overall_rating && (
                                <span className="text-xs font-semibold text-[#0b2a52]">
                                    Overall {form.overall_rating} · {form.fe_overall_adjectival_rating}
                                </span>
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

                    <Card title="IPCR Form Matrix">
                        <Matrix {...sheet} />
                    </Card>

                    <Card title="IPCR Form (FORM E)">
                        <FormE {...sheet} signedDate={form.submitted_at ?? ''} />
                    </Card>

                    {/* Scanned wet-signed copy (after approval) */}
                    {(canUploadScan || hasScan) && (
                        <section className="rounded-2xl bg-white p-6 shadow-sm">
                            <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
                                Scanned signed copy
                            </h3>
                            {hasScan ? (
                                <a
                                    href={route('ipcr.scan', form.id)}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-block rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    View uploaded copy
                                </a>
                            ) : (
                                <p className="text-sm text-gray-500">No scanned copy yet.</p>
                            )}
                            {canUploadScan && (
                                <form onSubmit={uploadScan} className="mt-4 flex flex-wrap items-center gap-3">
                                    <input
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(e) => scan.setData('scan', e.target.files[0])}
                                        className="text-sm"
                                    />
                                    <button
                                        type="submit"
                                        disabled={scan.processing || !scan.data.scan}
                                        className="rounded-md bg-[#0b2a52] px-4 py-2 text-sm font-medium text-white hover:bg-[#071b35] disabled:opacity-50"
                                    >
                                        {hasScan ? 'Replace' : 'Upload'}
                                    </button>
                                    {scan.errors.scan && (
                                        <span className="text-xs text-rose-600">{scan.errors.scan}</span>
                                    )}
                                </form>
                            )}
                        </section>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
