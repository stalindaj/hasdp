<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LdEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date'       => 'date',
        'date_to'    => 'date',
        'hours'      => 'decimal:1',
        'decided_at' => 'datetime',
    ];

    /**
     * The L&D tracks an office plans against: the foundation everyone gets,
     * the job's own technical skills, and the two supervisory rungs above
     * them. "Other" keeps a training recordable when it fits none of them.
     */
    public const COMPETENCIES = [
        'foundational' => 'Foundational',
        'technical'    => 'Technical',
        'supervisory'  => 'Supervisory',
        'managerial'   => 'Managerial / Leadership',
        'other'        => 'Other',
    ];

    /**
     * What a row is: an L&D training (hours, toward the target) or a local
     * career course / seminar — filed and approved the same way, but reported
     * on its own (Table 2) and never counted as training hours.
     */
    public const TRAINING = 'training';
    public const COURSE   = 'career_course';

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

    public function isCourse(): bool
    {
        return $this->kind === self::COURSE;
    }

    public function competencyLabel(): string
    {
        return self::COMPETENCIES[$this->competency] ?? '';
    }

    /**
     * The dates the way the office writes them: "02 October 2026" for one
     * day, "29 September - 01 October 2026" across a range.
     */
    public function getInclusiveDatesTextAttribute(): string
    {
        if (! $this->date) {
            return '';
        }

        $to = $this->date_to;

        if (! $to || $to->isSameDay($this->date)) {
            return $this->date->format('d F Y');
        }

        if ($to->isSameMonth($this->date, true)) {
            return $this->date->format('d').'-'.$to->format('d F Y');
        }

        return $this->date->year === $to->year
            ? $this->date->format('d F').' - '.$to->format('d F Y')
            : $this->date->format('d F Y').' - '.$to->format('d F Y');
    }

    /** Only approved hours count toward the yearly target. */
    public function scopeApproved($query)
    {
        return $query->where('status', self::APPROVED);
    }
}
