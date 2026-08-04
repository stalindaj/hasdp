<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One performance measure (Quality | Timeliness | Quantity) of an IWOT group,
 * holding its target and the five performance-standard descriptors.
 */
class IwotFormRow extends Model
{
    protected $guarded = [];

    protected $casts = ['sort_order' => 'integer'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(IwotFormGroup::class, 'group_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(IwotForm::class, 'iwot_form_id');
    }
}
