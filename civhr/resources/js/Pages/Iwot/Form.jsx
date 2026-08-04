import Matrix from '@/Components/Ipcr/Matrix';
import { MEASURES } from '@/Components/Ipcr/rating';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const navy = '#0b2a52';

const label = 'block text-xs font-semibold uppercase tracking-wide text-gray-500';
const input =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#0b2a52] focus:ring-[#0b2a52]';

function blankGroup() {
    return {
        major_final_output: '',
        timeliness: '',
        success_indicator: '',
        rows: MEASURES.map((m) => ({
            performance_measure: m.measure,
            performance_targets: '',
            outstanding: '',
            very_satisfactory: '',
            satisfactory: '',
            unsatisfactory: '',
            poor: '',
        })),
    };
}

function Card({ title, action, hint, children }) {
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
            <div className="overflow-x-auto p-4 pt-0">{children}</div>
        </section>
    );
}

export default function Form({ form, personnel, isManager, currentUserId, defaults }) {
    const editing = Boolean(form?.id);

    const { data, setData, post, patch, processing, errors } = useForm({
        user_id: form?.user_id ?? (isManager ? '' : currentUserId),
        position_title: form?.position_title ?? (isManager ? '' : (defaults?.position ?? '')),
        office_unit: form?.office_unit ?? '',
        rating_period: form?.rating_period ?? '',
        status: form?.status ?? 'draft',
        prepared_by: form?.prepared_by ?? (isManager ? '' : (defaults?.name ?? '')),
        prepared_designation: form?.prepared_designation ?? 'Employee',
        approved_by: form?.approved_by ?? '',
        approved_designation: form?.approved_designation ?? 'NCOIC',
        groups: form?.groups?.length ? structuredClone(form.groups) : [blankGroup()],
    });

    const employeeName =
        form?.employee ??
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
    const removeGroup = (gi) => setData((d) => ({ ...d, groups: d.groups.filter((_, i) => i !== gi) }));

    const setEmployee = (value) => {
        const person = personnel.find((p) => String(p.id) === String(value));
        patchData({
            user_id: value,
            position_title: person?.position ?? '',
            prepared_by: person?.name ?? '',
        });
    };

    const submit = (e) => {
        e.preventDefault();
        if (editing) patch(route('iwot.update', form.id));
        else post(route('iwot.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {editing ? 'Edit IWOT' : 'New IWOT'}
                </h2>
            }
        >
            <Head title={editing ? 'Edit IWOT' : 'New IWOT'} />

            <form onSubmit={submit} className="py-8">
                <div className="mx-auto max-w-[100rem] space-y-6 px-4 sm:px-6 lg:px-8">
                    <Card title="Work Output Target Details">
                        <div className="grid grid-cols-1 gap-4 pt-4 md:grid-cols-2">
                            {isManager && (
                                <div>
                                    <label className={label}>Personnel *</label>
                                    <select
                                        className={input}
                                        value={data.user_id}
                                        onChange={(e) => setEmployee(e.target.value)}
                                    >
                                        <option value="">— Select Personnel —</option>
                                        {personnel.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                                {p.position ? ` (${p.position})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.user_id && (
                                        <p className="mt-1 text-xs text-rose-600">{errors.user_id}</p>
                                    )}
                                </div>
                            )}

                            <div>
                                <label className={label}>Position / Designation</label>
                                <input
                                    className={input}
                                    placeholder="e.g. Administrative Aide III (Clerk I)"
                                    value={data.position_title}
                                    onChange={(e) => patchData({ position_title: e.target.value })}
                                />
                            </div>

                            <div>
                                <label className={label}>Office / Unit</label>
                                <input
                                    className={input}
                                    placeholder="e.g. 15th Strike Wing, PAF / Office of Directorate for Personnel"
                                    value={data.office_unit}
                                    onChange={(e) => patchData({ office_unit: e.target.value })}
                                />
                            </div>

                            <div>
                                <label className={label}>Period covered</label>
                                <input
                                    className={input}
                                    placeholder="e.g. January - June 2026"
                                    value={data.rating_period}
                                    onChange={(e) => patchData({ rating_period: e.target.value })}
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
                                        ? ['draft', 'submitted', 'approved']
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
                        title="IWOT Matrix"
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
                                Set the targets for the coming period: one <strong>Major Final Output</strong> per
                                block, its success indicator, and what counts as Outstanding down to Poor for each
                                of Quality, Timeliness and Quantity. The IPCR at the end of the period is rated
                                against exactly these standards.
                            </>
                        }
                    >
                        <Matrix
                            data={data}
                            setGroup={setGroup}
                            setRow={setRow}
                            addGroup={addGroup}
                            removeGroup={removeGroup}
                            rateeName={employeeName}
                        />
                    </Card>

                    <Card title="Signatories">
                        <div className="grid grid-cols-1 gap-6 pt-4 md:grid-cols-2">
                            <div className="space-y-3 rounded-md border border-gray-200 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-[#0b2a52]">
                                    Prepared by
                                </p>
                                <div>
                                    <label className={label}>Name</label>
                                    <input
                                        className={input}
                                        value={data.prepared_by}
                                        onChange={(e) => patchData({ prepared_by: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className={label}>Designation</label>
                                    <input
                                        className={input}
                                        value={data.prepared_designation}
                                        onChange={(e) => patchData({ prepared_designation: e.target.value })}
                                    />
                                </div>
                            </div>

                            <div className="space-y-3 rounded-md border border-gray-200 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-[#0b2a52]">
                                    Approved by
                                </p>
                                <div>
                                    <label className={label}>Name</label>
                                    <input
                                        className={input}
                                        placeholder="e.g. TSg Ronnie R Doble PAF"
                                        value={data.approved_by}
                                        onChange={(e) => patchData({ approved_by: e.target.value })}
                                    />
                                </div>
                                <div>
                                    <label className={label}>Designation</label>
                                    <input
                                        className={input}
                                        placeholder="e.g. NCOIC"
                                        value={data.approved_designation}
                                        onChange={(e) => patchData({ approved_designation: e.target.value })}
                                    />
                                </div>
                            </div>
                        </div>
                    </Card>

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-[#0b2a52] px-6 py-2 text-sm font-medium text-white hover:bg-[#071b35] disabled:opacity-50"
                            >
                                {editing ? 'Update IWOT' : 'Save draft'}
                            </button>
                            <Link
                                href={editing ? route('iwot.show', form.id) : route('iwot.index')}
                                className="rounded-md border border-gray-300 px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </Link>
                        </div>
                        <p className="text-xs text-gray-500">
                            Saving generates the printable IWOT sheet, ready to sign.
                        </p>
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
