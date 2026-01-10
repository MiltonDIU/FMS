<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Publication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'faculty_id',
        'department_id',
        'publication_type_id',
        'publication_linkage_id',
        'publication_quartile_id',
        'grant_type_id',
        'research_collaboration_id',
        'title',
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
}
