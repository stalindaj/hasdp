<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveCreditEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function application()
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id');
    }
}
