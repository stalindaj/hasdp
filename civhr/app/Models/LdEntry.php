<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LdEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date'       => 'date',
        'hours'      => 'decimal:1',
        'decided_at' => 'datetime',
    ];

    public const PENDING  = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** Only approved hours count toward the yearly target. */
    public function scopeApproved($query)
    {
        return $query->where('status', self::APPROVED);
    }
}
