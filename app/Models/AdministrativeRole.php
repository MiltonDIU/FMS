<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdministrativeRole extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'short_name',
        'scope',
        'rank',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the teacher administrative role assignments.
     */
    public function teacherAdministrativeRoles(): HasMany
    {
        return $this->hasMany(TeacherAdministrativeRole::class);
    }

    /**
     * Get the users assigned to this administrative role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'administrative_role_user')
            ->withPivot(['department_id', 'faculty_id', 'start_date', 'end_date', 'is_acting', 'is_active', 'remarks', 'assigned_by'])
            ->withTimestamps();
    }
}
