<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'publication_date' => 'date',
        'citescore' => 'decimal:2',
        'impact_factor' => 'decimal:2',
    ];

    public function teachers()
    {
        return $this->morphedByMany(Teacher::class, 'authorable', 'publication_authors')
            ->withPivot(['author_role', 'sort_order', 'incentive_amount'])
            ->withTimestamps();
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
