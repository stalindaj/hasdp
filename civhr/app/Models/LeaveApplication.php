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
        'signature_uploads' => 'array',
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
     * 6.C INCLUSIVE DATES, written the way the office writes them by hand —
     * the month and year said once when they are shared:
     *
     *   one day            21 July 2026
     *   within a month     20-22 July 2026
     *   across months      30 July - 2 August 2026
     *   across years       30 December 2026 - 2 January 2027
     */
    public function getInclusiveDatesTextAttribute(): string
    {
        if (! $this->date_from || ! $this->date_to) {
            return '';
        }

        $from = Carbon::parse($this->date_from);
        $to   = Carbon::parse($this->date_to);

        if ($from->isSameDay($to)) {
            return $from->format('j F Y');
        }

        // Same month and year: only the days differ.
        if ($from->isSameMonth($to, true)) {
            return $from->format('j').'-'.$to->format('j F Y');
        }

        // Same year: repeat the month but say the year once.
        if ($from->year === $to->year) {
            return $from->format('j F').' - '.$to->format('j F Y');
        }

        return $from->format('j F Y').' - '.$to->format('j F Y');
    }

}
