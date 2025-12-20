<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobExperience extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'position',
        'organization',
        'department',
        'location',
        'country',
        'start_date',
        'end_date',
        'is_current',
        'responsibilities',
        'source',
        'source_reference_id',
        'sort_order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    /**
     * Get the teacher that owns the job experience.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Check if this experience is system-generated (from admin role).
     */
    public function isSystemGenerated(): bool
    {
        return $this->source === 'system';
    }
}
