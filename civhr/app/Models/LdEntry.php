<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LdEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date'  => 'date',
        'hours' => 'decimal:1',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
