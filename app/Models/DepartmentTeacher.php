<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepartmentTeacher extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'department_teacher';

    protected $fillable = [
        'teacher_id',
        'department_id',
        'job_type_id',
        'sort_order',
        'assigned_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Get the teacher that owns this assignment.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the job type.
     */
    public function jobType(): BelongsTo
    {
        return $this->belongsTo(JobType::class);
    }

    /**
     * Get the user who assigned this.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
