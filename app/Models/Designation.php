<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'erp_id',
        'name',
        'short_name',
        'rank',
        'is_rank',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_rank' => 'boolean',
    ];

    /**
     * Only the rows that are academic grades.
     *
     * "Adjunct Faculty" and "System - Unassigned Designation" live in this table
     * because teachers.designation_id is NOT NULL and somebody has to go
     * somewhere, but neither is a rank the university awards. They are excluded
     * from the public filters, and a teacher holding one is shown by their job
     * type instead — see Teacher::getDesignationTitleAttribute().
     */
    public function scopeRanks(Builder $query): Builder
    {
        return $query->where('is_rank', true);
    }

    /**
     * Get the teachers with this designation.
     */
    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }
}
