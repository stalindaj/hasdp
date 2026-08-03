import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

const MEASURES = ['Quality', 'Timeliness', 'Quantity'];
const LEVELS = [
    ['outstanding', 'Outstanding (5)'],
    ['very_satisfactory', 'Very Satisfactory (4)'],
    ['satisfactory', 'Satisfactory (3)'],
    ['unsatisfactory', 'Unsatisfactory (2)'],
    ['poor', 'Poor (1)'],
];

function adjectival(score) {
    if (score == null || Number.isNaN(score)) return '—';
    if (score >= 4.5) return 'Outstanding';
    if (score >= 3.5) return 'Very Satisfactory';
    if (score >= 2.5) return 'Satisfactory';
    if (score >= 1.5) return 'Unsatisfactory';
    return 'Poor';
}

function groupAverage(g) {
    const vals = ['quality_rating', 'timeliness_rating', 'quantity_rating']
        .map((k) => parseFloat(g[k]))
        .filter((v) => !Number.isNaN(v));
    if (vals.length === 0) return null;
    return Math.round((vals.reduce((a, b) => a + b, 0) / vals.length) * 100) / 100;
}

function blankGroup() {
    return {
        major_final_output: '',
        success_indicator: '',
        timeliness: '',
        actual_accomplishment: '',
        quality_pct: '',
        timeliness_pct: '',
        quantity_pct: '',
        quality_rating: '',
        timeliness_rating: '',
        quantity_rating: '',
        remarks: '',
        rows: MEASURES.map((m) => ({
            performance_measure: m,
            performance_targets: '',
            outstanding: '',
            very_satisfactory: '',
            satisfactory: '',
            unsatisfactory: '',
            poor: '',
        })),
    };
}

const navy = '#0b2a52';

const label = 'block text-xs font-semibold uppercase tracking-wide text-gray-500';
const input =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#0b2a52] focus:ring-[#0b2a52]';

export default function Form({ form, personnel, signatories, isManager, currentUserId }) {
    const editing = Boolean(form?.id);

    const { data, setData, post, patch, processing, errors } = useForm({
        user_id: form?.user_id ?? (isManager ? '' : currentUserId),
        reviewer_name: form?.reviewer_sig?.name ?? '',
        reviewer_designation: form?.reviewer_sig?.designation ?? '',
        approver_name: form?.approver_sig?.name ?? '',
        approver_designation: form?.approver_sig?.designation ?? '',
        rating_period: form?.rating_period ?? '',
        position_title: form?.position_title ?? '',
        office_unit: form?.office_unit ?? '',
        strategic_priority: form?.strategic_priority ?? '',
        core_function: form?.core_function ?? '',
        status: form?.status ?? 'draft',
        prepared_by: form?.prepared_by ?? '',
        approved_by: form?.approved_by ?? '',
        discussed_with: form?.discussed_with ?? '',
        discussed_date: form?.discussed_date ?? '',
        fe_reviewed_by: form?.fe_reviewed_by ?? '',
        fe_reviewed_date: form?.fe_reviewed_date ?? '',
        fe_approved_by: form?.fe_approved_by ?? '',
        fe_approved_date: form?.fe_approved_date ?? '',
        fe_review_remarks: form?.fe_review_remarks ?? '',
        fe_assessed_by: form?.fe_assessed_by ?? '',
        fe_assessed_date: form?.fe_assessed_date ?? '',
        fe_final_rating_by: form?.fe_final_rating_by ?? '',
        fe_final_rating_date: form?.fe_final_rating_date ?? '',
        fe_comments: form?.fe_comments ?? '',
        fe_intervening_activity: form?.fe_intervening_activity ?? '',
        groups: form?.groups?.length ? structuredClone(form.groups) : [blankGroup()],
    });

    const overall = useMemo(() => {
        const avgs = data.groups.map(groupAverage).filter((v) => v != null);
        if (avgs.length === 0) return null;
        return Math.round((avgs.reduce((a, b) => a + b, 0) / avgs.length) * 100) / 100;
    }, [data.groups]);

    const setGroup = (gi, patchObj) =>
        setData(
            'groups',
            data.groups.map((g, i) => (i === gi ? { ...g, ...patchObj } : g)),
        );

    const setRow = (gi, ri, patchObj) =>
        setData(
            'groups',
            data.groups.map((g, i) =>
                i === gi
                    ? { ...g, rows: g.rows.map((r, j) => (j === ri ? { ...r, ...patchObj } : r)) }
                    : g,
            ),
        );

    const addGroup = () => setData('groups', [...data.groups, blankGroup()]);
    const removeGroup = (gi) =>
        setData('groups', data.groups.filter((_, i) => i !== gi));

    const submit = (e) => {
        e.preventDefault();
        if (editing) patch(route('ipcr.update', form.id));
        else post(route('ipcr.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {editing ? 'Edit IPCR' : 'New IPCR'}
                </h2>
            }
        >
            <Head title={editing ? 'Edit IPCR' : 'New IPCR'} />

            <form onSubmit={submit} className="py-8">
                <div className="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                    {/* Header */}
                    <section className="rounded-lg bg-white p-6 shadow-sm">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label className={label}>Ratee</label>
                                {isManager ? (
                                    <select
                                        className={input}
                                        value={data.user_id}
                                        onChange={(e) => setData('user_id', e.target.value)}
                                    >
                                        <option value="">— select personnel —</option>
                                        {personnel.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name} {p.username ? `(${p.username})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <input className={input} value={form?.ratee ?? 'Myself'} disabled />
                                )}
                                {errors.user_id && (
                                    <p className="mt-1 text-xs text-rose-600">{errors.user_id}</p>
                                )}
                            </div>
                            <div>
                                <label className={label}>Rating period</label>
                                <input
                                    className={input}
                                    placeholder="e.g. January – June 2026"
                                    value={data.rating_period}
                                    onChange={(e) => setData('rating_period', e.target.value)}
                                />
                                {errors.rating_period && (
                                    <p className="mt-1 text-xs text-rose-600">{errors.rating_period}</p>
                                )}
                            </div>
                            <div>
                                <label className={label}>Position title</label>
                                <input
                                    className={input}
                                    value={data.position_title}
                                    onChange={(e) => setData('position_title', e.target.value)}
                                />
                            </div>
                            <div>
                                <label className={label}>Office / Unit</label>
                                <input
                                    className={input}
                                    value={data.office_unit}
                                    onChange={(e) => setData('office_unit', e.target.value)}
                                />
                            </div>
                            <div>
                                <label className={label}>Strategic Priority</label>
                                <input
                                    className={input}
                                    placeholder="e.g. Territorial defense, security and stability services"
                                    value={data.strategic_priority}
                                    onChange={(e) => setData('strategic_priority', e.target.value)}
                                />
                            </div>
                            <div>
                                <label className={label}>Core Function</label>
                                <input
                                    className={input}
                                    placeholder="e.g. Maintain, repair, restore and recovery of aircraft"
                                    value={data.core_function}
                                    onChange={(e) => setData('core_function', e.target.value)}
                                />
                            </div>
                            <div>
                                <label className={label}>Status</label>
                                <select
                                    className={input}
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                >
                                    {['draft', 'submitted', 'reviewed', 'approved', 'rejected'].map((s) => (
                                        <option key={s} value={s}>
                                            {s[0].toUpperCase() + s.slice(1)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </section>

                    {/* Groups (MFOs) */}
                    {data.groups.map((g, gi) => {
                        const avg = groupAverage(g);
                        return (
                            <section key={gi} className="rounded-lg bg-white shadow-sm">
                                <div
                                    className="flex items-center justify-between rounded-t-lg px-6 py-3 text-white"
                                    style={{ background: navy }}
                                >
                                    <h3 className="text-sm font-semibold">
                                        Major Final Output #{gi + 1}
                                    </h3>
                                    {data.groups.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() => removeGroup(gi)}
                                            className="text-xs text-amber-300 hover:text-amber-200"
                                        >
                                            Remove
                                        </button>
                                    )}
                                </div>
                                <div className="space-y-4 p-6">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label className={label}>Major Final Output / KRA</label>
                                            <textarea
                                                rows={2}
                                                className={input}
                                                value={g.major_final_output}
                                                onChange={(e) =>
                                                    setGroup(gi, { major_final_output: e.target.value })
                                                }
                                            />
                                        </div>
                                        <div>
                                            <label className={label}>Success indicator</label>
                                            <textarea
                                                rows={2}
                                                className={input}
                                                value={g.success_indicator}
                                                onChange={(e) =>
                                                    setGroup(gi, { success_indicator: e.target.value })
                                                }
                                            />
                                        </div>
                                    </div>

                                    {/* Measures × rating-level descriptors */}
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full border text-xs">
                                            <thead style={{ background: '#f0f2f5' }}>
                                                <tr>
                                                    <th className="border px-2 py-1 text-left">Measure</th>
                                                    <th className="border px-2 py-1 text-left">Target</th>
                                                    {LEVELS.map(([k, lbl]) => (
                                                        <th key={k} className="border px-2 py-1 text-left">
                                                            {lbl}
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {g.rows.map((r, ri) => (
                                                    <tr key={ri}>
                                                        <td className="border px-2 py-1 font-medium">
                                                            {r.performance_measure}
                                                        </td>
                                                        <td className="border px-1 py-1">
                                                            <textarea
                                                                rows={2}
                                                                className="w-40 rounded border-gray-200 text-xs"
                                                                value={r.performance_targets}
                                                                onChange={(e) =>
                                                                    setRow(gi, ri, {
                                                                        performance_targets: e.target.value,
                                                                    })
                                                                }
                                                            />
                                                        </td>
                                                        {LEVELS.map(([k]) => (
                                                            <td key={k} className="border px-1 py-1">
                                                                <textarea
                                                                    rows={2}
                                                                    className="w-32 rounded border-gray-200 text-xs"
                                                                    value={r[k]}
                                                                    onChange={(e) =>
                                                                        setRow(gi, ri, { [k]: e.target.value })
                                                                    }
                                                                />
                                                            </td>
                                                        ))}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

                                    <div>
                                        <label className={label}>Actual accomplishment</label>
                                        <textarea
                                            rows={2}
                                            className={input}
                                            value={g.actual_accomplishment}
                                            onChange={(e) =>
                                                setGroup(gi, { actual_accomplishment: e.target.value })
                                            }
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                        {[
                                            ['quality_rating', 'Quality (Q)'],
                                            ['timeliness_rating', 'Timeliness (T)'],
                                            ['quantity_rating', 'Quantity (Q)'],
                                        ].map(([k, lbl]) => (
                                            <div key={k}>
                                                <label className={label}>{lbl}</label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="5"
                                                    className={input}
                                                    value={g[k]}
                                                    onChange={(e) => setGroup(gi, { [k]: e.target.value })}
                                                />
                                            </div>
                                        ))}
                                        <div>
                                            <label className={label}>Average</label>
                                            <input
                                                readOnly
                                                className={`${input} bg-gray-50 font-semibold`}
                                                value={avg ?? ''}
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className={label}>Remarks</label>
                                        <input
                                            className={input}
                                            value={g.remarks}
                                            onChange={(e) => setGroup(gi, { remarks: e.target.value })}
                                        />
                                    </div>
                                </div>
                            </section>
                        );
                    })}

                    <button
                        type="button"
                        onClick={addGroup}
                        className="rounded-md border border-dashed border-gray-400 px-4 py-2 text-sm font-medium text-gray-600 hover:border-[#0b2a52] hover:text-[#0b2a52]"
                    >
                        + Add Major Final Output
                    </button>

                    {/* Overall */}
                    <section
                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg px-6 py-4 text-white"
                        style={{ background: navy }}
                    >
                        <span className="text-sm font-semibold uppercase tracking-wide">
                            Overall rating
                        </span>
                        <span className="text-lg font-bold">
                            {overall ?? '—'}{' '}
                            <span className="text-sm font-medium text-amber-300">
                                {adjectival(overall)}
                            </span>
                        </span>
                    </section>

                    {/* Final evaluation */}
                    <section className="rounded-lg bg-white p-6 shadow-sm">
                        <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
                            Final evaluation &amp; signatories
                        </h3>

                        {/* The two named signatories from the CSC IPCR form —
                            typed by hand (they are usually military supervisors,
                            not civilian accounts). The ratee signs their own
                            blocks (Commitment / Discussed with) automatically. */}
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div className="space-y-3 rounded-md border border-gray-200 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-[#0b2a52]">
                                    Reviewed / Assessed by — NCOIC · Immediate Supervisor
                                </p>
                                <div>
                                    <label className={label}>Name</label>
                                    <input
                                        className={input}
                                        placeholder="e.g. TSg Ronnie R Doble PAF"
                                        value={data.reviewer_name}
                                        onChange={(e) => setData('reviewer_name', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <label className={label}>Designation</label>
                                    <input
                                        className={input}
                                        placeholder="e.g. Pneudraulics Shop Supervisor · NCOIC"
                                        value={data.reviewer_designation}
                                        onChange={(e) => setData('reviewer_designation', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="space-y-3 rounded-md border border-gray-200 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-[#0b2a52]">
                                    Approved / Final Rating by — Supervisor&apos;s Rater · CO
                                </p>
                                <div>
                                    <label className={label}>Name</label>
                                    <input
                                        className={input}
                                        placeholder="e.g. MAJ ARIEL DICKSON C ALMEDA PAF"
                                        value={data.approver_name}
                                        onChange={(e) => setData('approver_name', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <label className={label}>Designation</label>
                                    <input
                                        className={input}
                                        placeholder="e.g. 461st FWFMS Commanding Officer"
                                        value={data.approver_designation}
                                        onChange={(e) => setData('approver_designation', e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label className={label}>Comments</label>
                                <textarea
                                    rows={3}
                                    className={input}
                                    value={data.fe_comments}
                                    onChange={(e) => setData('fe_comments', e.target.value)}
                                />
                            </div>
                            <div>
                                <label className={label}>Intervening activity</label>
                                <textarea
                                    rows={3}
                                    className={input}
                                    value={data.fe_intervening_activity}
                                    onChange={(e) => setData('fe_intervening_activity', e.target.value)}
                                />
                            </div>
                        </div>
                    </section>

                    <div className="flex justify-end gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-[#0b2a52] px-6 py-2 text-sm font-medium text-white hover:bg-[#071b35] disabled:opacity-50"
                        >
                            {editing ? 'Save changes' : 'Save IPCR'}
                        </button>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
