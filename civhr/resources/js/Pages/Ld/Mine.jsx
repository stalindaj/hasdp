import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';

/**
 * The employee's own Learning & Development page: file a training — the
 * competency it builds, its title, the dates it ran, the hours — attach the
 * certificate and/or a photo if there is one, and watch it against the
 * salary-grade target for the year. An admin approves before the hours count.
 */

const label = 'block text-xs font-semibold uppercase tracking-wide text-gray-500';
const input =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#0b2a52] focus:ring-[#0b2a52]';

const STATUS = {
    pending: 'bg-amber-50 text-amber-700 ring-amber-200',
    approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    rejected: 'bg-red-50 text-red-700 ring-red-200',
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
        return <span className="text-xs italic text-slate-400">No proof attached yet</span>;
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

    const form = useForm({
        competency: 'technical',
        title: '',
        date: '',
        date_to: '',
        hours: '',
        certificate: null,
        photo: null,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('ld.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
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
                    <select className={`${input} mt-0 w-auto`} value={year} onChange={(e) => changeYear(e.target.value)}>
                        {years.map((y) => (
                            <option key={y} value={y}>
                                {y}
                            </option>
                        ))}
                    </select>
                </div>
            }
        >
            <Head title="My L&D" />

            <div className="py-8">
                <div className="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Stat value={`${summary.hours}h`} caption={`Approved in ${year}`} tone={summary.met ? 'good' : 'default'} />
                        <Stat value={`${summary.target}h`} caption="Target for your salary grade" />
                        <Stat
                            value={summary.met ? 'Met' : `${summary.remaining}h`}
                            caption={summary.met ? 'Target met' : 'Still to go'}
                            tone={summary.met ? 'good' : 'warn'}
                        />
                        <Stat value={`${summary.pending}h`} caption="Waiting for approval" tone={summary.pending > 0 ? 'warn' : 'default'} />
                    </div>

                    {/* Hours per track — the spread, not just the total. */}
                    <section className="overflow-hidden rounded-2xl bg-white shadow-sm">
                        <div className="bg-[#0b2a52] px-6 py-3 text-sm font-semibold text-white">
                            Hours by competency
                        </div>
                        <div className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-5">
                            {summary.by_competency.map((c) => (
                                <div key={c.key} className="rounded-lg bg-slate-50 px-3 py-2">
                                    <p className="text-lg font-bold tabular-nums text-slate-800">{c.hours}h</p>
                                    <p className="text-[0.7rem] leading-tight text-slate-500">{c.label}</p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl bg-white shadow-sm">
                        <div className="bg-[#0b2a52] px-6 py-3 text-sm font-semibold text-white">
                            Record a training
                        </div>
                        <p className="m-4 rounded-md border-l-4 border-[#0b2a52] bg-slate-50 p-3 text-xs leading-relaxed text-gray-600">
                            Attach the certificate and a photo taken during the training if you have them — they
                            help the approver, but a training can be recorded without them and the proof added
                            later. Leave the hours blank and it counts as <strong>1 hour</strong>. The hours count
                            toward your target once an admin approves the entry.
                        </p>

                        <form onSubmit={submit} className="space-y-4 p-4 pt-0">
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
                                    <p className="mt-1 text-xs text-gray-500">
                                        Leave blank for a one-day training.
                                    </p>
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
                                        className="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-[#0b2a52] file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white"
                                        onChange={(e) => form.setData('certificate', e.target.files[0] ?? null)}
                                    />
                                    {form.errors.certificate && (
                                        <p className="mt-1 text-xs text-rose-600">{form.errors.certificate}</p>
                                    )}
                                </div>
                                <div>
                                    <label className={label}>Photo during the training</label>
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        className="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-[#0b2a52] file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white"
                                        onChange={(e) => form.setData('photo', e.target.files[0] ?? null)}
                                    />
                                    {form.errors.photo && (
                                        <p className="mt-1 text-xs text-rose-600">{form.errors.photo}</p>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-md bg-[#0b2a52] px-6 py-2 text-sm font-medium text-white hover:bg-[#071b35] disabled:opacity-50"
                                >
                                    Submit training
                                </button>
                            </div>
                        </form>
                    </section>

                    <section className="overflow-hidden rounded-2xl bg-white shadow-sm">
                        <div className="bg-[#0b2a52] px-6 py-3 text-sm font-semibold text-white">
                            My trainings · {year}
                        </div>
                        {entries.length === 0 ? (
                            <p className="p-6 text-sm text-slate-400">Nothing recorded for {year} yet.</p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {entries.map((l) => (
                                    <li key={l.id} className="px-6 py-3">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="font-medium text-slate-800">{l.title}</span>
                                            <span className="flex items-center gap-3 text-sm text-slate-500">
                                                {l.hours}h
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset ${STATUS[l.status] ?? ''}`}
                                                >
                                                    {l.status}
                                                </span>
                                            </span>
                                        </div>
                                        <div className="mt-0.5 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                                            <span>
                                                {l.competency && <span className="font-medium">{l.competency} · </span>}
                                                {l.dates}
                                            </span>
                                            <Proof entry={l} />
                                        </div>
                                        {l.remarks && (
                                            <p className="mt-1 text-xs text-rose-600">Returned: {l.remarks}</p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
