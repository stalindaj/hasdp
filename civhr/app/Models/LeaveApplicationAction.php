<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveApplicationAction extends Model
{
    protected $guarded = [];

    public function application()
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
