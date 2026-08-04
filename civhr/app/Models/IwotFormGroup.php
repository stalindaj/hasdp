<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One Major Final Output on an IWOT sheet, with its three measure rows. */
class IwotFormGroup extends Model
{
    protected $guarded = [];

    protected $casts = ['sort_order' => 'integer'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(IwotForm::class, 'iwot_form_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(IwotFormRow::class, 'group_id')->orderBy('sort_order');
    }
}
