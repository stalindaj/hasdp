<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpcrRecord extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sem1_done' => 'boolean',
        'sem2_done' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
