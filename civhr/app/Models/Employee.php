<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Employee extends Model
{
    protected $guarded = [];   // all fields fillable (simple for an admin-managed table)

    protected $casts = [
        'is_civilian'        => 'boolean',
        'date_orig_appt'     => 'date',
        'date_assumption'    => 'date',
        'date_of_birth'      => 'date',
        'date_of_promotion'  => 'date',
        'last_ape_date'      => 'date',
        'credits_accrual_start' => 'date',
        'ape_date_started'   => 'date',
        'ape_date_completed' => 'date',
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    /**
     * "Archived" means the person's login has been deactivated — they drop
     * out of the active rosters (dashboard, balances, employees) but every
     * record they touched stays intact. An employee with no account (e.g. the
     * approving-official signatory) is never archived.
     */
    public function getArchivedAttribute(): bool
    {
        return (bool) ($this->user && ! $this->user->is_active);
    }

    /** Employees whose login is active (or who have no login at all). */
    public function scopeActive($query)
    {
        return $query->whereDoesntHave('user', fn ($u) => $u->where('is_active', false));
    }

    /** Employees whose login has been deactivated. */
    public function scopeArchived($query)
    {
        return $query->whereHas('user', fn ($u) => $u->where('is_active', false));
    }

    public function ipcrRecords()
    {
        return $this->hasMany(IpcrRecord::class);
    }

    public function ldEntries()
    {
        return $this->hasMany(LdEntry::class);
    }

    public function creditEntries()
    {
        return $this->hasMany(LeaveCreditEntry::class);
    }

    /**
     * The rank printed on a signature block — military only. Civilians print
     * nothing there (and therefore no service branch either), whatever text
     * happens to sit in the rank column.
     */
    public function getPrintedRankAttribute(): string
    {
        return $this->is_civilian ? '' : (string) ($this->rank ?? '');
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth
            ? Carbon::parse($this->date_of_birth)->age
            : null;
    }
}