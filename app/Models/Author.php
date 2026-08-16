<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Author extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'scopus_id',
        'author_type_id',
        'is_active',
        'used_our_affiliation',
        'affiliation',
        'merged_into_teacher_id',
        'merged_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'used_our_affiliation' => 'boolean',
        'merged_at' => 'datetime',
    ];

    public function authorType(): BelongsTo
    {
        return $this->belongsTo(AuthorType::class);
    }

    public function publications()
    {
        return $this->morphToMany(Publication::class, 'authorable', 'publication_authors')
            ->withPivot(['author_role', 'sort_order', 'incentive_amount'])
            ->withTimestamps();
    }

    /**
     * The teacher this author turned out to be.
     *
     * Set by merging. Null for everyone who is genuinely external — which is
     * most of them, and the point of keeping the column rather than deleting the
     * row: a name that was dealt with looks different from one that was not.
     */
    public function mergedIntoTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'merged_into_teacher_id');
    }

    /**
     * Scopus author identifiers known to be this person.
     *
     * Several is normal: Scopus splits an author across profiles when the name
     * is written differently.
     */
    public function scopusAuthorIds(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ScopusAuthorId::class, 'authorable');
    }

    /**
     * Records what an export said about where this author was writing from.
     *
     * "Ever" is the rule, and it only travels one way. Somebody who appears
     * once under our name and five times under a former employer's is ours, so
     * a true is never overwritten by a later false — the reverse would let the
     * last paper processed decide who somebody is.
     *
     * The named institution is kept only while the answer is no. Once we know
     * they wrote under our own affiliation, whose address appeared on some
     * other paper is not what the row is for.
     */
    public function recordAffiliationStanding(bool $usedOurs, ?string $namedInstitution = null): void
    {
        if ($this->used_our_affiliation === true) {
            return;
        }

        $this->used_our_affiliation = $usedOurs;
        $this->affiliation = $usedOurs ? null : ($namedInstitution ?: $this->affiliation);

        $this->save();
    }

    /**
     * Authors no export has ever placed at this institution.
     *
     * The ones to leave alone: a co-author at another university belongs in
     * this table permanently and is not a merge waiting to happen.
     */
    public function scopeNeverOurs(Builder $query): Builder
    {
        return $query->where('used_our_affiliation', false);
    }

    /**
     * Authors an export did place here, and who are therefore worth a look.
     *
     * Somebody writing under our own affiliation who is not one of our teachers
     * is either a teacher we failed to match by name, or a student or member of
     * staff who is not in the teachers table at all.
     */
    public function scopePossiblyOurs(Builder $query): Builder
    {
        return $query->where('used_our_affiliation', true)
            ->whereNull('merged_into_teacher_id');
    }

    /**
     * An author we know only by the name printed on a paper.
     *
     * `email` and `author_type_id` are both NOT NULL, and a Scopus export gives
     * us neither, so anything creating an author from one has to invent both or
     * the insert is refused outright — which is exactly what was happening to
     * the Scopus review importers.
     *
     * The address follows the one the original bulk import used for the 1,600
     * authors already here: the name reduced to its letters, at fms.com. Same
     * person arriving by a second route therefore lands on the same row rather
     * than a duplicate, even when the two spellings differ in punctuation.
     */
    public static function createExternal(string $name): self
    {
        $name = trim($name);
        $email = static::placeholderEmail($name);

        $existing = static::withTrashed()->where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        return static::create([
            'name' => $name,
            'email' => $email,
            // GA — a guest author, which is all an export tells us.
            'author_type_id' => AuthorType::query()->where('name', 'GA')->value('id') ?? 2,
            'is_active' => true,
        ]);
    }

    /**
     * The @fms.com address stood in for an author who never gave us one.
     *
     * A name with no letters at all — initials-only, or non-Latin — would
     * otherwise collapse every such author onto one address, so those fall back
     * to something unique instead of something shared.
     */
    public static function placeholderEmail(string $name): string
    {
        $handle = preg_replace('/[^a-z]/', '', strtolower($name));

        return ($handle !== '' ? $handle : 'author' . uniqid()) . '@fms.com';
    }

    public function isMerged(): bool
    {
        return $this->merged_into_teacher_id !== null;
    }

    /** Authors still standing on their own — the ones worth offering or checking. */
    public function scopeNotMerged(Builder $query): Builder
    {
        return $query->whereNull('authors.merged_into_teacher_id');
    }

    public function scopeMerged(Builder $query): Builder
    {
        return $query->whereNotNull('authors.merged_into_teacher_id');
    }

    /**
     * Hands this author's publications to a teacher.
     *
     * Lives on the model rather than in the table action because it rewrites
     * money and ownership, and that deserves to be callable — and testable —
     * without a browser.
     *
     * What it does, per publication the author is credited on:
     *
     *  - if the teacher is not on that paper, the pivot row is retyped, so the
     *    role, the position in the author list and the incentive all carry over
     *    untouched;
     *  - if the teacher is already on it, the two rows are one person, so they
     *    collapse into one. The amounts are **added**: 9,285,030.47 of the
     *    incentive money sits on external authors, and a merge that dropped a
     *    duplicate's share would quietly change what a publication paid out.
     *    The surviving row keeps the earlier of the two roles, first author
     *    outranking corresponding, which outranks co-author.
     *
     * The author row is kept and retired. Deleting it would erase the evidence
     * that these papers were ever filed under another spelling.
     *
     * @return array{publications: int, moved: int, combined: int, amount: float}
     */
    public function mergeInto(Teacher $teacher): array
    {
        $summary = ['publications' => 0, 'moved' => 0, 'combined' => 0, 'amount' => 0.0];

        DB::transaction(function () use ($teacher, &$summary) {
            $rows = DB::table('publication_authors')
                ->where('authorable_type', self::class)
                ->where('authorable_id', $this->id)
                ->get();

            $summary['publications'] = $rows->count();

            foreach ($rows as $row) {
                $summary['amount'] += (float) ($row->incentive_amount ?? 0);

                $existing = DB::table('publication_authors')
                    ->where('publication_id', $row->publication_id)
                    ->where('authorable_type', Teacher::class)
                    ->where('authorable_id', $teacher->id)
                    ->first();

                if ($existing === null) {
                    DB::table('publication_authors')
                        ->where('id', $row->id)
                        ->update([
                            'authorable_type' => Teacher::class,
                            'authorable_id' => $teacher->id,
                            'updated_at' => now(),
                        ]);

                    $summary['moved']++;

                    continue;
                }

                DB::table('publication_authors')
                    ->where('id', $existing->id)
                    ->update([
                        'incentive_amount' => (float) ($existing->incentive_amount ?? 0)
                            + (float) ($row->incentive_amount ?? 0),
                        'author_role' => static::strongerRole($existing->author_role, $row->author_role),
                        'updated_at' => now(),
                    ]);

                DB::table('publication_authors')->where('id', $row->id)->delete();

                $summary['combined']++;
            }

            $this->forceFill([
                'merged_into_teacher_id' => $teacher->id,
                'merged_at' => now(),
                // Retired, so it stops being offered as an author from here on.
                'is_active' => false,
            ])->save();
        });

        return $summary;
    }

    /** First author outranks corresponding, which outranks everything else. */
    protected static function strongerRole(?string $a, ?string $b): string
    {
        $rank = ['first' => 1, 'corresponding' => 2, 'co_author' => 3];

        $rankOf = fn (?string $role) => $rank[$role] ?? 4;

        return $rankOf($a) <= $rankOf($b) ? ($a ?? 'co_author') : ($b ?? 'co_author');
    }
}
