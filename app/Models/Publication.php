<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Publication extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::saving(function (Publication $publication) {
            if (empty($publication->slug) || $publication->isDirty('title')) {
                $publication->slug = Str::slug((string) $publication->title) ?: 'publication';
            }
        });

        static::creating(function (Publication $publication) {
            if (Auth::check() && empty($publication->created_by)) {
                $publication->created_by = Auth::id();
            }
        });
    }

    protected $fillable = [
        'faculty_id',
        'department_id',
        'publication_type_id',
        'publication_linkage_id',
        'publication_quartile_id',
        'grant_type_id',
        'research_collaboration_id',
        'title',
        'slug',
        'journal_name',
        'journal_link',
        'doi',
        'scopus_eid',
        'publication_date',
        'publication_year',
        'research_area',
        'h_index',
        'citescore',
        'impact_factor',
        'student_involvement',
        'keywords',
        'abstract',
        'status',
        'is_featured',
        'sort_order',
        'come_from_old_site',
        'come_from_pd',
        'created_by',
    ];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    protected $casts = [
        'student_involvement' => 'boolean',
        'is_featured' => 'boolean',
        'come_from_pd' => 'boolean',
        'come_from_old_site' => 'boolean',
        'publication_date' => 'date',
        'citescore' => 'decimal:2',
        'impact_factor' => 'decimal:2',
    ];

    /**
     * The pivot columns every reader of an authorship needs.
     *
     * `affiliation` and `used_our_affiliation` were being written by the import
     * and read by nobody, because they were never listed here — so the question
     * the columns exist to answer, which of this paper's authors wrote it as one
     * of ours, could not be asked through the relation at all.
     */
    protected const AUTHOR_PIVOT = ['author_role', 'sort_order', 'incentive_amount', 'affiliation', 'used_our_affiliation'];

    public function teachers()
    {
        return $this->morphedByMany(Teacher::class, 'authorable', 'publication_authors')
            ->withPivot(self::AUTHOR_PIVOT)
            ->withTimestamps();
    }

    public function externalAuthors()
    {
        return $this->morphedByMany(Author::class, 'authorable', 'publication_authors')
            ->withPivot(self::AUTHOR_PIVOT)
            ->withTimestamps();
    }

    /**
     * The authors this paper can be credited to the university by.
     *
     * Teachers who carried our own affiliation on this paper. Not "our teachers
     * who are on it": somebody who joined last year has papers written under a
     * previous employer, and those are that employer's output, not ours.
     *
     * Rows nothing has established are excluded, which is the strict reading —
     * an import that never recorded an affiliation cannot be taken as evidence
     * of one. The backfill command is what turns those from null into an answer.
     */
    public function ourTeachers()
    {
        return $this->teachers()->wherePivot('used_our_affiliation', true);
    }

    /**
     * The paper's byline, in the order it was published in.
     *
     * One entry per person, which is the part that needs saying: somebody who is
     * both the first-listed author and the corresponding one holds two rows,
     * because author_role is a single enum and neither fact can be dropped. On a
     * page they are one author with two things true of them, so the rows are
     * collapsed back here rather than printing the same name twice.
     *
     * @return \Illuminate\Support\Collection<int, array{
     *     name: string, roles: array<int, string>, is_ours: bool,
     *     used_our_affiliation: ?bool, affiliation: ?string, teacher: ?Teacher, url: ?string
     * }>
     */
    public function byline(): \Illuminate\Support\Collection
    {
        $this->loadMissing(['teachers.department.faculty', 'externalAuthors']);

        $entries = collect();

        foreach ($this->teachers as $teacher) {
            $entries->push([
                'key' => 'T:' . $teacher->id,
                'name' => $teacher->full_name,
                'pivot' => $teacher->pivot,
                'teacher' => $teacher,
            ]);
        }

        foreach ($this->externalAuthors as $author) {
            $entries->push([
                'key' => 'A:' . $author->id,
                'name' => $author->name,
                'pivot' => $author->pivot,
                'teacher' => null,
            ]);
        }

        return $entries
            ->groupBy('key')
            ->map(function ($rows) {
                /*
                 * The strongest claim among this person's rows decides where
                 * they sit, and the earliest sort_order decides the order — a
                 * corresponding row written later carries the same position.
                 */
                $roles = $rows->pluck('pivot.author_role')->unique()->values();
                $first = $rows->first();

                $affiliation = $rows->pluck('pivot.affiliation')->filter()->first();
                $standing = $rows->pluck('pivot.used_our_affiliation')
                    ->reject(fn ($value) => $value === null)
                    ->map(fn ($value) => (bool) $value)
                    ->sort()
                    ->last();

                return [
                    'name' => $first['name'],
                    'roles' => $roles->sortBy(fn (string $role) => match ($role) {
                        'first' => 0,
                        'corresponding' => 1,
                        default => 2,
                    })->values()->all(),
                    'teacher' => $first['teacher'],
                    // Ours on this paper: one of our teachers who carried our
                    // own affiliation on it. A teacher who published under a
                    // previous employer is on the paper but not on our count.
                    'is_ours' => $first['teacher'] !== null && $standing === true,
                    'used_our_affiliation' => $standing,
                    'affiliation' => $affiliation,
                    'url' => $first['teacher'] ? \App\Helpers\Seo::teacherUrl($first['teacher']) : null,
                    'sort_order' => (int) $rows->min('pivot.sort_order'),
                    'rank' => $roles->contains('first') ? 0 : ($roles->contains('corresponding') ? 1 : 2),
                ];
            })
            ->sortBy([['sort_order', 'asc'], ['rank', 'asc']])
            ->values();
    }


    /**
     * Get the publication incentive.
     */
    public function incentive(): HasOne
    {
        return $this->hasOne(PublicationIncentive::class);
    }

    /**
     * Check if incentive is assigned.
     */
    public function hasIncentive(): bool
    {
        return $this->incentive()->exists();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PublicationType::class, 'publication_type_id');
    }

    public function linkage(): BelongsTo
    {
        return $this->belongsTo(PublicationLinkage::class, 'publication_linkage_id');
    }

    public function quartile(): BelongsTo
    {
        return $this->belongsTo(PublicationQuartile::class, 'publication_quartile_id');
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(GrantType::class, 'grant_type_id');
    }

    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(ResearchCollaboration::class, 'research_collaboration_id');
    }

    /**
     * Get the user who created this publication.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get creator name or fallback.
     */
    public function getCreatedByNameAttribute(): string
    {
        return $this->creator?->name ?? 'System Generated';
    }

    /**
     * Publications that fall inside a date range, including the ones that only
     * know their year.
     *
     * Of 17,510 publications, 1,465 carry an exact publication_date and 17,003
     * carry a publication_year. That is not an import that went wrong — the
     * source export holds a Publication Date for 1,465 of its 12,423 rows, and
     * every one of them was imported. The year is simply all that was ever
     * recorded for the rest.
     *
     * So a filter that reads publication_date alone answers "papers we happen to
     * hold a day for", not "papers published between these dates", and quietly
     * drops most of the library. This matches on the exact date where there is
     * one and falls back to the year where there is not.
     *
     * Either bound may be null for an open-ended range.
     */
    public function scopePublishedBetween(Builder $query, mixed $from = null, mixed $until = null): Builder
    {
        if (blank($from) && blank($until)) {
            return $query;
        }

        $from = filled($from) ? Carbon::parse($from)->startOfDay() : null;
        $until = filled($until) ? Carbon::parse($until)->endOfDay() : null;

        return $query->where(function (Builder $outer) use ($from, $until) {
            $outer
                ->where(function (Builder $exact) use ($from, $until) {
                    $exact->whereNotNull('publication_date')
                        ->when($from, fn (Builder $q) => $q->where('publication_date', '>=', $from))
                        ->when($until, fn (Builder $q) => $q->where('publication_date', '<=', $until));
                })
                ->orWhere(function (Builder $yearOnly) use ($from, $until) {
                    // A year-only record counts as inside the range when its year
                    // overlaps it at all — the day it was published is unknown,
                    // so excluding it would be a guess in the other direction.
                    $yearOnly->whereNull('publication_date')
                        ->whereNotNull('publication_year')
                        ->when($from, fn (Builder $q) => $q->where('publication_year', '>=', $from->year))
                        ->when($until, fn (Builder $q) => $q->where('publication_year', '<=', $until->year));
                });
        });
    }

    /**
     * Build scholarly citations (APA, IEEE, BibTeX) for this publication.
     *
     * @return array{apa: string, ieee: string, bibtex: string}
     */
    public function citations(string $authors): array
    {
        $authors = trim($authors);
        $year = $this->publication_year ?? 'n.d.';
        $venue = $this->journal_name ?? '';
        $title = $this->title;

        return [
            'apa' => "{$authors} ({$year}). {$title}. {$venue}.",
            'ieee' => "[1] {$authors}, \"{$title},\" {$venue}, {$year}.",
            'bibtex' => "@article{diu_{$this->id},\n  author = {{$authors}},\n  title = {{$title}},\n  journal = {{$venue}},\n  year = {{$year}}\n}",
        ];
    }
}
