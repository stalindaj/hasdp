<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A non-working day — a regular holiday or special (non-working) day from the
 * yearly proclamation. 6.C's working-day count skips these; admins maintain
 * the list under Admin → Holidays when the next year's proclamation is out.
 */
class Holiday extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Store the date as a bare 'Y-m-d'. The default cast would write
     * 'Y-m-d H:i:s', which breaks equality matching (updateOrCreate in the
     * seeder, the duplicate check) against date-only input.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => Carbon::parse($value)->toDateString(),
        );
    }
}
