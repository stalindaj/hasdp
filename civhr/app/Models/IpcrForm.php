<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A full IPCR form (one ratee, one rating period). Ported from the standalone
 * PHP app; the rating maths lives here so it is computed server-side and never
 * trusted from the client.
 */
class IpcrForm extends Model
{
    protected $guarded = [];

    // The Form E date cells are free text ("January", "June 2026" — filled from
    // the rating period), so they are deliberately not cast to dates.
    protected $casts = [
        'fe_average_point_score' => 'decimal:2',
        'fe_overall_point_score' => 'decimal:2',
        'fe_overall_numerical_rating' => 'decimal:2',
        'overall_rating' => 'decimal:2',
        'ratee_sig' => 'array',
        'reviewer_sig' => 'array',
        'approver_sig' => 'array',
        'fe_intervening_activities' => 'array',
        'signature_uploads' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * The six blocks that can carry an e-signature on the printed Form E: the
     * commitment and review halves are signed separately, exactly as on paper.
     */
    public const SIGNATURE_SLOTS = ['ratee', 'reviewer', 'approver', 'discussed', 'assessed', 'final'];

    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const APPROVED = 'approved';
    public const RETURNED = 'returned';

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Freeze the name/designation of the ratee and chosen signatories into the
     * form (mirrors the leave *_sig blocks). Called when the ratee submits.
     */
    public function freezeSignatories(): void
    {
        $this->ratee_sig = $this->ratee?->signatoryBlock();
        $this->reviewer_sig = $this->reviewer?->signatoryBlock();
        $this->approver_sig = $this->approver?->signatoryBlock();
    }

    public function groups(): HasMany
    {
        return $this->hasMany(IpcrFormGroup::class)->orderBy('sort_order');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(IpcrFormRow::class);
    }

    /**
     * The PH government 5-point IPCR scale (identical to the original app).
     */
    public static function adjectival(?float $score): string
    {
        if ($score === null) {
            return '-';
        }
        if ($score >= 4.5) {
            return 'Outstanding';
        }
        if ($score >= 3.5) {
            return 'Very Satisfactory';
        }
        if ($score >= 2.5) {
            return 'Satisfactory';
        }
        if ($score >= 1.5) {
            return 'Unsatisfactory';
        }

        return 'Poor';
    }

    /**
     * Recompute the summary scores exactly as his updateSummary() does:
     *   average point score = mean of each group's average rating
     *   overall point score = average point score + intervening-activity total
     *   numerical rating     = min(overall point score, 5)
     *   adjectival           = the CSC 5-point band of the numerical rating
     */
    public function recomputeRatings(): void
    {
        $averages = $this->groups
            ->map(fn (IpcrFormGroup $g) => $g->computeAverage())
            ->filter(fn ($v) => $v !== null)
            ->values();

        $avg = $averages->isNotEmpty()
            ? round($averages->avg(), 2)
            : null;

        $intervening = $this->interveningTotal();

        $overall = $avg === null ? null : round($avg + $intervening, 2);
        $numerical = $overall === null ? null : min($overall, 5.00);

        $this->fe_average_point_score = $avg;
        $this->fe_intervening_activity = number_format($intervening, 2, '.', '');
        $this->fe_overall_point_score = $overall;
        $this->fe_overall_numerical_rating = $numerical;
        $this->fe_overall_adjectival_rating = self::adjectival($numerical);
        $this->overall_rating = $numerical;
    }

    /** Sum of the intervening-activity ratings (his computeInterveningTotal()). */
    public function interveningTotal(): float
    {
        return collect($this->fe_intervening_activities ?? [])
            ->sum(fn ($a) => is_numeric($a['rating'] ?? null) ? (float) $a['rating'] : 0);
    }
}
