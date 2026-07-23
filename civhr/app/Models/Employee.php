<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Employee extends Model
{
    protected $guarded = [];   // all fields fillable (simple for an admin-managed table)

    protected $casts = [
        'date_orig_appt'     => 'date',
        'date_assumption'    => 'date',
        'date_of_birth'      => 'date',
        'date_of_promotion'  => 'date',
        'last_ape_date'      => 'date',
        'ape_date_started'   => 'date',
        'ape_date_completed' => 'date',
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function ipcrRecords()
    {
        return $this->hasMany(IpcrRecord::class);
    }

    public function ldEntries()
    {
        return $this->hasMany(LdEntry::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth
            ? Carbon::parse($this->date_of_birth)->age
            : null;
    }
}