<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Education extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'educations';

    protected $fillable = [
        'teacher_id',
        'level_of_education',
        'degree',
        'field_of_study',
        'institution',
        'board',
        'country_id',
        'passing_year',
        'duration',
        'result_type',
        'cgpa',
        'scale',
        'marks',
        'thesis_title',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'cgpa' => 'decimal:2',
        'scale' => 'decimal:1',
        'marks' => 'decimal:2',
    ];

    /**
     * Get the teacher that owns the education.
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
