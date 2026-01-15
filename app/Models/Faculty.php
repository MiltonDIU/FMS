<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faculty extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'erp_id',
        'name',
        'short_name',
        'code',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the departments for the faculty.
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Get all teachers for the faculty through departments.
     */
    public function teachers(): HasManyThrough
    {
        return $this->hasManyThrough(Teacher::class, Department::class);
    }

    /**
     * Get the teacher administrative roles scoped to this faculty.
     */
    public function teacherAdministrativeRoles(): HasMany
    {
        return $this->hasMany(TeacherAdministrativeRole::class);
    }
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }
}
