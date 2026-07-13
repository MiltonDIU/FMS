<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'sort_order',
        'is_active',
        
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the teacher profile associated with the user.
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * Get the administrative roles assigned to this user.
     */
    public function administrativeRoles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AdministrativeRole::class, 'administrative_role_user')
            ->withPivot(['department_id', 'faculty_id', 'start_date', 'end_date', 'is_acting', 'is_active', 'remarks', 'assigned_by'])
            ->orderBy('administrative_roles.name')
            ->withTimestamps();
    }

    /**
     * Check if user has a teacher profile.
     */
    public function isTeacher(): bool
    {
        return $this->teacher()->exists();
    }

    /**
     * Determine if the user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // 1. Check if the user is active (applies to ALL roles/users)
        if (!$this->is_active) {
            return false;
        }

        // 2. Super admins and admins bypass other restrictions
        if ($this->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // 3. For teachers, perform detailed checks
        if ($this->isTeacher()) {
            // Check if teacher profile is active (not retired/resigned/suspended etc.)
            if (!$this->teacher?->is_active) {
                return false;
            }

            // Check if their current employment status allows login
            $employmentStatus = $this->teacher?->employmentStatus;
            if ($employmentStatus && !$employmentStatus->allow_login) {
                return false;
            }

            // Check global login mode
            $loginMode = Setting::get('teacher_login_mode', 'individual');
            if ($loginMode === 'disable_all') {
                return false;
            }
            if ($loginMode === 'allow_all') {
                return true;
            }

            // Individual level setting
            return (bool) $this->teacher?->login_allowed;
        }

        // 4. For other roles (registrar, dean, head, etc.) who are not teachers:
        return true;
    }
}
