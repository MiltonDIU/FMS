<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One area a teacher is an expert in, as the Directorate of Research lists it.
 *
 * Not a research interest: those come from the teacher's own profile on the old
 * site. These come from researchers.json through import:researcher-profiles.
 */
class AreaOfExpertise extends Model
{
    use SoftDeletes;

    protected $table = 'area_of_expertises';

    protected $fillable = [
        'teacher_id',
        'expertise',
        'description',
        'sort_order',
    ];

    /**
     * Get the teacher that owns the area of expertise.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
