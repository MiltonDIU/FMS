<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResearchProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'title',
        'description',
        'project_leader',
        'funding_agency',
        'funding_agency_organization_id',
        'budget',
        'currency',
        'role',
        'start_date',
        'end_date',
        'status',
        'outcome',
        'sort_order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->funding_agency_organization_id) {
                $model->funding_agency = \App\Models\Organization::find($model->funding_agency_organization_id)?->name;
            }
        });
    }

    /**
     * Get the teacher that owns the research project.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function fundingAgencyOrganizationRelation(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'funding_agency_organization_id');
    }
}
