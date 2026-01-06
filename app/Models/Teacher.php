<?php

namespace App\Models;

use App\Observers\TeacherObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[ObservedBy([TeacherObserver::class])]
class Teacher extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'department_id',
        'designation_id',
        'employee_id',
        'webpage',
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'extension_no',
        'personal_phone',
        'secondary_email',
        'date_of_birth',
        'gender_id',
        'blood_group_id',
        'country_id',
        'religion_id',
        'present_address',
        'permanent_address',
        'joining_date',
        'work_location',
        'office_room',
        'photo',
        'bio',
        'research_interest',
        'personal_website',
        'google_scholar',
        'research_gate',
        'orcid',
        'profile_status',
        'is_public',
        'is_active',
        'is_active',
        'employment_status_id',
        'job_type_id',
        'is_archived',
        'sort_order',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joining_date' => 'date',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
    ];

    /**
     * Scope: Only active (non-archived) teachers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope: Only archived teachers.
     */
    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    /**
     * Get the full name of the teacher.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    /**
     * Get the user that owns the teacher profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the department that the teacher belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the designation of the teacher.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }

    public function bloodGroup(): BelongsTo
    {
        return $this->belongsTo(BloodGroup::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religion::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }

    public function jobType(): BelongsTo
    {
        return $this->belongsTo(JobType::class);
    }

    /**
     * Get the educations for the teacher.
     */
    public function educations(): HasMany
    {
        return $this->hasMany(Education::class)->orderBy('sort_order');
    }

    /**
     * Get the publications for the teacher.
     */
    public function publications(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(Publication::class, 'authorable', 'publication_authors')
            ->withPivot(['author_role', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Get the research projects for the teacher.
     */
//    public function researchProjects(): HasMany
//    {
//        return $this->hasMany(ResearchProject::class)->orderBy('sort_order');
//    }

    /**
     * Get the training experiences for the teacher.
     */
    public function trainingExperiences(): HasMany
    {
        return $this->hasMany(TrainingExperience::class)->orderBy('sort_order');
    }

    /**
     * Get the certifications for the teacher.
     */
    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class)->orderBy('sort_order');
    }

    /**
     * Get the skills for the teacher.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class)->orderBy('sort_order');
    }

    /**
     * Get the teaching areas for the teacher.
     */
    public function teachingAreas(): HasMany
    {
        return $this->hasMany(TeachingArea::class)->orderBy('sort_order');
    }

    /**
     * Get the memberships for the teacher.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->orderBy('sort_order');
    }

    /**
     * Get the awards for the teacher.
     */
    public function awards(): HasMany
    {
        return $this->hasMany(Award::class)->orderBy('sort_order');
    }

    /**
     * Get the job experiences for the teacher.
     */
    public function jobExperiences(): HasMany
    {
        return $this->hasMany(JobExperience::class)->orderBy('sort_order');
    }

    /**
     * Get the social links for the teacher.
     */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(SocialLink::class)->orderBy('sort_order');
    }

    /**
     * Get the teacher administrative role assignments.
     */
    public function teacherAdministrativeRoles(): HasMany
    {
        return $this->hasMany(TeacherAdministrativeRole::class);
    }

    /**
     * Get the administrative roles assigned to this teacher.
     */
    public function administrativeRoles(): BelongsToMany
    {
        return $this->belongsToMany(AdministrativeRole::class, 'teacher_administrative_roles')
            ->withPivot(['department_id', 'faculty_id', 'start_date', 'end_date', 'is_acting', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Get the current active administrative roles.
     */
    public function activeAdministrativeRoles(): BelongsToMany
    {
        return $this->administrativeRoles()->wherePivot('is_active', true)->wherePivotNull('end_date');
    }

    /**
     * Get the versions for the teacher.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TeacherVersion::class)->orderByDesc('version_number');
    }

    /**
     * Get the current active version.
     */
    public function activeVersion()
    {
        return $this->hasOne(TeacherVersion::class)->where('is_active', true);
    }

    /**
     * Register media collections for the teacher.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->useFallbackUrl('/images/default-avatar.png')
            ->useFallbackPath(public_path('/images/default-avatar.png'));

        $this->addMediaCollection('documents');

        $this->addMediaCollection('certificates');
    }

    /**
     * Register media conversions.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->sharpen(10)
            ->performOnCollections('avatar');

        $this->addMediaConversion('medium')
            ->width(300)
            ->height(300)
            ->performOnCollections('avatar');
    }
}
