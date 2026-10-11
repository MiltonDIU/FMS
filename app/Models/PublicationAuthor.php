<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row of publication_authors: a person on a paper, in one role.
 *
 * The relations on Teacher and Publication read these as a pivot, and the
 * forms write them through App\Support\PublicationAuthorship. This model exists
 * for the screens that list the rows themselves — who put a teacher on which
 * paper, and when — and is read, not written, through it.
 */
class PublicationAuthor extends Model
{
    protected $table = 'publication_authors';

    protected $guarded = ['*'];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'answered_at' => 'datetime',
        'used_our_affiliation' => 'boolean',
    ];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function authorable(): MorphTo
    {
        return $this->morphTo();
    }
}
