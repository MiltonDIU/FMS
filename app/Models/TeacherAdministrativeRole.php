<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeacherAdministrativeRole extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'administrative_role_id',
        'department_id',
        'faculty_id',
        'start_date',
        'end_date',
        'is_acting',
        'remarks',
        'assigned_by',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_acting' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the teacher that owns this role assignment.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the administrative role.
     */
    public function administrativeRole(): BelongsTo
    {
        return $this->belongsTo(AdministrativeRole::class);
    }

    /**
     * Get the department (if department-scoped).
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the faculty (if faculty-scoped).
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * Get the user who assigned this role.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
