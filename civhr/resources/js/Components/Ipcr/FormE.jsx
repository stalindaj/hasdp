import { MEASURES, autoRating, fmt, groupAverage, summary } from './rating';

/**
 * IPCR FORM E — the rating sheet. The outputs and success indicators mirror the
 * matrix above it; what is filled in here is the actual accomplishment, the %
 * achieved per measure (which auto-rates Ql1 / Qn2 / T3), the intervening
 * activities, and the signatory blocks.
 */

const HEADER_BG = '#fce4d6';
const SUBHEAD_BG = '#d9e1f2';
const BAND_BG = '#f0f5fa';

const boxed = 'w-full border-0 border-b-2 border-gray-300 bg-transparent p-1 text-center focus:border-indigo-400 focus:ring-0';
const areaCls = 'w-full resize-y border-0 bg-transparent p-1 text-[0.7rem] leading-snug focus:ring-0';

function Text({ value, onChange, readOnly, placeholder, className = '', small }) {
    if (readOnly) {
        return (
            <div className={`p-1 text-center ${small ? 'text-[0.75rem]' : 'text-[0.9rem] font-bold'}`}>
                {value || ''}
            </div>
        );
    }
    return (
        <input
            type="text"
            value={value ?? ''}
            placeholder={placeholder}
            onChange={(e) => onChange(e.target.value)}
            className={`${boxed} ${small ? 'text-[0.75rem] font-semibold' : 'text-[0.9rem] font-bold'} ${className}`}
        />
    );
}

function Area({ value, onChange, readOnly, minHeight = 50 }) {
    if (readOnly) {
        return (
            <div className="whitespace-pre-wrap p-1 text-[0.7rem] leading-snug" style={{ minHeight }}>
                {value || ''}
            </div>
        );
    }
    return (
        <textarea
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value)}
            className={areaCls}
            style={{ minHeight }}
        />
    );
}

function Summary({ value }) {
    return <div className="p-[3px] text-center text-[0.8rem] font-bold text-[#0b2a52]">{value || ''}</div>;
}

export default function FormE({ data, setData, setGroup, readOnly = false, rateeName, signedDate }) {
    const groups = data.groups ?? [];
    const sums = summary(data);
    const activities = data.fe_intervening_activities ?? [];

    // Typing a % re-rates that measure against the matrix standards, exactly
    // like his autoRateGroup(); the rating stays editable afterwards.
    const setPct = (gi, mi, value) => {
        const next = { ...groups[gi], [MEASURES[mi].pct]: value };
        setGroup(gi, {
            [MEASURES[mi].pct]: value,
            [MEASURES[mi].rating]: autoRating(next, mi) ?? '',
        });
    };

    const setActivity = (ai, patch) =>
        setData({
            fe_intervening_activities: activities.map((a, i) => (i === ai ? { ...a, ...patch } : a)),
        });

    const addActivity = () =>
        setData({ fe_intervening_activities: [...activities, { activity: '', rating: 0.25 }] });

    const removeActivity = (ai) =>
        setData({ fe_intervening_activities: activities.filter((_, i) => i !== ai) });

    return (
        <div className="border border-black bg-white p-[2px] font-sans">
            <table className="w-full border-collapse text-[0.75rem] [&_td]:border [&_td]:border-black [&_td]:p-[5px_6px] [&_td]:align-middle">
                <tbody>
                    <tr>
                        <td colSpan={10} className="p-2 text-center text-[0.85rem] font-bold">
                            INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)
                            <span className="float-right">(FORM E)</span>
                        </td>
                    </tr>

                    <tr>
                        <td colSpan={10} className="p-3">
                            <span className="font-bold">
                                I, {rateeName || '________'}, {data.position_title || '________'} of the{' '}
                                {data.office_unit || '________'}, commit to deliver and agree to be rated on the
                                attainment of the following targets in accordance with the indicated measures for
                                the period {data.rating_period || '________'}.
                            </span>
                            <div className="mt-2 text-right">
                                <div className="text-[0.9rem] font-bold underline">{rateeName}</div>
                                <div className="text-[0.8rem]">{data.position_title}</div>
                                <div className="text-[0.8rem]">Date: {signedDate}</div>
                            </div>
                        </td>
                    </tr>

                    <tr style={{ background: HEADER_BG }} className="text-center font-semibold">
                        <td colSpan={3} style={{ width: '30%' }}>Reviewed by</td>
                        <td colSpan={2} style={{ width: '20%' }}>Date</td>
                        <td colSpan={3} style={{ width: '30%' }}>Approved by</td>
                        <td colSpan={2} style={{ width: '20%' }}>Date</td>
                    </tr>
                    <tr>
                        <td colSpan={3} className="p-2 text-center">
                            <Text
                                readOnly={readOnly}
                                placeholder="Enter Reviewer Name"
                                value={data.fe_reviewed_by}
                                onChange={(v) => setData({ fe_reviewed_by: v })}
                            />
                        </td>
                        <td colSpan={2} className="text-center">
                            <Text
                                small
                                readOnly={readOnly}
                                placeholder="e.g. January"
                                value={data.fe_reviewed_date}
                                onChange={(v) => setData({ fe_reviewed_date: v })}
                            />
                        </td>
                        <td colSpan={3} className="p-2 text-center">
                            <Text
                                readOnly={readOnly}
                                placeholder="Enter Approver Name"
                                value={data.fe_approved_by}
                                onChange={(v) => setData({ fe_approved_by: v })}
                            />
                        </td>
                        <td colSpan={2} className="text-center">
                            <Text
                                small
                                readOnly={readOnly}
                                placeholder="e.g. January"
                                value={data.fe_approved_date}
                                onChange={(v) => setData({ fe_approved_date: v })}
                            />
                        </td>
                    </tr>

                    <tr style={{ background: SUBHEAD_BG }} className="text-center font-bold">
                        <td style={{ width: '10%' }}>Output</td>
                        <td colSpan={2} style={{ width: '20%' }}>Success Indicator (Target + Measure)</td>
                        <td colSpan={2} style={{ width: '22%' }}>
                            Actual Accomplishments
                            <br />
                            <span className="text-[0.65rem] font-normal italic">(with % per measure)</span>
                        </td>
                        <td style={{ width: '7%' }}>Ql1</td>
                        <td style={{ width: '7%' }}>Qn2</td>
                        <td style={{ width: '7%' }}>T3</td>
                        <td style={{ width: '7%' }}>A4</td>
                        <td style={{ width: '12%' }}>Remarks</td>
                    </tr>

                    <tr>
                        <td colSpan={10} style={{ background: BAND_BG }} className="p-1 font-bold">
                            <div className="flex flex-wrap items-center gap-1">
                                <span>Strategic Priority No.:</span>
                                {readOnly ? (
                                    <span className="font-normal">{data.strategic_priority}</span>
                                ) : (
                                    <input
                                        type="text"
                                        value={data.strategic_priority ?? ''}
                                        placeholder="e.g. Territorial defense, security and stability services"
                                        onChange={(e) => setData({ strategic_priority: e.target.value })}
                                        className="min-w-[16rem] flex-1 border-0 border-b border-gray-300 bg-transparent p-0 text-[0.75rem] font-normal focus:ring-0"
                                    />
                                )}
                            </div>
                            <div className="flex flex-wrap items-center gap-1">
                                <span>Core Function:</span>
                                {readOnly ? (
                                    <span className="font-normal">{data.core_function}</span>
                                ) : (
                                    <input
                                        type="text"
                                        value={data.core_function ?? ''}
                                        placeholder="e.g. Administration of PAF Civ HRs"
                                        onChange={(e) => setData({ core_function: e.target.value })}
                                        className="min-w-[16rem] flex-1 border-0 border-b border-gray-300 bg-transparent p-0 text-[0.75rem] font-normal focus:ring-0"
                                    />
                                )}
                            </div>
                        </td>
                    </tr>

                    {groups.map((g, gi) => {
                        const avg = groupAverage(g);
                        return (
                            <tr key={gi}>
                                <td className="align-top">
                                    <div className="whitespace-pre-wrap p-1 text-[0.7rem] leading-snug">
                                        {g.major_final_output}
                                    </div>
                                </td>
                                <td colSpan={2} className="align-top">
                                    <div className="whitespace-pre-wrap p-1 text-[0.7rem] leading-snug">
                                        {g.success_indicator}
                                    </div>
                                </td>
                                <td colSpan={2} className="align-top">
                                    <Area
                                        readOnly={readOnly}
                                        value={g.actual_accomplishment}
                                        onChange={(v) => setGroup(gi, { actual_accomplishment: v })}
                                    />
                                    <div className="mt-1 flex gap-1">
                                        {MEASURES.map((m, mi) => (
                                            <div key={m.pct} className="flex-1">
                                                <label className="block text-center text-[0.6rem] text-gray-600">
                                                    {['Qlty %', 'Time %', 'Qty %'][mi]}
                                                </label>
                                                {readOnly ? (
                                                    <div className="text-center text-[0.65rem]">{g[m.pct] ?? ''}</div>
                                                ) : (
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="200"
                                                        value={g[m.pct] ?? ''}
                                                        onChange={(e) => setPct(gi, mi, e.target.value)}
                                                        className="w-full rounded border border-gray-300 p-[2px] text-center text-[0.65rem]"
                                                    />
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </td>

                                {/* Form E prints Quality, Quantity, Timeliness in that order. */}
                                {[0, 2, 1].map((mi) => {
                                    const auto = autoRating(g, mi);
                                    const value = g[MEASURES[mi].rating];
                                    const filled = auto != null && String(auto) === String(value);
                                    return (
                                        <td key={mi} className="text-center align-middle">
                                            {readOnly ? (
                                                <div className="text-center">{value ?? ''}</div>
                                            ) : (
                                                <input
                                                    type="text"
                                                    value={value ?? ''}
                                                    placeholder={['Ql1', 'Qn2', 'T3'][[0, 2, 1].indexOf(mi)]}
                                                    onChange={(e) =>
                                                        setGroup(gi, { [MEASURES[mi].rating]: e.target.value })
                                                    }
                                                    className={`w-full border-0 border-b-2 bg-transparent p-[2px] text-center text-[0.7rem] focus:ring-0 ${
                                                        filled
                                                            ? 'border-emerald-400 bg-emerald-400/10 font-bold'
                                                            : 'border-gray-300'
                                                    }`}
                                                />
                                            )}
                                        </td>
                                    );
                                })}

                                <td className="text-center align-middle text-[0.7rem] font-bold">
                                    {avg != null ? avg.toFixed(2) : ''}
                                </td>
                                <td className="align-top">
                                    <Area
                                        readOnly={readOnly}
                                        value={g.remarks}
                                        onChange={(v) => setGroup(gi, { remarks: v })}
                                    />
                                </td>
                            </tr>
                        );
                    })}

                    <tr>
                        <td colSpan={2} style={{ background: BAND_BG }} className="p-1 font-bold">
                            Average point score
                        </td>
                        <td colSpan={8} style={{ background: BAND_BG }}>
                            <Summary value={fmt(sums.average)} />
                        </td>
                    </tr>

                    <tr>
                        <td colSpan={2} style={{ background: BAND_BG }} className="p-1 align-top font-bold">
                            Add: Intervening Activity:
                            {!readOnly && (
                                <div>
                                    <button
                                        type="button"
                                        onClick={addActivity}
                                        title="Add another intervening activity"
                                        className="mt-1 h-6 w-6 rounded-full bg-emerald-500 text-xs text-white shadow hover:bg-emerald-600"
                                    >
                                        +
                                    </button>
                                </div>
                            )}
                        </td>
                        <td colSpan={8} style={{ background: BAND_BG }}>
                            {activities.map((a, ai) => (
                                <div key={ai} className="mb-1 flex items-center gap-1">
                                    {readOnly ? (
                                        <span className="flex-1 text-[0.7rem]">{a.activity}</span>
                                    ) : (
                                        <input
                                            type="text"
                                            value={a.activity ?? ''}
                                            placeholder="Describe intervening activity"
                                            onChange={(e) => setActivity(ai, { activity: e.target.value })}
                                            className="flex-1 rounded border border-gray-300 p-[2px] text-[0.65rem]"
                                        />
                                    )}
                                    {readOnly ? (
                                        <span className="w-16 text-center text-[0.7rem] font-semibold">
                                            {fmt(a.rating)}
                                        </span>
                                    ) : (
                                        <input
                                            type="number"
                                            step="0.25"
                                            min="0"
                                            value={a.rating ?? ''}
                                            onChange={(e) => setActivity(ai, { rating: e.target.value })}
                                            className="w-20 rounded border border-gray-300 p-[2px] text-center text-[0.65rem]"
                                        />
                                    )}
                                    {!readOnly && (
                                        <button
                                            type="button"
                                            onClick={() => removeActivity(ai)}
                                            title="Remove"
                                            className="rounded-full border border-rose-200 bg-rose-50 px-2 text-[0.65rem] text-rose-600 hover:bg-rose-600 hover:text-white"
                                        >
                                            ✕
                                        </button>
                                    )}
                                </div>
                            ))}
                            <div className="mt-1 flex items-center gap-2">
                                <span className="text-[0.75rem] font-bold">Total:</span>
                                <span className="text-[0.8rem] font-bold text-[#0b2a52]">
                                    {fmt(sums.intervening)}
                                </span>
                            </div>
                        </td>
                    </tr>

                    {[
                        ['Overall point score:', fmt(sums.overall)],
                        ['Overall Equivalent Numerical Rating', fmt(sums.numerical)],
                        ['Overall Equivalent Adjectival Rating', sums.adjectival],
                    ].map(([label, value]) => (
                        <tr key={label}>
                            <td colSpan={2} style={{ background: BAND_BG }} className="p-1 font-bold">
                                {label}
                            </td>
                            <td colSpan={8} style={{ background: BAND_BG }}>
                                <Summary value={value} />
                            </td>
                        </tr>
                    ))}

                    <tr>
                        <td colSpan={10} style={{ background: BAND_BG }} className="p-1 font-bold">
                            Comments and Recommendations for Development Purposes
                        </td>
                    </tr>
                    <tr>
                        <td colSpan={10}>
                            <Area
                                minHeight={40}
                                readOnly={readOnly}
                                value={data.fe_comments}
                                onChange={(v) => setData({ fe_comments: v })}
                            />
                        </td>
                    </tr>

                    <tr style={{ background: HEADER_BG }} className="text-center font-semibold">
                        <td colSpan={2}>Discussed with</td>
                        <td>Date</td>
                        <td colSpan={2}>Assessed by</td>
                        <td>Date</td>
                        <td colSpan={2}>Final Rating by</td>
                        <td colSpan={2}>Date</td>
                    </tr>
                    <tr>
                        <td colSpan={2} className="p-2 text-center">
                            <Text
                                readOnly={readOnly}
                                placeholder="Enter Employee Name"
                                value={data.discussed_with}
                                onChange={(v) => setData({ discussed_with: v })}
                            />
                            <div className="text-[0.7rem] text-gray-600">Employee</div>
                        </td>
                        <td className="text-center">
                            <Text
                                small
                                readOnly={readOnly}
                                placeholder="e.g. June 2026"
                                value={data.discussed_date}
                                onChange={(v) => setData({ discussed_date: v })}
                            />
                        </td>
                        <td colSpan={2} className="p-2 text-center">
                            <Text
                                readOnly={readOnly}
                                placeholder="Enter Assessor Name"
                                value={data.fe_assessed_by}
                                onChange={(v) => setData({ fe_assessed_by: v })}
                            />
                        </td>
                        <td className="text-center">
                            <Text
                                small
                                readOnly={readOnly}
                                placeholder="e.g. June 2026"
                                value={data.fe_assessed_date}
                                onChange={(v) => setData({ fe_assessed_date: v })}
                            />
                        </td>
                        <td colSpan={2} className="p-2 text-center">
                            <Text
                                readOnly={readOnly}
                                placeholder="Enter Final Rater Name"
                                value={data.fe_final_rating_by}
                                onChange={(v) => setData({ fe_final_rating_by: v })}
                            />
                        </td>
                        <td colSpan={2} className="text-center">
                            <Text
                                small
                                readOnly={readOnly}
                                placeholder="e.g. June 2026"
                                value={data.fe_final_rating_date}
                                onChange={(v) => setData({ fe_final_rating_date: v })}
                            />
                        </td>
                    </tr>

                    <tr>
                        <td colSpan={10} className="p-1 text-[0.65rem]">
                            Legend: 1 - Quality (Ql) &nbsp; 2 - Quantity (Qn) &nbsp; 3 - Timeliness (T) &nbsp; 4 -
                            Average (A)
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    );
}
