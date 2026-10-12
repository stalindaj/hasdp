import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';

const blankRecord = {
    kind: 'training',
    competency: 'foundational',
    title: '',
    date: '',
    date_to: '',
    hours: '',
    batch: '',
    conducted_by: '',
    outside_paf: false,
    employees: [],
};

/** Record one training or course for several people, approved on save. */
function RecordDialog({ show, onClose, rows, competencies }) {
    const form = useForm(blankRecord);
    const [who, setWho] = useState('');
    const course = form.data.kind === 'career_course';

    const close = () => {
        form.reset();
        form.clearErrors();
        setWho('');
        onClose();
    };
    const submit = (e) => {
        e.preventDefault();
        form.post(route('ld.record'), { preserveScroll: true, onSuccess: close });
    };
    const toggle = (id) =>
        form.setData('employees', form.data.employees.includes(id)
            ? form.data.employees.filter((x) => x !== id)
            : [...form.data.employees, id]);

    const list = rows.filter((r) => !who || r.name.toLowerCase().includes(who.toLowerCase()));
    const allShown = list.length > 0 && list.every((r) => form.data.employees.includes(r.id));

    const label = 'block text-xs font-semibold uppercase tracking-wide text-gray-500';
    const input = 'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#0b2a52] focus:ring-[#0b2a52]';
    const err = (k) => form.errors[k] && <p className="mt-1 text-xs text-rose-600">{form.errors[k]}</p>;

    return (
        <Modal show={show} onClose={close} maxWidth="4xl">
            <form onSubmit={submit} className="p-6">
                <h3 className="text-lg font-semibold text-slate-800">Record for employees</h3>
                <p className="mt-1 text-xs text-slate-500">
                    Fill it in once and tick everyone who attended. It is saved as <strong>approved</strong> for each
                    of them and shows on their L&amp;D and in the reports.
                </p>

                <div className="mt-4 inline-flex rounded-lg bg-slate-100 p-1 text-sm">
                    {[['training', 'Training / seminar'], ['career_course', 'Career course']].map(([k, l]) => (
                        <button
                            key={k}
                            type="button"
                            onClick={() => form.setData('kind', k)}
                            className={`rounded-md px-3 py-1.5 font-medium ${form.data.kind === k ? 'bg-white text-slate-900 shadow' : 'text-slate-500'}`}
                        >
                            {l}
                        </button>
                    ))}
                </div>

                <div className="mt-4 grid gap-6 md:grid-cols-[1fr_18rem]">
                    <div className="space-y-4">
                        <div>
                            <label className={label}>{course ? 'Course title' : 'Training / seminar title'} *</label>
                            <input className={input} value={form.data.title}
                                   onChange={(e) => form.setData('title', e.target.value)} />
                            {err('title')}
                        </div>
                        {course ? (
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className={label}>Batch</label>
                                    <input className={input} placeholder="e.g. 2026-01" value={form.data.batch}
                                           onChange={(e) => form.setData('batch', e.target.value)} />
                                </div>
                                <div>
                                    <label className={label}>Conducted by</label>
                                    <input className={input} placeholder="e.g. PAFCHRS" value={form.data.conducted_by}
                                           onChange={(e) => form.setData('conducted_by', e.target.value)} />
                                </div>
                                <label className="col-span-2 flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" checked={form.data.outside_paf}
                                           onChange={(e) => form.setData('outside_paf', e.target.checked)}
                                           className="rounded border-gray-300 text-[#0b2a52] focus:ring-[#0b2a52]" />
                                    Taken outside the PAF
                                </label>
                            </div>
                        ) : (
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className={label}>Competency</label>
                                    <select className={input} value={form.data.competency}
                                            onChange={(e) => form.setData('competency', e.target.value)}>
                                        {competencies.map((c) => (
                                            <option key={c.value} value={c.value}>{c.label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className={label}>Hours each</label>
                                    <input type="number" step="0.5" min="0.5" placeholder="1" className={input}
                                           value={form.data.hours} onChange={(e) => form.setData('hours', e.target.value)} />
                                    {err('hours')}
                                </div>
                            </div>
                        )}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={label}>From *</label>
                                <input type="date" className={input} value={form.data.date}
                                       onChange={(e) => form.setData('date', e.target.value)} />
                                {err('date')}
                            </div>
                            <div>
                                <label className={label}>To</label>
                                <input type="date" className={input} value={form.data.date_to}
                                       onChange={(e) => form.setData('date_to', e.target.value)} />
                                {err('date_to')}
                            </div>
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center justify-between">
                            <label className={label}>Attended ({form.data.employees.length}) *</label>
                            <button type="button" className="text-xs font-medium text-blue-600 hover:underline"
                                    onClick={() => form.setData('employees', allShown
                                        ? form.data.employees.filter((id) => !list.some((r) => r.id === id))
                                        : [...new Set([...form.data.employees, ...list.map((r) => r.id)])])}>
                                {allShown ? 'Clear' : 'Tick all'}
                            </button>
                        </div>
                        <input type="search" placeholder="Filter names" value={who} onChange={(e) => setWho(e.target.value)}
                               className="mt-1 block w-full rounded-md border-gray-300 py-1 text-sm shadow-sm" />
                        <ul className="mt-2 h-64 overflow-y-auto rounded-md border border-slate-200">
                            {list.map((r) => (
                                <li key={r.id}>
                                    <label className="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50">
                                        <input type="checkbox" checked={form.data.employees.includes(r.id)}
                                               onChange={() => toggle(r.id)}
                                               className="rounded border-gray-300 text-[#0b2a52] focus:ring-[#0b2a52]" />
                                        {r.name}
                                    </label>
                                </li>
                            ))}
                        </ul>
                        {err('employees')}
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <button type="button" onClick={close}
                            className="rounded-md border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" disabled={form.processing}
                            className="rounded-md bg-[#0b2a52] px-6 py-2 text-sm font-medium text-white hover:bg-[#071b35] disabled:opacity-50">
                        Save for {form.data.employees.length || '…'} {form.data.employees.length === 1 ? 'person' : 'people'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

/**
 * Admin → L&D: the whole roster's Learning & Development standing for a year.
 *
 * Each employee's target is set by salary grade (8h for SG 1–14, 40h for
 * SG 15 and up). Approved hours count; the queue up top clears pending
 * submissions. A row opens to show that person's trainings and career
 * courses; the report tables live under Reports.
 */

const STATUS = {
    pending: 'bg-amber-50 text-amber-700 ring-amber-200',
    approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    rejected: 'bg-red-50 text-red-700 ring-red-200',
};

/** "Certificate · Photo" proof links for a pending submission. */
function ProofLinks({ entry }) {
    if (!entry.certificate && !entry.photo) return null;
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

function Stat({ value, label, tone = 'default', children }) {
    const tones = {
        default: 'text-slate-900',
        good: 'text-emerald-600',
        warn: 'text-amber-600',
        info: 'text-blue-600',
    };
    return (
        <div className="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            <p className={`text-3xl font-bold tabular-nums ${tones[tone]}`}>{value}</p>
            <p className="mt-0.5 text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
            {children}
        </div>
    );
}

function Progress({ hours, target }) {
    const pct = target > 0 ? Math.min(100, (hours / target) * 100) : 0;
    const done = hours >= target;
    return (
        <div className="flex items-center gap-3">
            <div className="h-2 w-28 overflow-hidden rounded-full bg-slate-100">
                <div
                    className={`h-full rounded-full ${done ? 'bg-emerald-500' : 'bg-amber-400'}`}
                    style={{ width: `${pct}%` }}
                />
            </div>
            <span className="w-20 text-sm tabular-nums text-slate-700">
                <strong className="text-slate-900">{hours}</strong>
                <span className="text-slate-400"> / {target}h</span>
            </span>
        </div>
    );
}

function Filings({ title, items, empty }) {
    return (
        <div>
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</p>
            {items.length === 0 ? (
                <p className="text-sm italic text-slate-400">{empty}</p>
            ) : (
                <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                    {items.map((t) => (
                        <li key={t.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                            <div className="min-w-0">
                                <p className="text-sm font-medium text-slate-800">{t.title}</p>
                                <p className="text-xs text-slate-500">
                                    {[t.detail, t.dates].filter(Boolean).join(' · ')}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-3">
                                {t.hours !== null && (
                                    <span className="text-sm tabular-nums text-slate-600">{t.hours}h</span>
                                )}
                                <span
                                    className={`rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset ${STATUS[t.status] ?? ''}`}
                                >
                                    {t.status}
                                </span>
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (confirm(`Remove "${t.title}" from this person's record?`)) {
                                            router.delete(route('ld.destroy', t.id), { preserveScroll: true, preserveState: true });
                                        }
                                    }}
                                    className="text-xs font-medium text-rose-600 hover:underline"
                                >
                                    Remove
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function Index({ year, years, rows, pending, summary, competencies }) {
    const flash = usePage().props.flash;
    const [recording, setRecording] = useState(false);
    const [filter, setFilter] = useState('all');
    const [search, setSearch] = useState('');
    const [open, setOpen] = useState(null);

    const changeYear = (y) =>
        router.get(route('ld.index', { year: y }), {}, { preserveScroll: true, preserveState: true });

    const decide = (entry, decision) => {
        let remarks = null;
        if (decision === 'rejected') {
            remarks = prompt(`Reason for rejecting "${entry.title}"?`);
            if (!remarks) return; // cancelled
        }
        router.patch(route('ld.decide', entry.id), { decision, remarks }, { preserveScroll: true });
    };

    const shown = useMemo(() => {
        const q = search.trim().toLowerCase();
        return rows.filter((r) => {
            if (filter === 'behind' && r.met) return false;
            if (filter === 'met' && !r.met) return false;
            if (filter === 'pending' && r.pending === 0) return false;
            return !q || r.name.toLowerCase().includes(q) || String(r.emp_no).includes(q);
        });
    }, [rows, filter, search]);

    const compliance = summary.total ? Math.round((summary.met / summary.total) * 100) : 0;

    const tabs = [
        ['all', 'All', summary.total],
        ['behind', 'Behind', summary.behind],
        ['met', 'Met', summary.met],
        ['pending', 'Has pending', rows.filter((r) => r.pending > 0).length],
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Learning &amp; Development
                        </h2>
                        <p className="text-xs text-slate-500">
                            CY {year} · {summary.total} employees on the roster
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <select
                            value={year}
                            onChange={(e) => changeYear(e.target.value)}
                            className="rounded-md border-slate-300 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            {years.map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                        <button
                            type="button"
                            onClick={() => setRecording(true)}
                            className="whitespace-nowrap rounded-md border border-[#0b2a52] px-4 py-2 text-sm font-medium text-[#0b2a52] hover:bg-slate-50"
                        >
                            + Record for employees
                        </button>
                        <Link
                            href={route('ld.reports', { year })}
                            className="whitespace-nowrap rounded-md bg-[#0b2a52] px-4 py-2 text-sm font-medium text-white hover:bg-[#071b35]"
                        >
                            Reports &amp; Excel
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Learning & Development" />

            <div className="py-6">
                <div className="mx-auto max-w-[110rem] space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">
                            {flash.success}
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                        <Stat value={`${compliance}%`} label="Compliance">
                            <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div className="h-full rounded-full bg-emerald-500" style={{ width: `${compliance}%` }} />
                            </div>
                        </Stat>
                        <Stat value={summary.met} label={`Met target · ${year}`} tone="good" />
                        <Stat value={summary.behind} label="Below target" tone="warn" />
                        <Stat value={`${summary.hours}h`} label="Approved hours" />
                        <Stat value={summary.pending} label="Awaiting approval" tone="info" />
                    </div>

                    {/* Approval queue */}
                    {pending.length > 0 && (
                        <div className="rounded-xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
                            <h3 className="text-sm font-semibold text-amber-900">
                                Pending approval ({pending.length})
                            </h3>
                            <ul className="mt-3 divide-y divide-amber-100">
                                {pending.map((l) => (
                                    <li key={l.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-slate-800">{l.employee}</p>
                                            {l.kind === 'career_course' ? (
                                                <p className="text-xs text-slate-500">
                                                    <span className="mr-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-indigo-700">
                                                        Career course
                                                    </span>
                                                    {l.title}
                                                    {l.batch ? ` · Batch ${l.batch}` : ''} · {l.date}
                                                    {l.conducted_by ? ` · ${l.conducted_by}` : ''}
                                                </p>
                                            ) : (
                                                <p className="text-xs text-slate-500">
                                                    {l.title} · {l.hours}h · {l.date}
                                                    {l.competency ? ` · ${l.competency}` : ''}
                                                </p>
                                            )}
                                            <ProofLinks entry={l} />
                                        </div>
                                        <div className="flex shrink-0 gap-3 text-sm font-medium">
                                            <button onClick={() => decide(l, 'approved')}
                                                    className="text-emerald-700 hover:underline">
                                                Approve
                                            </button>
                                            <button onClick={() => decide(l, 'rejected')}
                                                    className="text-red-600 hover:underline">
                                                Reject
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Roster */}
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                            <div className="flex flex-wrap gap-1.5">
                                {tabs.map(([key, label, count]) => (
                                    <button
                                        key={key}
                                        onClick={() => setFilter(key)}
                                        className={`rounded-full px-3 py-1 text-sm font-medium transition ${
                                            filter === key
                                                ? 'bg-[#0b2a52] text-white'
                                                : 'text-slate-600 hover:bg-slate-100'
                                        }`}
                                    >
                                        {label}
                                        <span className={`ml-1.5 tabular-nums ${filter === key ? 'text-white/70' : 'text-slate-400'}`}>
                                            {count}
                                        </span>
                                    </button>
                                ))}
                            </div>
                            <input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search name or employee no."
                                className="w-64 rounded-md border-slate-300 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3 text-left">Employee</th>
                                        <th className="px-4 py-3 text-center">SG</th>
                                        <th className="px-4 py-3 text-left">Approved hours</th>
                                        <th className="px-4 py-3 text-center">Trainings</th>
                                        <th className="px-4 py-3 text-center">Career courses</th>
                                        <th className="px-4 py-3 text-center">Remaining</th>
                                        <th className="px-4 py-3 text-center">Status</th>
                                        <th className="w-8 px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {shown.map((r) => {
                                        const isOpen = open === r.id;
                                        return (
                                            <Fragment key={r.id}>
                                                <tr
                                                    onClick={() => setOpen(isOpen ? null : r.id)}
                                                    className={`cursor-pointer ${isOpen ? 'bg-slate-50' : 'hover:bg-slate-50'}`}
                                                >
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium text-slate-800">{r.name}</span>
                                                            <span className="text-xs text-slate-400">#{r.emp_no}</span>
                                                            <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500">
                                                                {r.level} level
                                                            </span>
                                                            {r.pending > 0 && (
                                                                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800">
                                                                    {r.pending} pending
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-center tabular-nums text-slate-500">{r.sg ?? '—'}</td>
                                                    <td className="px-4 py-3">
                                                        <Progress hours={r.hours} target={r.target} />
                                                    </td>
                                                    <td className="px-4 py-3 text-center tabular-nums text-slate-700">
                                                        {r.trainings.length || <span className="text-slate-300">0</span>}
                                                    </td>
                                                    <td className="px-4 py-3 text-center tabular-nums text-slate-700">
                                                        {r.courses.length || <span className="text-slate-300">0</span>}
                                                    </td>
                                                    <td className="px-4 py-3 text-center tabular-nums text-slate-600">
                                                        {r.remaining > 0 ? `${r.remaining}h` : '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        {r.met ? (
                                                            <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">
                                                                met
                                                            </span>
                                                        ) : (
                                                            <span className="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-200">
                                                                behind
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-400">{isOpen ? '▴' : '▾'}</td>
                                                </tr>
                                                {isOpen && (
                                                    <tr className="bg-slate-50">
                                                        <td colSpan={8} className="px-4 pb-4 pt-1">
                                                            <div className="grid gap-4 lg:grid-cols-2">
                                                                <Filings
                                                                    title={`Trainings · ${year}`}
                                                                    items={r.trainings}
                                                                    empty="No trainings filed this year."
                                                                />
                                                                <Filings
                                                                    title={`Career courses & seminars · ${year}`}
                                                                    items={r.courses}
                                                                    empty="No career courses filed this year."
                                                                />
                                                            </div>
                                                            <Link
                                                                href={route('dashboard.employee', r.id)}
                                                                className="mt-3 inline-block text-xs font-medium text-blue-600 hover:underline"
                                                            >
                                                                Open employee card →
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                )}
                                            </Fragment>
                                        );
                                    })}
                                    {shown.length === 0 && (
                                        <tr>
                                            <td colSpan={8} className="px-4 py-8 text-center text-sm text-slate-400">
                                                {rows.length === 0 ? 'No employees on the roster.' : 'Nobody matches.'}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <p className="text-xs text-slate-400">
                        Target follows salary grade — 8 hours a year for SG 1–14, 40 for SG 15 and up. Only approved
                        training hours count; career courses are listed but add no hours. Click a person to see what
                        they filed.
                    </p>
                </div>
            </div>

            <RecordDialog
                show={recording}
                onClose={() => setRecording(false)}
                rows={rows}
                competencies={competencies}
            />
        </AuthenticatedLayout>
    );
}
