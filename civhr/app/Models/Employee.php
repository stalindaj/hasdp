<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Employee extends Model
{
    protected $guarded = [];   // all fields fillable (simple for an admin-managed table)

    protected $casts = [
        'date_orig_appt'    => 'date',
        'date_assumption'   => 'date',
        'date_of_birth'     => 'date',
        'date_of_promotion' => 'date',
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth
            ? Carbon::parse($this->date_of_birth)->age
            : null;
    }
}