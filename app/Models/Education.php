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
        'degree_type_id',
        'country_id',
        'result_type_id',
        'educational_institution_id',
        'major_id',
        'major',
        'institution',
        'passing_year',
        'duration',
        'cgpa',
        'scale',
        'marks',
        'grade',
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

    public function degreeType(): BelongsTo
    {
        return $this->belongsTo(DegreeType::class);
    }

    public function degreeLevel(): BelongsTo
    {
        return $this->belongsTo(DegreeLevel::class);
    }

    public function resultType(): BelongsTo
    {
        return $this->belongsTo(ResultType::class);
    }

    public function educationalInstitution(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'educational_institution_id');
    }

    public function majorRelation(): BelongsTo
    {
        return $this->belongsTo(Major::class, 'major_id');
    }
}
