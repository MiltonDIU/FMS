<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * One Scopus author identifier, against the person it belongs to.
 *
 * Several rows per person is the normal case, not the exception — Scopus splits
 * an author across profiles when a name is written differently or an
 * affiliation changes, and 2,057 of the names in one export carry more than one.
 *
 * The identifier itself is unique: it names exactly one profile, so two people
 * cannot both claim it. That constraint is the point of the table.
 */
class ScopusAuthorId extends Model
{
    public const SOURCE_REVIEW = 'review';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'scopus_author_id',
        'authorable_type',
        'authorable_id',
        'source',
        'recorded_by',
    ];

    /**
     * Keep the owner's own `scopus_id` column pointing at something real.
     *
     * That column carries one identifier for a person who may have several, and
     * it exists to be shown and searched rather than to be the record — this
     * table is the record. Maintaining it here means every route that binds an
     * identifier keeps it right: a review, a workbook, a hand-typed correction.
     *
     * The first identifier bound wins and later ones do not displace it, so the
     * profile a teacher is listed under does not change under them each time an
     * export turns up another spelling of their name. Removing the one on the
     * column hands the place to whichever is left.
     */
    protected static function booted(): void
    {
        static::created(function (self $identifier) {
            $owner = $identifier->authorable;

            if ($owner !== null && blank($owner->scopus_id)) {
                $owner->forceFill(['scopus_id' => $identifier->scopus_author_id])->save();
            }
        });

        static::deleted(function (self $identifier) {
            $owner = $identifier->authorable;

            if ($owner === null || $owner->scopus_id !== $identifier->scopus_author_id) {
                return;
            }

            $owner->forceFill([
                'scopus_id' => static::query()
                    ->where('authorable_type', $identifier->authorable_type)
                    ->where('authorable_id', $identifier->authorable_id)
                    ->orderBy('id')
                    ->value('scopus_author_id'),
            ])->save();
        });
    }

    public function authorable(): MorphTo
    {
        return $this->morphTo();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Binds an identifier to somebody, without taking it off anybody else.
     *
     * The identifier is unique by design — it names one Scopus profile, so two
     * people cannot both hold it — which means a blind create is an exception
     * waiting for the first export that disagrees with a binding we already
     * made. That is not a rare case either: the matcher's whole job is deciding
     * which of several similar names a paper belongs to, and it is allowed to
     * be wrong.
     *
     * So an identifier already recorded stays where it is. Whoever bound it
     * first did so with a reviewer's decision or an earlier run behind them,
     * and silently moving it would rewrite that without telling anybody.
     *
     * @param  Model&object{id: int}  $owner  a Teacher or an Author
     * @return bool  whether this call is what bound it
     */
    public static function bindTo(Model $owner, ?string $scopusAuthorId, string $source = self::SOURCE_REVIEW, ?int $recordedBy = null): bool
    {
        $scopusAuthorId = trim((string) $scopusAuthorId);

        if ($scopusAuthorId === '') {
            return false;
        }

        $existing = static::query()->where('scopus_author_id', $scopusAuthorId)->first();

        if ($existing !== null) {
            // Already ours, or already somebody else's. Either way, untouched.
            return false;
        }

        static::create([
            'scopus_author_id' => $scopusAuthorId,
            'authorable_type' => $owner->getMorphClass(),
            'authorable_id' => $owner->getKey(),
            'source' => $source,
            'recorded_by' => $recordedBy,
        ]);

        return true;
    }

    /** Who currently holds this identifier, if anybody. */
    public static function ownerOf(?string $scopusAuthorId): ?Model
    {
        $scopusAuthorId = trim((string) $scopusAuthorId);

        if ($scopusAuthorId === '') {
            return null;
        }

        return static::query()->where('scopus_author_id', $scopusAuthorId)->first()?->authorable;
    }
}
