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

    protected $casts = [
        'discussed_date' => 'date',
        'fe_reviewed_date' => 'date',
        'fe_approved_date' => 'date',
        'fe_assessed_date' => 'date',
        'fe_final_rating_date' => 'date',
        'fe_average_point_score' => 'decimal:2',
        'fe_overall_point_score' => 'decimal:2',
        'fe_overall_numerical_rating' => 'decimal:2',
        'overall_rating' => 'decimal:2',
        'ratee_sig' => 'array',
        'reviewer_sig' => 'array',
        'approver_sig' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

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
     * Recompute the overall scores from the group ratings and persist them.
     * Overall = mean of each group's average rating.
     */
    public function recomputeRatings(): void
    {
        $averages = $this->groups
            ->map(fn (IpcrFormGroup $g) => $g->computeAverage())
            ->filter(fn ($v) => $v !== null)
            ->values();

        $overall = $averages->isNotEmpty()
            ? round($averages->avg(), 2)
            : null;

        if ($overall !== null) {
            $overall = min($overall, 5.00);
        }

        $this->fe_average_point_score = $overall;
        $this->fe_overall_point_score = $overall;
        $this->fe_overall_numerical_rating = $overall;
        $this->fe_overall_adjectival_rating = self::adjectival($overall);
        $this->overall_rating = $overall;
    }
}
