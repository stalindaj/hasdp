import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import {
    Portal,
    PortalFooter,
    PortalHero,
    PortalModules,
    PortalPanel,
    PortalStat,
    PortalTicker,
} from '@/Components/Portal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

/* Quick Access modules — where each hat goes most often. Holidays and the
   audit trail live here now that the dashboard has no title strip. */
const adminModules = (isSuperadmin) =>
    [
        { title: 'Personnel', blurb: 'Accounts & records', icon: '👥', href: () => route('admin.users.index') },
        { title: 'Leave requests', blurb: 'Decide pending leave', icon: '🗓️', href: () => route('leave.requests') },
        { title: 'IWOT', blurb: 'Work output targets', icon: '🎯', href: () => route('iwot.index') },
        { title: 'IPCR', blurb: 'Performance review', icon: '📋', href: () => route('ipcr.index') },
        { title: 'Holidays', blurb: 'Non-working days', icon: '📅', href: () => route('admin.holidays.index') },
        isSuperadmin && {
            title: 'Audit trail', blurb: 'Every act logged', icon: '🧾', href: () => route('admin.audit.index'),
        },
    ].filter(Boolean);

const EMPLOYEE_MODULES = [
    { title: 'IPCR', blurb: 'Individual Performance', icon: '📋', href: () => route('ipcr.index') },
    { title: 'IWOT', blurb: 'Work Output Targets', icon: '🎯', href: () => route('iwot.index') },
    { title: 'Leave', blurb: 'File / View Leave', icon: '🗓️', href: () => route('leave.index') },
];

const withHrefs = (modules) => modules.map((m) => ({ ...m, href: m.href() }));

/* ── Small pieces ─────────────────────────────────────────────────────── */

/** The IPCR semester tick, on the dark board. */
function SemChip({ done, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            title="Click to toggle"
            className={`p-chip${done ? ' done' : ''}`}
        >
            {done ? '✓' : '—'}
        </button>
    );
}


function BigBox({ title, accent, children }) {
    return (
        <div className={`rounded-2xl border bg-white p-6 shadow-sm ${accent}`}>
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                {title}
            </p>
            <div className="mt-3">{children}</div>
        </div>
    );
}

function Stat({ value, label }) {
    return (
        <div>
            <p className="text-3xl font-bold text-slate-900">{value}</p>
            <p className="text-xs text-slate-500">{label}</p>
        </div>
    );
}

function SemBadge({ done, onClick, clickable }) {
    const base =
        'inline-flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-xs font-bold ring-1 ring-inset transition';
    const style = done
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
        : 'bg-slate-50 text-slate-400 ring-slate-200';
    if (!clickable) {
        return <span className={`${base} ${style}`}>{done ? '✓' : '—'}</span>;
    }
    return (
        <button
            type="button"
            onClick={onClick}
            title="Click to toggle"
            className={`${base} ${style} cursor-pointer hover:ring-2`}
        >
            {done ? '✓' : '—'}
        </button>
    );
}

/** pending / approved / rejected chip for L&D rows. */
function LdStatus({ status }) {
    const styles = {
        pending: 'bg-amber-50 text-amber-700 ring-amber-200',
        approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        rejected: 'bg-red-50 text-red-700 ring-red-200',
    };
    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset ${styles[status] ?? styles.pending}`}
        >
            {status}
        </span>
    );
}

/** "Certificate · Photo" proof links for an L&D entry. */
function LdProofLinks({ entry, dark = false }) {
    if (!entry.certificate && !entry.photo) return null;
    const cls = dark
        ? 'p-link'
        : 'font-medium text-blue-600 underline-offset-2 hover:underline';
    return (
        <span className="space-x-2 text-xs">
            {entry.certificate && (
                <a href={entry.certificate} target="_blank" rel="noopener" className={cls}>
                    Certificate
                </a>
            )}
            {entry.photo && (
                <a href={entry.photo} target="_blank" rel="noopener" className={cls}>
                    Photo
                </a>
            )}
        </span>
    );
}

/* ── Admin view ───────────────────────────────────────────────────────── */

function AdminDashboard({ year, ldTarget, rows, boxes, pendingLeaves, pendingLd }) {
    const { user, isSuperadmin } = usePage().props.auth;
    const adminName = user?.name ?? 'Administrator';

    const decideLd = (entry, decision) => {
        let remarks = null;
        if (decision === 'rejected') {
            remarks = prompt(`Reason for rejecting "${entry.title}"?`);
            if (!remarks) return; // cancelled
        }
        router.patch(
            route('ld.decide', entry.id),
            { decision, remarks },
            { preserveScroll: true },
        );
    };
    const [ldFor, setLdFor] = useState(null); // employee row for the L&D modal
    const ld = useForm({
        title: '',
        hours: '',
        date: new Date().toISOString().slice(0, 10),
    });

    const toggleSem = (row, sem) =>
        router.patch(
            route('dashboard.ipcr', row.id),
            { sem },
            { preserveScroll: true },
        );

    const submitLd = (e) => {
        e.preventDefault();
        ld.post(route('dashboard.ld', ldFor.id), {
            preserveScroll: true,
            onSuccess: () => {
                ld.reset();
                setLdFor(null);
            },
        });
    };

    return (
        <Portal>
            <PortalTicker clearance="admin" />

            <PortalHero
                compact
                name={adminName}
                designation="Administrator"
                subtitle="Command view · personnel status"
            />

            {/* The roster is what an admin comes here for, so it leads. */}
            <div className="p-wrap">
                <div className="p-label">Personnel Status Board</div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <PortalPanel title={`IPCR status · ${year}`}>
                        <div className="p-stats">
                            <PortalStat
                                value={`${boxes.ipcr.sem1_done}/${boxes.ipcr.total}`}
                                label="1st sem submitted"
                            />
                            <PortalStat
                                value={`${boxes.ipcr.sem2_done}/${boxes.ipcr.total}`}
                                label="2nd sem submitted"
                            />
                        </div>
                    </PortalPanel>
                    <PortalPanel title="Leave">
                        <div className="p-stats">
                            <PortalStat value={boxes.leave.pending} label="pending approval" />
                            <PortalStat value={`${boxes.leave.used_days}d`} label={`days used · ${year}`} />
                        </div>
                    </PortalPanel>
                    <PortalPanel title="Learning & Development">
                        <div className="p-stats">
                            <PortalStat value={`${boxes.ld.total_hours}h`} label={`hours approved · ${year}`} />
                            <PortalStat value={boxes.ld.behind} label={`below ${ldTarget}h target`} />
                            <PortalStat value={boxes.ld.pending} label="awaiting approval" />
                        </div>
                    </PortalPanel>
                </div>

                <div className="mt-4 grid gap-4 lg:grid-cols-[1fr_320px]">
                    {/* Everyone's status — scrolls sideways on a phone rather
                        than squashing the columns. */}
                    <PortalPanel title={`Roster · ${rows.length} personnel`}>
                        <div className="overflow-x-auto">
                            <table className="p-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th style={{ textAlign: 'center' }}>IPCR 1st</th>
                                        <th style={{ textAlign: 'center' }}>IPCR 2nd</th>
                                        <th>Leave</th>
                                        <th>L&amp;D</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((r) => (
                                        <tr key={r.id}>
                                            <td>
                                                <Link href={route('dashboard.employee', r.id)}>
                                                    {r.name}
                                                </Link>
                                                <div className="dim">#{r.emp_no}</div>
                                            </td>
                                            <td style={{ textAlign: 'center' }}>
                                                <SemChip done={r.sem1} onClick={() => toggleSem(r, 1)} />
                                            </td>
                                            <td style={{ textAlign: 'center' }}>
                                                <SemChip done={r.sem2} onClick={() => toggleSem(r, 2)} />
                                            </td>
                                            <td>
                                                <Link
                                                    href={route('dashboard.employee', r.id)}
                                                    title="View balances & ledger"
                                                >
                                                    {r.leave_used}d used
                                                </Link>
                                                {r.leave_pending > 0 && (
                                                    <div className="warn">{r.leave_pending} pending</div>
                                                )}
                                            </td>
                                            <td>
                                                <div>{r.ld_hours}h</div>
                                                {r.ld_pending > 0 ? (
                                                    <div className="dim">{r.ld_pending}h pending</div>
                                                ) : (
                                                    <div className="ok">target met</div>
                                                )}
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <button className="p-link" onClick={() => setLdFor(r)}>
                                                    + L&amp;D
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </PortalPanel>

                    {/* Pending sidebar */}
                    <aside className="space-y-4">
                        <PortalPanel title="Pending L&D">
                            {pendingLd.length === 0 ? (
                                <p className="p-empty">Nothing waiting.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {pendingLd.map((l) => (
                                        <li key={l.id} className="p-item">
                                            <p className="text-sm font-medium">{l.employee}</p>
                                            <p className="dim text-xs">
                                                {l.title} · {l.hours}h · {l.date}
                                            </p>
                                            <div className="mt-1.5 flex items-center justify-between">
                                                <LdProofLinks entry={l} dark />
                                                <span className="space-x-3">
                                                    <button
                                                        className="p-link"
                                                        style={{ color: '#3ddc84' }}
                                                        onClick={() => decideLd(l, 'approved')}
                                                    >
                                                        Approve
                                                    </button>
                                                    <button
                                                        className="p-link"
                                                        style={{ color: '#f08a80' }}
                                                        onClick={() => decideLd(l, 'rejected')}
                                                    >
                                                        Reject
                                                    </button>
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </PortalPanel>

                        <PortalPanel
                            title="Pending leave"
                            action={
                                <Link href={route('leave.requests')} className="p-link">
                                    View all
                                </Link>
                            }
                        >
                            {pendingLeaves.length === 0 ? (
                                <p className="p-empty">Nothing waiting.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {pendingLeaves.map((p) => (
                                        <li key={p.id}>
                                            <Link href={route('leave.show', p.id)} className="p-item">
                                                <p className="text-sm font-medium">{p.applicant}</p>
                                                <p className="dim text-xs">
                                                    {p.type} · {p.days}d · {p.inclusive}
                                                </p>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </PortalPanel>
                    </aside>
                </div>
            </div>

            <PortalModules modules={withHrefs(adminModules(isSuperadmin))} />
            <PortalFooter />

            {/* Log L&D modal */}
            <Modal show={!!ldFor} onClose={() => setLdFor(null)} maxWidth="md">
                {ldFor && (
                    <form onSubmit={submitLd} className="p-6">
                        <h3 className="text-lg font-semibold text-slate-800">
                            Log L&amp;D — {ldFor.name}
                        </h3>
                        <div className="mt-5 space-y-4">
                            <div>
                                <InputLabel htmlFor="ld_title" value="Training title" />
                                <TextInput
                                    id="ld_title"
                                    className="mt-1 block w-full"
                                    value={ld.data.title}
                                    onChange={(e) => ld.setData('title', e.target.value)}
                                />
                                <InputError message={ld.errors.title} className="mt-1" />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel htmlFor="ld_hours" value="Hours" />
                                    <TextInput
                                        id="ld_hours"
                                        type="number"
                                        step="0.5"
                                        min="0.5"
                                        className="mt-1 block w-full"
                                        value={ld.data.hours}
                                        onChange={(e) => ld.setData('hours', e.target.value)}
                                    />
                                    <InputError message={ld.errors.hours} className="mt-1" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="ld_date" value="Date" />
                                    <TextInput
                                        id="ld_date"
                                        type="date"
                                        className="mt-1 block w-full"
                                        value={ld.data.date}
                                        onChange={(e) => ld.setData('date', e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end gap-3">
                            <SecondaryButton type="button" onClick={() => setLdFor(null)}>
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton disabled={ld.processing}>
                                Log training
                            </PrimaryButton>
                        </div>
                    </form>
                )}
            </Modal>
        </Portal>
    );
}

/* ── Employee view ────────────────────────────────────────────────────── */

function EmployeeDashboard({ year, ldTarget, me }) {
    const [showLdForm, setShowLdForm] = useState(false);
    const ld = useForm({
        title: '',
        hours: '',
        date: '',
        certificate: null,
        photo: null,
    });

    const submitLd = (e) => {
        e.preventDefault();
        ld.post(route('ld.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                ld.reset();
                setShowLdForm(false);
            },
        });
    };

    return (
        <Portal>
            <PortalTicker clearance={me.clearance} />

            <PortalHero
                name={me.name}
                designation={me.position}
                subtitle="Ready to serve. Ready to strike."
            />

            <PortalModules modules={withHrefs(EMPLOYEE_MODULES)} />

            <div className="p-wrap">
            <div className="p-label">My Status</div>
            <div className="grid gap-4 sm:grid-cols-3">
                <PortalPanel title={`My IPCR · ${year}`}>
                    <div className="space-y-2 text-sm">
                        <p className="flex items-center justify-between">
                            <span>1st semester</span>
                            <span className={`p-chip${me.sem1 ? ' done' : ''}`}>{me.sem1 ? '✓' : '—'}</span>
                        </p>
                        <p className="flex items-center justify-between">
                            <span>2nd semester</span>
                            <span className={`p-chip${me.sem2 ? ' done' : ''}`}>{me.sem2 ? '✓' : '—'}</span>
                        </p>
                    </div>
                </PortalPanel>
                <PortalPanel title="My leave balances">
                    {me.balances ? (
                        <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            {[
                                ['VL', me.balances.vl],
                                ['SL', me.balances.sl],
                                ['Wellness', me.balances.wellness],
                                ['SPL', me.balances.spl],
                            ].map(([k, v]) => (
                                <p key={k} className="flex justify-between">
                                    <span className="dim">{k}</span>
                                    <span className="font-semibold">{v}</span>
                                </p>
                            ))}
                        </div>
                    ) : (
                        <p className="p-empty">No employee record linked.</p>
                    )}
                    {me.leave_pending > 0 && (
                        <p className="warn mt-2">{me.leave_pending} application(s) pending</p>
                    )}
                    <Link href={route('leave.create')} className="p-link mt-3 inline-block">
                        File leave →
                    </Link>
                </PortalPanel>
                <PortalPanel title="My L&D">
                    <PortalStat value={`${me.ld_hours}h`} label={`approved, of ${ldTarget}h · ${year}`} />
                    {me.ld_pending > 0 ? (
                        <p className="dim mt-1 text-xs">{me.ld_pending}h still pending</p>
                    ) : (
                        <p className="ok mt-1 text-xs">target met</p>
                    )}
                    <button
                        type="button"
                        onClick={() => setShowLdForm(true)}
                        className="p-link mt-3 inline-block"
                    >
                        Submit a training →
                    </button>
                </PortalPanel>
            </div>

            {me.ld_entries.length > 0 && (
                <div className="mt-4">
                <PortalPanel title={`My trainings · ${year}`}>
                    <ul className="space-y-2 text-sm">
                        {me.ld_entries.map((l, i) => (
                            <li key={i} className="p-item">
                                <div className="flex items-center justify-between gap-3">
                                    <span>{l.title}</span>
                                    <span className="dim flex shrink-0 items-center gap-2">
                                        {l.hours}h · {l.date}
                                        <LdStatus status={l.status} />
                                    </span>
                                </div>
                                <div className="mt-0.5 flex items-center justify-between">
                                    <LdProofLinks entry={l} dark />
                                    {l.status === 'rejected' && l.remarks && (
                                        <p className="text-xs" style={{ color: '#f08a80' }}>
                                            {l.remarks}
                                        </p>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </PortalPanel>
                </div>
            )}
            </div>

            <PortalFooter />

            {/* Submit-a-training modal */}
            <Modal show={showLdForm} onClose={() => setShowLdForm(false)} maxWidth="md">
                <form onSubmit={submitLd} className="p-6">
                    <h3 className="text-lg font-semibold text-slate-800">
                        Submit a training
                    </h3>
                    <p className="mt-1 text-sm text-slate-500">
                        Attach the certificate, a photo taken during the
                        training, or both. The hours count once an admin
                        approves.
                    </p>
                    <div className="mt-5 space-y-4">
                        <div>
                            <InputLabel htmlFor="my_ld_title" value="Training title" />
                            <TextInput
                                id="my_ld_title"
                                className="mt-1 block w-full"
                                value={ld.data.title}
                                onChange={(e) => ld.setData('title', e.target.value)}
                            />
                            <InputError message={ld.errors.title} className="mt-1" />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="my_ld_hours" value="Hours" />
                                <TextInput
                                    id="my_ld_hours"
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    className="mt-1 block w-full"
                                    value={ld.data.hours}
                                    onChange={(e) => ld.setData('hours', e.target.value)}
                                />
                                <InputError message={ld.errors.hours} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="my_ld_date" value="Date" />
                                <TextInput
                                    id="my_ld_date"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={ld.data.date}
                                    onChange={(e) => ld.setData('date', e.target.value)}
                                />
                                <InputError message={ld.errors.date} className="mt-1" />
                            </div>
                        </div>
                        <div>
                            <InputLabel htmlFor="my_ld_cert" value="Certificate (photo/scan)" />
                            <input
                                id="my_ld_cert"
                                type="file"
                                accept="image/*"
                                className="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100"
                                onChange={(e) => ld.setData('certificate', e.target.files[0] ?? null)}
                            />
                            <InputError message={ld.errors.certificate} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="my_ld_photo" value="Photo during the training" />
                            <input
                                id="my_ld_photo"
                                type="file"
                                accept="image/*"
                                className="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100"
                                onChange={(e) => ld.setData('photo', e.target.files[0] ?? null)}
                            />
                            <InputError message={ld.errors.photo} className="mt-1" />
                        </div>
                        <p className="text-xs text-slate-400">
                            At least one image is required · JPG/PNG · max 5 MB each.
                        </p>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={() => setShowLdForm(false)}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={ld.processing}>
                            {ld.processing ? 'Submitting…' : 'Submit for approval'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </Portal>
    );
}

/* ── Page ─────────────────────────────────────────────────────────────── */

export default function Dashboard(props) {
    const page = usePage().props;
    const flash = page.flash;

    // No white title strip here — the portal banner starts directly under the
    // nav, which is the whole point of the design.
    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />
            {flash?.success && (
                <div className="bg-emerald-500/15 px-4 py-2 text-center text-sm text-emerald-100">
                    {flash.success}
                </div>
            )}
            {props.mode === 'admin' ? (
                <AdminDashboard {...props} />
            ) : (
                <EmployeeDashboard {...props} />
            )}
        </AuthenticatedLayout>
    );
}
