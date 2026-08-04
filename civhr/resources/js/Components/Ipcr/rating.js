/**
 * The IPCR auto-rating engine, carried over from the standalone PHP app so the
 * numbers behave identically. Everything here is pure — the same maths runs
 * again server-side on save (see IpcrFormGroup / IpcrForm), which is what the
 * stored scores come from.
 */

// The three measure rows of a group, in matrix order. Row 0 drives Quality,
// row 1 Timeliness, row 2 Quantity — note Form E prints them Ql1 / Qn2 / T3.
export const MEASURES = [
    { measure: 'Quality', pct: 'quality_pct', rating: 'quality_rating' },
    { measure: 'Timeliness', pct: 'timeliness_pct', rating: 'timeliness_rating' },
    { measure: 'Quantity', pct: 'quantity_pct', rating: 'quantity_rating' },
];

// Performance Standards columns, best to worst — the order the rating is read.
export const BANDS = [
    { key: 'outstanding', band: 'o', label: 'Outstanding', score: 5 },
    { key: 'very_satisfactory', band: 'vs', label: 'Very Satisfactory', score: 4 },
    { key: 'satisfactory', band: 's', label: 'Satisfactory', score: 3 },
    { key: 'unsatisfactory', band: 'u', label: 'Unsatisfactory', score: 2 },
    { key: 'poor', band: 'p', label: 'Poor', score: 1 },
];

/** The first number in a standard descriptor: "95% and above" -> 95. */
export function parsePercent(text) {
    if (text == null || text === '') return null;
    const m = String(text).match(/(\d+(?:\.\d+)?)/);
    return m ? parseFloat(m[1]) : null;
}

const number = (v) => {
    if (v == null || v === '') return null;
    const n = parseFloat(v);
    return Number.isNaN(n) ? null : n;
};

/**
 * The 5-point rating for an achieved %, read off that measure's five standard
 * descriptors. Poor is the floor once a % is given at all.
 */
export function rateFromPercent(pct, row) {
    if (pct == null) return null;
    for (const { key, score } of BANDS) {
        if (score === 1) break;
        const threshold = parsePercent(row?.[key]);
        if (threshold != null && pct >= threshold) return score;
    }
    return 1;
}

/** What the engine would give this measure, ignoring anything typed by hand. */
export function autoRating(group, measureIndex) {
    return rateFromPercent(number(group?.[MEASURES[measureIndex].pct]), group?.rows?.[measureIndex]);
}

/** A4 — the mean of whichever of Ql1 / Qn2 / T3 are filled in. */
export function groupAverage(group) {
    const vals = MEASURES.map((m) => number(group?.[m.rating])).filter((v) => v != null);
    if (vals.length === 0) return null;
    return Math.round((vals.reduce((a, b) => a + b, 0) / vals.length) * 100) / 100;
}

/** The CSC 5-point band of a numerical rating. */
export function adjectival(score) {
    if (score == null || Number.isNaN(score)) return '';
    if (score >= 4.5) return 'Outstanding';
    if (score >= 3.5) return 'Very Satisfactory';
    if (score >= 2.5) return 'Satisfactory';
    if (score >= 1.5) return 'Unsatisfactory';
    return 'Poor';
}

export function interveningTotal(activities) {
    return (activities ?? []).reduce((sum, a) => sum + (number(a?.rating) ?? 0), 0);
}

/**
 * The Form E footer block:
 *   average point score = mean of the groups' A4
 *   overall point score = average + intervening-activity total
 *   numerical rating    = min(overall, 5)
 */
export function summary(data) {
    const averages = (data.groups ?? []).map(groupAverage).filter((v) => v != null);
    const intervening = interveningTotal(data.fe_intervening_activities);

    if (averages.length === 0) {
        return { average: null, intervening, overall: null, numerical: null, adjectival: '' };
    }

    const average = Math.round((averages.reduce((a, b) => a + b, 0) / averages.length) * 100) / 100;
    const overall = Math.round((average + intervening) * 100) / 100;
    const numerical = Math.min(overall, 5);

    return { average, intervening, overall, numerical, adjectival: adjectival(numerical) };
}

/**
 * "January - June 2026" -> ["January", "June 2026"]. The first half dates the
 * Reviewed/Approved cells (the commitment), the second the Discussed/Assessed/
 * Final Rating cells (the review) — his syncApprovedDateFromRatingPeriod().
 */
export function splitRatingPeriod(text) {
    if (!text) return ['', ''];
    const parts = String(text).split(/\s+(?:-|–|to)\s+/i);
    return [(parts[0] ?? '').trim(), (parts[1] ?? '').trim()];
}

export const fmt = (v, dp = 2) => (v == null || v === '' ? '' : Number(v).toFixed(dp));
