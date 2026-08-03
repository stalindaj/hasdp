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
