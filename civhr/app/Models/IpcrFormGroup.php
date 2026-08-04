<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Major Final Output / KRA within an IPCR form: its accomplishment, the
 * Quality / Timeliness / Quantity ratings, and the averaged rating.
 */
class IpcrFormGroup extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quality_pct' => 'decimal:2',
        'timeliness_pct' => 'decimal:2',
        'quantity_pct' => 'decimal:2',
        'quality_rating' => 'decimal:2',
        'timeliness_rating' => 'decimal:2',
        'quantity_rating' => 'decimal:2',
        'average_rating' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(IpcrForm::class, 'ipcr_form_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(IpcrFormRow::class, 'group_id')->orderBy('sort_order');
    }

    /** The measure rows, in matrix order, keyed by the % field they drive. */
    public const MEASURES = [
        0 => ['measure' => 'Quality', 'pct' => 'quality_pct', 'rating' => 'quality_rating'],
        1 => ['measure' => 'Timeliness', 'pct' => 'timeliness_pct', 'rating' => 'timeliness_rating'],
        2 => ['measure' => 'Quantity', 'pct' => 'quantity_pct', 'rating' => 'quantity_rating'],
    ];

    /** The first number in a standard descriptor ("95% and above" -> 95.0). */
    public static function parsePercent(?string $text): ?float
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        return preg_match('/(\d+(?:\.\d+)?)/', $text, $m) ? (float) $m[1] : null;
    }

    /**
     * The 5-point rating for an achieved %, read off that measure's five
     * standard descriptors (Outstanding first, Poor as the floor).
     */
    public static function rateFromPercent(?float $pct, array $row): ?int
    {
        if ($pct === null) {
            return null;
        }

        foreach ([['outstanding', 5], ['very_satisfactory', 4], ['satisfactory', 3], ['unsatisfactory', 2]] as [$band, $score]) {
            $threshold = self::parsePercent($row[$band] ?? null);
            if ($threshold !== null && $pct >= $threshold) {
                return $score;
            }
        }

        return 1;
    }

    /**
     * The group's average = mean of whichever of Quality / Timeliness /
     * Quantity ratings were given. Stored back onto average_rating.
     */
    public function computeAverage(): ?float
    {
        $given = array_values(array_filter([
            $this->quality_rating,
            $this->timeliness_rating,
            $this->quantity_rating,
        ], fn ($v) => $v !== null && $v !== ''));

        $avg = count($given) > 0
            ? round(array_sum(array_map('floatval', $given)) / count($given), 2)
            : null;

        $this->average_rating = $avg;

        return $avg;
    }
}
