<?php

namespace App\Models\Concerns;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * created_by as a users id, filled from whoever is signed in — as on
 * publications.
 *
 * For the lookups teachers add from their profile: majors, positions,
 * organizations. They held a teachers id, so a name added by an admin or the
 * research team recorded nobody; now anyone who adds one is recorded.
 */
trait RecordsCreator
{
    protected static function bootRecordsCreator(): void
    {
        static::creating(function (self $record): void {
            $record->created_by ??= auth()->id();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who to record as adding a name for $teacherId: whoever is signed in, or —
     * for an import run from the command line, with nobody signed in — that
     * teacher's own account.
     */
    protected static function creatorUserFor(?int $teacherId): ?int
    {
        if (auth()->check()) {
            return auth()->id();
        }

        return $teacherId ? Teacher::whereKey($teacherId)->value('user_id') : null;
    }
}
