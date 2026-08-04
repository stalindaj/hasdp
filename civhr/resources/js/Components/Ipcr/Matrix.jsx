import { BANDS, MEASURES } from './rating';

/**
 * The IPCR performance-standards matrix — the sheet the ratee actually fills
 * in, laid out exactly like the printed form: one Major Final Output per block
 * of three measure rows (Quality / Timeliness / Quantity), each measure
 * carrying its target and the five Performance Standards descriptors.
 *
 * Clicking a standard cell marks it as the achieved band for that measure and
 * copies its % down into Form E, which re-rates itself.
 */

const cellText = 'w-full resize-y border-0 bg-transparent p-1 text-[0.7rem] leading-snug focus:ring-0';

function Cell({ value, onChange, rows = 2, readOnly, className = '', minHeight }) {
    if (readOnly) {
        return (
            <div
                className="whitespace-pre-wrap p-1 text-[0.7rem] leading-snug"
                style={minHeight ? { minHeight } : undefined}
            >
                {value || ''}
            </div>
        );
    }

    return (
        <textarea
            rows={rows}
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value)}
            className={`${cellText} ${className}`}
            style={minHeight ? { minHeight } : undefined}
        />
    );
}

export default function Matrix({
    data,
    setGroup,
    setRow,
    selectStandard,
    addGroup,
    removeGroup,
    rateeName,
    readOnly = false,
}) {
    const groups = data.groups ?? [];

    return (
        <div className="ipcr-sheet ipcr-matrix border border-black bg-white p-[2px]">
            <table className="w-full border-collapse text-[0.72rem] [&_td]:border [&_td]:border-black [&_td]:p-[3px] [&_th]:border [&_th]:border-black [&_th]:p-[6px_4px] [&_th]:text-center [&_th]:font-bold">
                <tbody>
                    {/* Ratee header block, live from the fields above. */}
                    <tr>
                        <td colSpan={readOnly ? 10 : 11} className="py-3 text-center">
                            <div className="font-bold">{rateeName || '—'}</div>
                            <div className="text-[0.8em] underline">{data.position_title}</div>
                            <div className="text-[0.75em] italic text-gray-600">{data.office_unit}</div>
                            <div className="text-[0.7em]">{data.rating_period}</div>
                        </td>
                    </tr>

                    <tr>
                        <th rowSpan={2} style={{ width: '14%', minWidth: 140 }}>
                            MAJOR FINAL OUTPUT
                        </th>
                        <th rowSpan={2} style={{ width: '9%', minWidth: 80 }}>
                            TIMELINESS
                        </th>
                        <th style={{ width: '12%', minWidth: 100 }}>PERFORMANCE MEASURES</th>
                        <th style={{ width: '12%', minWidth: 100 }}>PERFORMANCE TARGETS</th>
                        <th rowSpan={2} style={{ width: '18%', minWidth: 140 }}>
                            SUCCESS INDICATOR
                            <br />
                            <span className="text-[0.8em] font-normal italic">Measures/Targets</span>
                        </th>
                        <th colSpan={5}>PERFORMANCE STANDARDS</th>
                        {!readOnly && <th rowSpan={2} style={{ width: '4%', minWidth: 30 }} />}
                    </tr>
                    <tr>
                        <th className="font-normal italic">(Measures)</th>
                        <th className="font-normal italic">(Targets)</th>
                        {BANDS.map((b) => (
                            <th key={b.band} style={{ width: '9%', minWidth: 75 }} className="text-[0.65rem]">
                                {b.label}
                            </th>
                        ))}
                    </tr>

                    {groups.length === 0 && (
                        <tr>
                            <td colSpan={readOnly ? 10 : 11} className="py-6 text-center text-gray-500">
                                {readOnly
                                    ? 'No major final outputs recorded.'
                                    : 'No outputs yet — use “Add Major Output Group” above to start.'}
                            </td>
                        </tr>
                    )}

                    {groups.map((g, gi) =>
                        MEASURES.map((m, mi) => (
                            <tr key={`${gi}-${mi}`}>
                                {mi === 0 && (
                                    <>
                                        <td rowSpan={3}>
                                            <Cell
                                                rows={3}
                                                readOnly={readOnly}
                                                value={g.major_final_output}
                                                onChange={(v) => setGroup(gi, { major_final_output: v })}
                                            />
                                        </td>
                                        <td rowSpan={3}>
                                            <Cell
                                                rows={3}
                                                readOnly={readOnly}
                                                value={g.timeliness}
                                                onChange={(v) => setGroup(gi, { timeliness: v })}
                                            />
                                        </td>
                                    </>
                                )}

                                <td className="measure-cell text-center align-middle font-semibold">
                                    {m.measure}
                                </td>
                                <td>
                                    <Cell
                                        readOnly={readOnly}
                                        value={g.rows?.[mi]?.performance_targets}
                                        onChange={(v) => setRow(gi, mi, { performance_targets: v })}
                                    />
                                </td>

                                {mi === 0 && (
                                    <td rowSpan={3} className="align-middle">
                                        <Cell
                                            rows={5}
                                            minHeight={140}
                                            readOnly={readOnly}
                                            value={g.success_indicator}
                                            onChange={(v) => setGroup(gi, { success_indicator: v })}
                                        />
                                    </td>
                                )}

                                {BANDS.map((b) => {
                                    // Only the IPCR marks an achieved band; the
                                    // IWOT is target-setting, so it passes no
                                    // selectStandard and the cells stay plain.
                                    const pickable = !readOnly && typeof selectStandard === 'function';
                                    const selected = g.rows?.[mi]?.selected_band === b.band;
                                    return (
                                        <td key={b.band} className="p-0">
                                            <div
                                                onClick={pickable ? () => selectStandard(gi, mi, b.band) : undefined}
                                                title={
                                                    pickable
                                                        ? `Mark ${b.label} as the achieved standard for ${m.measure}`
                                                        : undefined
                                                }
                                                className={`relative min-h-[35px] rounded transition ${
                                                    pickable ? 'cursor-pointer hover:bg-indigo-500/10' : ''
                                                } ${selected ? 'bg-emerald-400/25' : ''}`}
                                            >
                                                <Cell
                                                    readOnly={readOnly}
                                                    value={g.rows?.[mi]?.[b.key]}
                                                    onChange={(v) => setRow(gi, mi, { [b.key]: v })}
                                                />
                                                {selected && (
                                                    <span className="pointer-events-none absolute right-[3px] top-[1px] text-[0.7rem] font-bold text-emerald-700">
                                                        ✓
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                    );
                                })}

                                {!readOnly && mi === 0 && (
                                    <td rowSpan={3} className="text-center align-middle">
                                        <button
                                            type="button"
                                            onClick={() => removeGroup(gi)}
                                            title="Remove this group"
                                            className="rounded-full border border-rose-200 bg-rose-50 px-2 py-[1px] text-[0.65rem] text-rose-600 hover:bg-rose-600 hover:text-white"
                                        >
                                            ✕
                                        </button>
                                    </td>
                                )}
                            </tr>
                        )),
                    )}
                </tbody>
            </table>

            {!readOnly && groups.length > 0 && (
                <div className="p-2 text-right">
                    <button
                        type="button"
                        onClick={addGroup}
                        className="rounded-full border border-dashed border-gray-400 px-3 py-1 text-xs font-medium text-gray-600 hover:border-[#0b2a52] hover:text-[#0b2a52]"
                    >
                        + Add Major Output Group
                    </button>
                </div>
            )}
        </div>
    );
}
