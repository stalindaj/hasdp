import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

/**
 * The employee's own Learning & Development page. The trainings table is the
 * page; recording one — or correcting one already filed — happens in a dialog
 * over it. An edit always sends the row back to the approver, because
 * approved hours already count toward the year's target.
 */

const label = 'block text-xs font-semibold uppercase tracking-wide text-gray-500';
const input =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#0b2a52] focus:ring-[#0b2a52]';
const fileInput =
    'mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-[#0b2a52] file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white';

const STATUS = {
    pending: 'bg-amber-50 text-amber-700 ring-amber-200',
    approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    rejected: 'bg-red-50 text-red-700 ring-red-200',
};

const blank = {
    competency: 'technical',
    title: '',
    date: '',
    date_to: '',
    hours: '',
    certificate: null,
    photo: null,
};

function Stat({ value, caption, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900',
        good: 'text-emerald-600',
        warn: 'text-amber-600',
    };
    return (
        <div className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p className={`text-2xl font-bold tabular-nums ${tones[tone]}`}>{value}</p>
            <p className="text-xs text-slate-500">{caption}</p>
        </div>
    );
}

function Proof({ entry }) {
    if (!entry.certificate && !entry.photo) {
        return <span className="text-xs italic text-slate-400">None yet</span>;
    }
    return (
        <span className="space-x-3 text-xs">
            {entry.certificate && (
                <a href={entry.certificate} target="_blank" rel="noopener"
                   className="font-medium text-blue-600 underline-offset-2 hover:underline">
                    Certificate
                </a>
            )}
            {entry.photo && (
                <a href={entry.photo} target="_blank" rel="noopener"
                   className="font-medium text-blue-600 underline-offset-2 hover:underline">
                    Photo
                </a>
            )}
        </span>
    );
}

export default function Mine({ year, years, competencies, summary, entries }) {
    const flash = usePage().props.flash;

    // null = closed, {} = recording a new one, an entry = correcting it.
    const [editing, setEditing] = useState(null);
    const form = useForm(blank);

    const open = (entry) => {
        form.clearErrors();
        form.setData(
            entry
                ? {
                      competency: entry.competency_key ?? 'technical',
                      title: entry.title,
                      date: entry.date ?? '',
                      date_to: entry.date_to ?? '',
                      hours: String(entry.hours ?? ''),
                      certificate: null,
                      photo: null,
                  }
                : blank,
        );
        setEditing(entry ?? {});
    };

    const close = () => {
        setEditing(null);
        form.reset();
    };

    const submit = (e) => {
        e.preventDefault();
        // Both go through POST: a PATCH with files needs the method spoofed
        // anyway, and the update route takes the same fields as the store.
        const url = editing?.id ? route('ld.update', editing.id) : route('ld.store');
        form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: close });
    };

    const remove = (entry) => {
        if (!confirm(`Remove "${entry.title}"? This cannot be undone.`)) return;
        router.delete(route('ld.destroy', entry.id), { preserveScroll: true });
    };

    const changeYear = (y) =>
        router.get(route('ld.mine', { year: y }), {}, { preserveScroll: true, preserveState: true });

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Learning &amp; Development
                    </h2>
                    <div className="flex items-center gap-3">
                        <select
                            className={`${input} mt-0 w-auto`}
                            value={year}
                            onChange={(e) => changeYear(e.target.value)}
                        >
                            {years.map((y) => (
                                <option key={y} value={y}>
                                    {y}
                                </option>
                            ))}
                        </select>
                        <button
                            onClick={() => open(null)}
                            className="rounded-md bg-[#0b2a52] px-4 py-2 text-sm font-medium text-white hover:bg-[#071b35]"
                        >
                            + Record training
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="My L&D" />

            <div className="py-6">
                <div className="mx-auto max-w-[110rem] space-y-4 px-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <Stat value={`${summary.hours}h`} caption={`Approved in ${year}`} tone={summary.met ? 'good' : 'default'} />
                        <Stat value={`${summary.target}h`} caption="Target for your salary grade" />
                        <Stat
                            value={summary.met ? 'Met' : `${summary.remaining}h`}
                            caption={summary.met ? 'Target met' : 'Still to go'}
                            tone={summary.met ? 'good' : 'warn'}
                        />
                        <Stat
                            value={`${summary.pending}h`}
                            caption="Waiting for approval"
                            tone={summary.pending > 0 ? 'warn' : 'default'}
                        />
                    </div>

                    {/* Hours per track, compact — the table below is the page. */}
                    <div className="flex flex-wrap gap-2">
                        {summary.by_competency.map((c) => (
                            <span
                                key={c.key}
                                className="rounded-full bg-white px-3 py-1 text-xs text-slate-600 shadow-sm ring-1 ring-slate-200"
                            >
                                {c.label}: <strong className="tabular-nums text-slate-800">{c.hours}h</strong>
                            </span>
                        ))}
                    </div>

                    <section className="overflow-hidden rounded-2xl bg-white shadow-sm">
                        <div className="flex items-center justify-between bg-[#0b2a52] px-6 py-3 text-sm font-semibold text-white">
                            <span>My trainings · {year}</span>
                            <span className="text-xs font-normal text-white/70">
                                {entries.length} {entries.length === 1 ? 'entry' : 'entries'}
                            </span>
                        </div>

                        {entries.length === 0 ? (
                            <div className="p-10 text-center">
                                <p className="text-sm text-slate-400">Nothing recorded for {year} yet.</p>
                                <button
                                    onClick={() => open(null)}
                                    className="mt-3 rounded-md bg-[#0b2a52] px-4 py-2 text-sm font-medium text-white hover:bg-[#071b35]"
                                >
                                    + Record training
                                </button>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th className="px-6 py-2 font-semibold">Training / seminar</th>
                                            <th className="px-4 py-2 font-semibold">Competency</th>
                                            <th className="px-4 py-2 font-semibold">Inclusive dates</th>
                                            <th className="px-4 py-2 text-right font-semibold">Hours</th>
                                            <th className="px-4 py-2 font-semibold">Proof</th>
                                            <th className="px-4 py-2 font-semibold">Status</th>
                                            <th className="px-6 py-2 text-right font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {entries.map((l) => (
                                            <tr key={l.id} className="align-top hover:bg-slate-50/60">
                                                <td className="px-6 py-3">
                                                    <span className="font-medium text-slate-800">{l.title}</span>
                                                    {l.remarks && (
                                                        <p className="mt-0.5 text-xs text-rose-600">
                                                            Returned: {l.remarks}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">{l.competency || '—'}</td>
                                                <td className="px-4 py-3 text-slate-600">{l.dates}</td>
                                                <td className="px-4 py-3 text-right tabular-nums text-slate-700">
                                                    {l.hours}h
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Proof entry={l} />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset ${STATUS[l.status] ?? ''}`}
                                                    >
                                                        {l.status}
                                                    </span>
                                                </td>
                                                <td className="space-x-3 whitespace-nowrap px-6 py-3 text-right text-xs font-medium">
                                                    <button
                                                        onClick={() => open(l)}
                                                        className="text-blue-600 hover:underline"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        onClick={() => remove(l)}
                                                        className="text-rose-600 hover:underline"
                                                    >
                                                        Remove
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            </div>

            {/* Record / correct a training */}
            <Modal show={editing !== null} onClose={close} maxWidth="2xl">
                <form onSubmit={submit} className="p-6">
                    <h3 className="text-lg font-semibold text-slate-800">
                        {editing?.id ? 'Edit training' : 'Record a training'}
                    </h3>
                    <p className="mt-1 text-xs leading-relaxed text-slate-500">
                        {editing?.id
                            ? 'Saving sends this entry back to the approver, so its hours stop counting until it is approved again.'
                            : 'Attach the certificate and a photo from the training if you have them — a training can be recorded without them and the proof added later.'}{' '}
                        Leave the hours blank and it counts as <strong>1 hour</strong>.
                    </p>

                    <div className="mt-5 space-y-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label className={label}>Competency</label>
                                <select
                                    className={input}
                                    value={form.data.competency}
                                    onChange={(e) => form.setData('competency', e.target.value)}
                                >
                                    {competencies.map((c) => (
                                        <option key={c.value} value={c.value}>
                                            {c.label}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.competency && (
                                    <p className="mt-1 text-xs text-rose-600">{form.errors.competency}</p>
                                )}
                            </div>
                            <div className="md:col-span-2">
                                <label className={label}>Training / seminar title *</label>
                                <input
                                    className={input}
                                    placeholder="e.g. Records Management Seminar"
                                    value={form.data.title}
                                    onChange={(e) => form.setData('title', e.target.value)}
                                />
                                {form.errors.title && (
                                    <p className="mt-1 text-xs text-rose-600">{form.errors.title}</p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label className={label}>From *</label>
                                <input
                                    type="date"
                                    className={input}
                                    value={form.data.date}
                                    onChange={(e) => form.setData('date', e.target.value)}
                                />
                                {form.errors.date && (
                                    <p className="mt-1 text-xs text-rose-600">{form.errors.date}</p>
                                )}
                            </div>
                            <div>
                                <label className={label}>To</label>
                                <input
                                    type="date"
                                    className={input}
                                    value={form.data.date_to}
                                    onChange={(e) => form.setData('date_to', e.target.value)}
                                />
                                <p className="mt-1 text-xs text-gray-500">Blank for a one-day training.</p>
                                {form.errors.date_to && (
                                    <p className="mt-1 text-xs text-rose-600">{form.errors.date_to}</p>
                                )}
                            </div>
                            <div>
                                <label className={label}>Number of hours</label>
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    className={input}
                                    placeholder="1"
                                    value={form.data.hours}
                                    onChange={(e) => form.setData('hours', e.target.value)}
                                />
                                {form.errors.hours && (
                                    <p className="mt-1 text-xs text-rose-600">{form.errors.hours}</p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label className={label}>Certificate (photo or scan)</label>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    className={fileInput}
                                    onChange={(e) => form.setData('certificate', e.target.files[0] ?? null)}
                                />
                                {editing?.certificate && (
                                    <p className="mt-1 text-xs text-gray-500">
                                        One is already attached — choosing a file replaces it.
                                    </p>
                                )}
                                {form.errors.certificate && (
                                    <p className="mt-1 text-xs text-rose-600">{form.errors.certificate}</p>
                                )}
                            </div>
                            <div>
                                <label className={label}>Photo during the training</label>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    className={fileInput}
                                    onChange={(e) => form.setData('photo', e.target.files[0] ?? null)}
                                />
                                {editing?.photo && (
                                    <p className="mt-1 text-xs text-gray-500">
                                        One is already attached — choosing a file replaces it.
                                    </p>
                                )}
                                {form.errors.photo && (
                                    <p className="mt-1 text-xs text-rose-600">{form.errors.photo}</p>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={close}
                            className="rounded-md border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-md bg-[#0b2a52] px-6 py-2 text-sm font-medium text-white hover:bg-[#071b35] disabled:opacity-50"
                        >
                            {editing?.id ? 'Save changes' : 'Submit training'}
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
