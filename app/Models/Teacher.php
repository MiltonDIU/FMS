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
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[ObservedBy([TeacherObserver::class])]
class Teacher extends Model implements HasMedia
{
    /**
     * Where the legacy system serves teacher photographs from.
     *
     * Was hardcoded in thirteen templates. Once the images move into
     * local storage, this is the line that changes.
     */
    public const PHOTO_BASE_URL = 'https://faculty.daffodilvarsity.edu.bd/images/teacher/';

    use HasFactory, SoftDeletes, InteractsWithMedia, Notifiable;

    protected $fillable = [
        'user_id',
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
        'profile_status',
        'verification_status',
        'verification_token',
        'verified_at',
        'is_public',
        'is_active',
        'login_allowed',
        'employment_status_id',
        'job_type_id',
        'is_archived',
        'sort_order',
        'profile_score',
        'profile_score_synced_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joining_date' => 'date',
        'verified_at' => 'datetime',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'login_allowed' => 'boolean',
        'is_archived'              => 'boolean',
        'profile_score'            => 'integer',
        'profile_score_synced_at'  => 'datetime',
        // Activation link lifecycle. These are compared with isPast() and
        // checked for null, so they have to arrive as dates rather than strings.
        'verification_token_expires_at' => 'datetime',
        'verification_token_used_at'    => 'datetime',
        'activation_email_sent_at'      => 'datetime',
    ];

    /**
     * Route notifications for the mail channel.
     */
    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->email ?? $this->user?->email;
    }

    /**
     * Check if teacher profile is verified.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /**
     * Mark profile as verified.
     */
    public function markAsVerified(): void
    {
        \App\Services\TeacherVersionService::$ignoreObserver = true;

        $this->update([
            'verification_status' => 'verified',
            'verified_at'         => now(),
            'is_public'           => true,
        ]);

        \App\Services\TeacherVersionService::$ignoreObserver = false;
    }

    /**
     * Set the teacher's employee ID, automatically trimming any spaces.
     */
    public function setEmployeeIdAttribute($value)
    {
        $this->attributes['employee_id'] = $value !== null ? trim($value) : null;
    }

    /**
     * Accessor for photo attribute.
     * Returns photo column value if set, or fallback to Spatie media library URL for avatar.
     */
    public function getPhotoAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->exists) {
            $mediaUrl = $this->getFirstMediaUrl('avatar');
            return !empty($mediaUrl) ? $mediaUrl : null;
        }

        return null;
    }

    /**
     * Where the teacher's photograph can actually be fetched from, or null.
     *
     * `photo` cannot be used directly in an <img> tag. It holds three different
     * kinds of value — a bare filename from the legacy import, an absolute URL
     * from the media library, or the registered fallback path
     * "/images/default-avatar.png" — and every view was prefixing all three with
     * the external image host, producing addresses like
     * ".../images/teacher//images/default-avatar.png".
     *
     * Because the fallback is never empty, `@if($teacher->photo)` was also
     * always true, so the initials placeholders behind those checks never
     * appeared. This returns null for the fallback so they can, which matters
     * for the 139 teachers who have no picture — and the fallback file does not
     * exist in public/ anyway, so it only ever rendered a broken image.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        $photo = $this->photo;

        if (blank($photo)) {
            return null;
        }

        if (Str::startsWith($photo, ['http://', 'https://'])) {
            return $photo;
        }

        // A rooted path is the media-library fallback rather than a real
        // photograph, and the file it names is not present.
        if (Str::startsWith($photo, '/')) {
            return null;
        }

        return self::PHOTO_BASE_URL . rawurlencode($photo);
    }

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
        $name = collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter(fn ($part) => filled($part))
            ->implode(' ');

        return $name
            ?: $this->user?->name
            ?: $this->user?->email
            ?: $this->employee_id
            ?: 'Teacher';
    }

    /**
     * Determine whether the teacher holds an administrative designation
     * (dean, head, chairman, director, coordinator, advisor).
     */
    public function getIsAdministrativeAttribute(): bool
    {
        $designation = optional($this->designation)->name;

        return $designation
            ? (bool) preg_match('/(dean|head|chairman|director|coordinator|advisor)/i', $designation)
            : false;
    }

    /**
     * Get the research interests as a clean, trimmed array
     * (parsed from the comma-separated source string).
     */
    public function getResearchInterestsAttribute(): array
    {
        if (!$this->research_interest) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $this->research_interest))
        ));
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

    public function maritalStatus(): BelongsTo
    {
        return $this->belongsTo(MaritalStatus::class);
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

    /** Words stored in the name fields that are titles, not names. */
    protected const HONORIFICS = ['professor', 'prof', 'dr', 'md', 'mohammad', 'mohammed', 'mr', 'mrs', 'ms', 'miss', 'engr', 'engineer'];

    /**
     * Initials for the block shown when someone has no photograph.
     *
     * Taken from the first real name words, because titles are stored in the
     * name fields: "Professor Dr. Md. Asif Nazrul" is first_name "Professor Dr.
     * Md. Asif" and last_name "Nazrul", so the obvious first-letter-of-each gave
     * "PN". Falls back to whatever is there if a name is nothing but titles.
     */
    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim(
            $this->full_name ?: "{$this->first_name} {$this->middle_name} {$this->last_name}"
        ), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $names = array_values(array_filter(
            $words,
            fn ($word) => ! in_array(mb_strtolower(rtrim($word, '.')), self::HONORIFICS, true)
        ));

        $names = $names ?: $words;

        $first = mb_substr($names[0] ?? '', 0, 1);
        $last = count($names) > 1 ? mb_substr(end($names), 0, 1) : '';

        return mb_strtoupper($first . $last);
    }

    /**
     * The employment status the public needs told about, or null when there is
     * nothing to say.
     *
     * Statuses split three ways. Those with check_active = 0 (retired,
     * resigned, terminated) take the teacher off the site altogether, so they
     * never reach a page. "Active" needs no announcement. What is left is the
     * middle group — On Leave, Study Leave, Deputation — and that group is not
     * small: of the teachers the directory currently shows, 219 are on study
     * leave and 31 on ordinary leave. Showing them exactly like everyone else
     * tells a visitor they are at their desk, which is the wrong message; a
     * student emails and hears nothing back for a year.
     *
     * `slug` decides, not `name`, because names get edited in the admin panel.
     *
     * @return array{label: string, note: string|null, tone: string}|null
     */
    public function getPublicStatusAttribute(): ?array
    {
        $status = $this->employmentStatus;

        if (! $status || $status->slug === 'active') {
            return null;
        }

        return [
            'label' => $status->name,
            'note' => $status->description ?: null,
            'tone' => $status->color ?: 'gray',
        ];
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
            ->withPivot(['author_role', 'sort_order', 'incentive_amount'])
            ->orderBy('publications.sort_order')
            ->withTimestamps();
    }

    /**
     * Get total publication incentives for this teacher.
     */
    public function getTotalPublicationIncentivesAttribute(): float
    {
        return $this->publications()
            ->get()
            ->sum(fn($pub) => (float) ($pub->pivot->incentive_amount ?? 0));
    }

    /**
     * Get the research projects for the teacher.
     */
    public function researchProjects(): HasMany
    {
        return $this->hasMany(ResearchProject::class)->orderBy('sort_order');
    }

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
     * Get the teacher's administrative role assignments (via the user).
     */
    public function administrativeRoles(): HasMany
    {
        return $this->hasMany(UserAdministrativeRole::class, 'user_id', 'user_id');
    }

    /**
     * Get the departments this teacher is assigned to (via pivot).
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_teacher')
            ->withPivot(['job_type_id', 'sort_order', 'assigned_by'])
            ->withTimestamps();
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
            ->useDisk('public')
            ->singleFile()
            ->useFallbackUrl('/images/default-avatar.png')
            ->useFallbackPath(public_path('/images/default-avatar.png'));

        $this->addMediaCollection('documents')
            ->useDisk('public');

        $this->addMediaCollection('certificates')
            ->useDisk('public');
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
