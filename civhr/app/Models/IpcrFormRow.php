<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One performance measure (Quality | Timeliness | Quantity) within a group,
 * holding the target descriptors for each of the five rating levels.
 */
class IpcrFormRow extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(IpcrFormGroup::class, 'group_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(IpcrForm::class, 'ipcr_form_id');
    }
}
