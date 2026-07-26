<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'employee_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function roles()
    {
        return $this->belongsToMany(\App\Models\Role::class);
    }

    public function hasRole(string $name): bool
    {
        return $this->roles()->where('name', $name)->exists();
    }

    /**
     * The parts of this person's printed signature block:
     *
     *              STALIN JOSEPH BAGUIO      <- name, centred above the rule
     *     TSG  ─────────────────────  PAF    <- rank left, branch right
     *
     * The branch suffix only applies to ranked personnel, so civilian staff
     * print as a plain name with nothing flanking it. Falls back to the
     * account name when no employee record is linked.
     */
    public function signatoryBlock(): array
    {
        $employee = $this->employee;

        if (! $employee) {
            return ['rank' => '', 'name' => strtoupper($this->name), 'branch' => '', 'position' => '', 'designation' => ''];
        }

        $name = trim(implode(' ', array_filter([
            $employee->first_name,
            $employee->middle_name,
            $employee->last_name,
            $employee->suffix,
        ])));

        // Civilians print neither rank nor service branch.
        $rank = $employee->printed_rank;

        return [
            'rank'        => $rank,
            'name'        => strtoupper($name !== '' ? $name : $this->name),
            'branch'      => $rank !== '' ? (string) config('agency.branch_suffix') : '',
            'position'    => (string) ($employee->position ?? ''),
            'designation' => (string) ($employee->designation ?? ''),
        ];
    }

    /** The same block flattened onto one line, for dropdowns and lists. */
    public function signatoryLabel(): string
    {
        $block = $this->signatoryBlock();

        return trim(implode(' ', array_filter([
            $block['rank'],
            $block['name'],
            $block['branch'],
        ])));
    }
}