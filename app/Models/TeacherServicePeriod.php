<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One period of a teacher's service at the university: when they joined,
 * and — once it has ended — when they left and why. A teacher who left and
 * came back has more than one.
 *
 * Kept in step with the profile by App\Support\TeacherServicePeriods.
 */
class TeacherServicePeriod extends Model
{
    protected $fillable = [
        'teacher_id',
        'joined_on',
        'left_on',
        'ended',
        'end_reason',
        'remarks',
        'source',
        'created_by',
    ];

    protected $casts = [
        'joined_on' => 'date',
        'left_on' => 'date',
        'ended' => 'boolean',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
