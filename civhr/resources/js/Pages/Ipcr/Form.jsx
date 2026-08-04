import FormE from '@/Components/Ipcr/FormE';
import Matrix from '@/Components/Ipcr/Matrix';
import { MEASURES, autoRating, parsePercent, splitRatingPeriod } from '@/Components/Ipcr/rating';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const navy = '#0b2a52';

const label = 'block text-xs font-semibold uppercase tracking-wide text-gray-500';
const input =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#0b2a52] focus:ring-[#0b2a52]';

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
            performance_measure: m.measure,
            performance_targets: '',
            outstanding: '',
            very_satisfactory: '',
            satisfactory: '',
            unsatisfactory: '',
            poor: '',
            selected_band: null,
        })),
    };
}

function Card({ title, action, hint, scrolls = false, children }) {
    return (
        <section className="overflow-hidden rounded-2xl bg-white shadow-sm">
            <div
                className="flex items-center justify-between gap-3 px-6 py-3 text-sm font-semibold text-white"
                style={{ background: navy }}
            >
                <span>{title}</span>
                {action}
            </div>
            {hint && (
                <p className="m-4 rounded-md border-l-4 border-[#0b2a52] bg-slate-50 p-3 text-xs leading-relaxed text-gray-600">
                    {hint}
                </p>
            )}
            {scrolls && (
                <p className="sheet-hint px-4 pb-2 text-xs text-gray-500">
                    ↔ Swipe the sheet sideways to reach the rest of the columns.
                </p>
            )}
            <div className="sheet-scroll overflow-x-auto p-4 pt-0">{children}</div>
        </section>
    );
}

export default function Form({ form, personnel, isManager, currentUserId, defaults, periods }) {
    const editing = Boolean(form?.id);

    const period =
        form?.rating_period ??
        `${periods.semesters[periods.currentSemester]} ${periods.currentYear}`;
    // The signature cells are dated from the rating period from the start, not
    // only once it is edited.
    const [periodStart, periodEnd] = splitRatingPeriod(period);

    const { data, setData, post, patch, processing, errors } = useForm({
        user_id: form?.user_id ?? (isManager ? '' : currentUserId),
        year: form?.year ?? periods.currentYear,
        semester: form?.semester ?? periods.currentSemester,
        rating_period: period,
        // A manager files for someone else, so their own position is not a
        // sensible default — it arrives when they pick the ratee.
        position_title: form?.position_title ?? (isManager ? '' : (defaults?.position ?? '')),
        office_unit: form?.office_unit ?? '',
        strategic_priority: form?.strategic_priority ?? '',
        core_function: form?.core_function ?? '',
        status: form?.status ?? 'draft',
        discussed_with: form?.discussed_with ?? '',
        discussed_date: form?.discussed_date ?? periodEnd,
        fe_reviewed_by: form?.fe_reviewed_by ?? '',
        fe_reviewed_date: form?.fe_reviewed_date ?? periodStart,
        fe_approved_by: form?.fe_approved_by ?? '',
        fe_approved_date: form?.fe_approved_date ?? periodStart,
        fe_assessed_by: form?.fe_assessed_by ?? '',
        fe_assessed_date: form?.fe_assessed_date ?? periodEnd,
        fe_final_rating_by: form?.fe_final_rating_by ?? '',
        fe_final_rating_date: form?.fe_final_rating_date ?? periodEnd,
        fe_comments: form?.fe_comments ?? '',
        fe_intervening_activities: form?.fe_intervening_activities ?? [],
        groups: form?.groups?.length ? structuredClone(form.groups) : [blankGroup()],
    });

    const rateeName =
        form?.ratee ??
        personnel.find((p) => String(p.id) === String(data.user_id))?.name ??
        defaults?.name ??
        '';

    const patchData = (obj) => setData((d) => ({ ...d, ...obj }));

    const patchGroups = (gi, fn) =>
        setData((d) => ({ ...d, groups: d.groups.map((g, i) => (i === gi ? fn(g) : g)) }));

    const setGroup = (gi, obj) => patchGroups(gi, (g) => ({ ...g, ...obj }));

    const setRow = (gi, ri, obj) =>
        patchGroups(gi, (g) => ({
            ...g,
            rows: g.rows.map((r, j) => (j === ri ? { ...r, ...obj } : r)),
        }));

    const addGroup = () => setData((d) => ({ ...d, groups: [...d.groups, blankGroup()] }));

    const removeGroup = (gi) =>
        setData((d) => ({ ...d, groups: d.groups.filter((_, i) => i !== gi) }));

    /**
     * Clicking a Performance Standards cell marks it as the achieved band for
     * that measure, copies its % into Form E and re-rates the measure
     * (his selectStandard()).
     */
    const selectStandard = (gi, mi, band) =>
        patchGroups(gi, (g) => {
            const rows = g.rows.map((r, j) => (j === mi ? { ...r, selected_band: band } : r));
            const key = { o: 'outstanding', vs: 'very_satisfactory', s: 'satisfactory', u: 'unsatisfactory', p: 'poor' }[band];
            const pct = parsePercent(g.rows[mi]?.[key]);
            if (pct == null) {
                return { ...g, rows };
            }
            const next = { ...g, rows, [MEASURES[mi].pct]: pct };
            return { ...next, [MEASURES[mi].rating]: autoRating(next, mi) ?? '' };
        });

    // Picking the semester rewrites the printed line (and, through it, the
    // signature dates) — unless someone has typed their own wording.
    const setPeriod = (year, semester) => {
        const printed = `${periods.semesters[semester]} ${year}`;
        patchData({ year, semester });
        setRatingPeriod(printed);
    };

    // The rating period dates the signature cells: its first half for the
    // commitment (Reviewed / Approved), its second for the review.
    const setRatingPeriod = (value) => {
        const [start, end] = splitRatingPeriod(value);
        patchData({
            rating_period: value,
            fe_reviewed_date: start,
            fe_approved_date: start,
            discussed_date: end,
            fe_assessed_date: end,
            fe_final_rating_date: end,
        });
    };

    const setRatee = (value) => {
        const person = personnel.find((p) => String(p.id) === String(value));
        patchData({
            user_id: value,
            position_title: person?.position ?? '',
            discussed_with: person?.name ?? '',
        });
    };

    const submit = (e) => {
        e.preventDefault();
        if (editing) patch(route('ipcr.update', form.id));
        else post(route('ipcr.store'));
    };

    const signedDate = new Date().toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

    const sheet = { data, setGroup, setRow, readOnly: false, rateeName };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {editing ? 'Edit IPCR Entry' : 'New IPCR Entry'}
                </h2>
            }
        >
            <Head title={editing ? 'Edit IPCR' : 'New IPCR'} />

            <form onSubmit={submit} className="py-8">
                <div className="mx-auto max-w-[100rem] space-y-6 px-4 sm:px-6 lg:px-8">
                    <Card title="Performance Commitment Details">
                        <div className="grid grid-cols-1 gap-4 pt-4 md:grid-cols-2">
                            {isManager && (
                                <div>
                                    <label className={label}>Personnel (Ratee) *</label>
                                    <select
                                        className={input}
                                        value={data.user_id}
                                        onChange={(e) => setRatee(e.target.value)}
                                    >
                                        <option value="">— Select Personnel —</option>
                                        {personnel.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                                {p.position ? ` (${p.position})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                    <p className="mt-1 text-xs text-gray-500">
                                        Position auto-fills from the selected personnel.
                                    </p>
                                    {errors.user_id && (
                                        <p className="mt-1 text-xs text-rose-600">{errors.user_id}</p>
                                    )}
                                </div>
                            )}

                            {/* One IPCR per semester — two a year, never
                                more — so the period is picked, not typed. */}
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className={label}>Year *</label>
                                    <select
                                        className={input}
                                        value={data.year}
                                        onChange={(e) => setPeriod(Number(e.target.value), data.semester)}
                                    >
                                        {periods.years.map((y) => (
                                            <option key={y} value={y}>
                                                {y}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className={label}>Semester *</label>
                                    <select
                                        className={input}
                                        value={data.semester}
                                        onChange={(e) => setPeriod(data.year, Number(e.target.value))}
                                    >
                                        {Object.entries(periods.semesters).map(([n, name]) => (
                                            <option key={n} value={n}>
                                                {n === '1' ? '1st' : '2nd'} · {name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                {errors.semester && (
                                    <p className="col-span-2 text-xs text-rose-600">{errors.semester}</p>
                                )}
                            </div>

                            <div>
                                <label className={label}>Rating period (as printed)</label>
                                <input
                                    className={input}
                                    placeholder="e.g. January - June 2026"
                                    value={data.rating_period}
                                    onChange={(e) => setRatingPeriod(e.target.value)}
                                />
                                {errors.rating_period && (
                                    <p className="mt-1 text-xs text-rose-600">{errors.rating_period}</p>
                                )}
                            </div>

                            <div>
                                <label className={label}>Position / Designation</label>
                                <input
                                    className={input}
                                    placeholder="e.g. Computer Operator"
                                    value={data.position_title}
                                    onChange={(e) => patchData({ position_title: e.target.value })}
                                />
                            </div>

                            <div>
                                <label className={label}>Office / Unit</label>
                                <input
                                    className={input}
                                    placeholder="e.g. Headquarters, Office of the Director for Personnel, 15SW"
                                    value={data.office_unit}
                                    onChange={(e) => patchData({ office_unit: e.target.value })}
                                />
                            </div>

                            <div>
                                <label className={label}>Status</label>
                                <select
                                    className={input}
                                    value={data.status}
                                    onChange={(e) => patchData({ status: e.target.value })}
                                >
                                    {(isManager
                                        ? ['draft', 'submitted', 'reviewed', 'approved']
                                        : ['draft', 'submitted']
                                    ).map((s) => (
                                        <option key={s} value={s}>
                                            {s[0].toUpperCase() + s.slice(1)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </Card>

                    <Card
                        title="IPCR Form Matrix"
                        scrolls
                        action={
                            <button
                                type="button"
                                onClick={addGroup}
                                className="rounded-full border border-white/20 bg-white/15 px-4 py-1 text-xs font-medium text-white hover:bg-white/25"
                            >
                                + Add Major Output Group
                            </button>
                        }
                        hint={
                            <>
                                Click on any cell under <strong>Performance Standards</strong> (Outstanding, Very
                                Satisfactory, Satisfactory, Unsatisfactory, or Poor) to select it for that measure —
                                the cell will highlight green with a check mark, and its % will automatically be
                                copied into the matching Quality/Timeliness/Quantity % field in FORM E below, which
                                then recalculates the rating for you.
                            </>
                        }
                    >
                        <Matrix
                            {...sheet}
                            selectStandard={selectStandard}
                            addGroup={addGroup}
                            removeGroup={removeGroup}
                        />
                    </Card>

                    <Card
                        title="IPCR Form (FORM E)"
                        scrolls
                        hint={
                            <>
                                Enter the <strong>% accomplished</strong> for Quality, Timeliness, and Quantity of
                                each output below — the Ql1 / Qn2 / T3 ratings and the Average (A4) are computed by
                                comparing your entry against the standards you set in the matrix above. You can
                                still edit any rating manually afterward, or simply click a standard cell in the
                                matrix to have its % copied down here.
                            </>
                        }
                    >
                        <FormE {...sheet} setData={patchData} signedDate={signedDate} />
                    </Card>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-[#0b2a52] px-6 py-2 text-sm font-medium text-white hover:bg-[#071b35] disabled:opacity-50"
                            >
                                {editing ? 'Update IPCR' : 'Save IPCR'}
                            </button>
                            <Link
                                href={editing ? route('ipcr.show', form.id) : route('ipcr.index')}
                                className="rounded-md border border-gray-300 px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </Link>
                        </div>
                        <p className="text-xs text-gray-500">
                            Saving generates the printable matrix and Form E.
                        </p>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
