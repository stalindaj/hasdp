import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';

/**
 * Admin → L&D → Reports: the L&D part of the accomplishment report.
 *
 * Every table is computed live from the approved filings. Click a cell to
 * correct it — the edit is saved for this period, the cell turns amber, and
 * totals and percentages follow it; ↺ puts the live figure back. Each table
 * copies straight into Word, and the whole set downloads as an Excel file.
 */

const QUARTERS = [
    [0, 'Whole year'],
    [1, 'As of 1st Quarter'],
    [2, 'As of 2nd Quarter'],
    [3, 'As of 3rd Quarter'],
    [4, 'As of 4th Quarter'],
];

// Inline, so the borders survive a paste into Word.
const cellStyle = { border: '1px solid #000', padding: '4px 6px', verticalAlign: 'middle' };

function show(v) {
    if (v === null || v === undefined || v === '') return '';
    if (typeof v === 'number' || (/^-?\d+(\.\d+)?$/.test(String(v)) && String(v).length < 12)) {
        const n = Number(v);
        return n.toLocaleString('en-US', { maximumFractionDigits: 1 });
    }
    return String(v);
}

/** A table cell that turns into an input on click when it is editable. */
function Cell({ table, row, col, value, editable, edited, onSave, onReset, wide }) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState('');
    const isText = !col.sum;
    const align = col.sum || col.key.startsWith('pct') || col.key === 'complied' ? 'center' : 'left';

    const start = () => {
        if (!editable) return;
        setDraft(value === null || value === undefined ? '' : String(value));
        setEditing(true);
    };
    const commit = () => {
        setEditing(false);
        if (draft !== String(value ?? '')) onSave(table, row, col, draft);
    };

    if (editing) {
        const common = {
            autoFocus: true,
            value: draft,
            onChange: (e) => setDraft(e.target.value),
            onBlur: commit,
            onKeyDown: (e) => {
                if (e.key === 'Escape') setEditing(false);
                if (e.key === 'Enter' && !(wide && e.shiftKey)) {
                    e.preventDefault();
                    commit();
                }
            },
            className: 'w-full min-w-[4rem] rounded border border-blue-400 px-1 py-0.5 text-[13px] focus:outline-none focus:ring-1 focus:ring-blue-500',
        };
        return (
            <td style={cellStyle}>
                {wide ? <textarea rows={3} {...common} /> : <input {...common} />}
            </td>
        );
    }

    const isEdited = edited && Object.prototype.hasOwnProperty.call(edited, col.key);

    return (
        <td
            style={{ ...cellStyle, textAlign: align, whiteSpace: isText ? 'pre-line' : undefined }}
            onClick={start}
            className={`group relative ${editable ? 'cursor-text hover:bg-blue-50/60' : ''} ${isEdited ? 'bg-amber-50' : ''}`}
            title={isEdited ? `Edited — live value: ${show(edited[col.key]) || '(blank)'}` : editable ? 'Click to edit' : undefined}
        >
            {show(value)}
            {isEdited && (
                <button
                    type="button"
                    data-noexport
                    onClick={(e) => {
                        e.stopPropagation();
                        onReset(table, row, col);
                    }}
                    className="absolute right-0.5 top-0.5 hidden rounded bg-white px-1 text-[11px] text-amber-700 shadow ring-1 ring-amber-200 group-hover:inline"
                    title="Back to the live value"
                >
                    ↺
                </button>
            )}
        </td>
    );
}

function ReportTable({ table, onSave, onReset }) {
    const ref = useRef(null);
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        // A clean copy for Word: the caption and the bordered grid, no buttons.
        const clone = ref.current.cloneNode(true);
        clone.querySelectorAll('[data-noexport]').forEach((n) => n.remove());
        const html = `<p style="font-family:Arial;font-style:italic">${table.title}</p>${clone.outerHTML}`;
        const text = [...clone.querySelectorAll('tr')]
            .map((tr) => [...tr.children].map((c) => c.innerText.trim()).join('\t'))
            .join('\n');
        try {
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([text], { type: 'text/plain' }),
                }),
            ]);
        } catch {
            await navigator.clipboard.writeText(text);
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    const wideCols = ['title', 'trainings', 'competency', 'level', 'parameter', 'name'];

    return (
        <section className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm italic text-slate-700">{table.title}</p>
                <button
                    type="button"
                    onClick={copy}
                    className="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
                >
                    {copied ? 'Copied ✓' : 'Copy for Word'}
                </button>
            </div>
            <div className="overflow-x-auto">
                <table
                    ref={ref}
                    style={{ borderCollapse: 'collapse', fontFamily: 'Arial, Helvetica, sans-serif', fontSize: '13px', width: '100%' }}
                >
                    <thead>
                        <tr>
                            {table.columns.map((c) => (
                                <th key={c.key} style={{ ...cellStyle, textAlign: 'center', fontWeight: 700 }}>
                                    {c.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {table.rows.map((row) => (
                            <tr key={row.key}>
                                {table.columns.map((c) => (
                                    <Cell
                                        key={c.key}
                                        table={table}
                                        row={row}
                                        col={c}
                                        value={row.cells[c.key]}
                                        editable={c.edit}
                                        edited={row.edited}
                                        wide={wideCols.includes(c.key)}
                                        onSave={onSave}
                                        onReset={onReset}
                                    />
                                ))}
                            </tr>
                        ))}
                        {table.rows.length === 0 && (
                            <tr>
                                <td colSpan={table.columns.length} style={{ ...cellStyle, textAlign: 'center', color: '#777' }}>
                                    Nothing recorded for this period.
                                </td>
                            </tr>
                        )}
                        {table.total && (
                            <tr>
                                {table.columns.map((c) => (
                                    <td
                                        key={c.key}
                                        style={{ ...cellStyle, fontWeight: 700, textAlign: c.sum || c.key.startsWith('pct') ? 'center' : 'right' }}
                                    >
                                        {show(table.total[c.key])}
                                    </td>
                                ))}
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export default function Reports({ year, quarter, period, years, tables }) {
    const params = { year, quarter };

    const go = (next) => router.get(route('ld.reports', { ...params, ...next }), {}, { preserveScroll: true });

    const save = (table, row, col, value) =>
        router.post(
            route('ld.reports.cell'),
            { ...params, report: table.report, row_key: row.key, col: col.key, value },
            { preserveScroll: true, preserveState: true },
        );

    const reset = (table, row, col) =>
        router.delete(route('ld.reports.reset'), {
            data: { ...params, report: table.report, row_key: row.key, col: col.key },
            preserveScroll: true,
            preserveState: true,
        });

    const select = 'rounded-md border-slate-300 py-1 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">L&amp;D Report</h2>
                        <p className="text-xs text-slate-500">
                            {period} ·{' '}
                            <Link href={route('ld.index', { year })} className="text-blue-600 hover:underline">
                                back to L&amp;D
                            </Link>
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <select className={select} value={year} onChange={(e) => go({ year: e.target.value })}>
                            {years.map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                        <select className={select} value={quarter} onChange={(e) => go({ quarter: e.target.value })}>
                            {QUARTERS.map(([q, label]) => (
                                <option key={q} value={q}>{label}</option>
                            ))}
                        </select>
                        <a
                            href={route('ld.reports.export', params)}
                            className="rounded-md bg-emerald-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-800"
                        >
                            Download Excel
                        </a>
                    </div>
                </div>
            }
        >
            <Head title="L&D Report" />

            <div className="py-6">
                <div className="mx-auto max-w-[110rem] space-y-5 px-4 sm:px-6 lg:px-8">
                    <p className="text-xs text-slate-500">
                        Figures come from <strong>approved</strong> filings of the active roster. Click a cell to
                        correct it for this period — edited cells turn{' '}
                        <span className="rounded bg-amber-50 px-1 ring-1 ring-amber-200">amber</span> and the totals
                        follow; hover one and press ↺ to go back to the live figure. Totals and percentages are worked
                        out for you.
                    </p>

                    {tables.map((t) => (
                        <ReportTable key={t.report} table={t} onSave={save} onReset={reset} />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
