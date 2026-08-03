import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const navy = '#0b2a52';

function Field({ label, children }) {
    return (
        <div>
            <div className="text-xs font-semibold uppercase tracking-wide text-gray-400">{label}</div>
            <div className="text-sm text-gray-800">{children || '—'}</div>
        </div>
    );
}

export default function Show({ form, canEdit, canDelete }) {
    const del = () => {
        if (confirm('Delete this IPCR? This cannot be undone.')) {
            router.delete(route('ipcr.destroy', form.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        IPCR · {form.ratee}
                    </h2>
                    <div className="flex gap-2">
                        <a
                            href={route('ipcr.print', form.id)}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Print
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
                <div className="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                    <section className="rounded-lg bg-white p-6 shadow-sm">
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <Field label="Ratee">{form.ratee}</Field>
                            <Field label="Rating period">{form.rating_period}</Field>
                            <Field label="Position">{form.position_title}</Field>
                            <Field label="Office / Unit">{form.office_unit}</Field>
                            <Field label="Status">
                                <span className="capitalize">{form.status}</span>
                            </Field>
                            <Field label="Overall">
                                {form.overall_rating
                                    ? `${form.overall_rating} · ${form.fe_overall_adjectival_rating}`
                                    : '—'}
                            </Field>
                        </div>
                    </section>

                    {form.groups.map((g, gi) => (
                        <section key={gi} className="rounded-lg bg-white shadow-sm">
                            <div
                                className="rounded-t-lg px-6 py-3 text-sm font-semibold text-white"
                                style={{ background: navy }}
                            >
                                MFO #{gi + 1}
                                {g.average_rating != null && (
                                    <span className="float-right text-amber-300">
                                        Avg {g.average_rating}
                                    </span>
                                )}
                            </div>
                            <div className="space-y-3 p-6 text-sm">
                                <Field label="Major Final Output / KRA">{g.major_final_output}</Field>
                                <Field label="Success indicator">{g.success_indicator}</Field>
                                <Field label="Actual accomplishment">{g.actual_accomplishment}</Field>
                                <div className="grid grid-cols-3 gap-4 md:grid-cols-4">
                                    <Field label="Quality">{g.quality_rating}</Field>
                                    <Field label="Timeliness">{g.timeliness_rating}</Field>
                                    <Field label="Quantity">{g.quantity_rating}</Field>
                                    <Field label="Average">{g.average_rating}</Field>
                                </div>
                                {g.remarks && <Field label="Remarks">{g.remarks}</Field>}
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
