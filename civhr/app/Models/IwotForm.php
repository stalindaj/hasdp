<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An IWOT sheet — the targets an employee commits to at the start of a rating
 * period. It is the same matrix the IPCR later rates against, signed by the
 * employee ("Prepared by") and the NCOIC ("Approved by").
 */
class IwotForm extends Model
{
    protected $guarded = [];

    protected $casts = [
        'signature_uploads' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const APPROVED = 'approved';
    public const RETURNED = 'returned';

    /** The blocks that can carry an e-signature image on the printed sheet. */
    public const SIGNATURE_SLOTS = ['prepared', 'approved'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(IwotFormGroup::class)->orderBy('sort_order');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(IwotFormRow::class);
    }
}
