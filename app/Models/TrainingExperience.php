<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingExperience extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'title',
        'organization',
        'category',
        'duration_days',
        'completion_date',
        'year',
        'country_id',
        'certificate_url',
        'is_online',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'completion_date' => 'date',
        'is_online' => 'boolean',
    ];

    /**
     * Get the teacher that owns the training experience.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
