<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LeaveApplication extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date_filing'      => 'date',
        'date_from'        => 'date',
        'date_to'          => 'date',
        'cert_as_of'       => 'date',
        'certified_at'     => 'datetime',
        'recommended_at'   => 'datetime',
        'decided_at'       => 'datetime',
        'applicant_sig'    => 'array',
        'hr_officer_sig'   => 'array',
        'recommender_sig'  => 'array',
        'approver_sig'     => 'array',
        'working_days'     => 'decimal:2',
        'days_with_pay'    => 'decimal:2',
        'days_without_pay' => 'decimal:2',
        'days_others'      => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function hrOfficer()
    {
        return $this->belongsTo(User::class, 'hr_officer_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function actions()
    {
        return $this->hasMany(LeaveApplicationAction::class)->oldest();
    }

    /** "DELA CRUZ, Juan P." — how 2. NAME is printed. */
    public function getApplicantNameAttribute(): string
    {
        return trim(collect([
            $this->applicant_last_name,
            $this->applicant_first_name,
            $this->applicant_middle_name,
        ])->filter()->implode(' '));
    }

    /**
     * 6.C INCLUSIVE DATES, printed as free text like the paper form.
     * A single-day leave prints one date rather than "X to X".
     */
    public function getInclusiveDatesTextAttribute(): string
    {
        if (! $this->date_from || ! $this->date_to) {
            return '';
        }

        $from = Carbon::parse($this->date_from);
        $to   = Carbon::parse($this->date_to);

        return $from->isSameDay($to)
            ? $from->format('F j, Y')
            : $from->format('F j, Y').' to '.$to->format('F j, Y');
    }

}
