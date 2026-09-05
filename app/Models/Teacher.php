<?php

namespace App\Models;

use App\Helpers\OutboundUrl;
use App\Observers\TeacherObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Constraint;
use Spatie\Image\Enums\Fit;
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
        'scopus_id',
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
        'profile_status',
        'verification_status',
        'verification_token',
        'verified_at',
        'is_public',
        // In the Directorate of Research's directory, and so served by the API
        // the research site reads. Set by import:researcher-profiles for every
        // teacher it matches, and editable from the teachers table afterwards.
        'is_researcher',
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
        'is_researcher' => 'boolean',
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
     * The teacher's photograph, served from our own storage.
     *
     * The one address any page should use. Four views were each working it out
     * for themselves and each getting it wrong in a different way — one pasted
     * the legacy filename onto the external host, one ran it through
     * Storage::url() as though it were a local path, one fell back to
     * ui-avatars.com, and the fourth did two of those in sequence.
     *
     * Nothing here reaches outside any more. teachers:download-photos fetched
     * the pictures off the old faculty site into the avatar collection, and
     * that site is going away — an <img> still pointing at it is a photograph
     * that works until somebody else turns their server off.
     *
     * Null when there is none, so the initials placeholder behind
     * `@if($teacher->photo_url)` can show. It matters for the teachers who have
     * no picture at all, and it is why the media library is asked for its file
     * rather than its URL: the collection registers a fallback URL, so asking
     * for the URL never returns nothing and the placeholder never appeared.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if ($this->exists && ($media = $this->getFirstMedia('avatar')) !== null) {
            /*
             * The 600px copy, not the master: a directory page shows two dozen
             * faces at once and the original is whatever the photographer
             * delivered.
             *
             * getAvailableUrl rather than getUrl('profile'), because getUrl
             * builds an address whether or not the file behind it exists. Of
             * 1,861 photographs on file only 18 have been through the current
             * conversions — the rest were imported before these existed — so
             * naming the conversion directly would answer 1,843 profiles with a
             * broken image instead of the picture sitting right there.
             * Regenerating them is a command, not a prerequisite.
             */
            return $media->getAvailableUrl(['profile']);
        }

        // Uploads made before the photographs moved wrote the media URL into
        // the column itself. Still ours, still local — just stored elsewhere.
        $photo = $this->getRawOriginal('photo');

        return filled($photo) && Str::startsWith($photo, ['http://', 'https://'])
            ? $photo
            : null;
    }

    /**
     * The same photograph, for somewhere small.
     *
     * A byline strip draws a face at 3.75rem and the department contact list at
     * 3.25rem; handing those the 600px copy is eight times the pixels anyone
     * can see. Same guard as photo_url, and it falls through to the larger
     * conversion and then the original, so a picture always appears.
     */
    public function getPhotoThumbUrlAttribute(): ?string
    {
        if ($this->exists && ($media = $this->getFirstMedia('avatar')) !== null) {
            return $media->getAvailableUrl(['list', 'profile']);
        }

        return $this->photo_url;
    }

    /**
     * Where the old faculty site keeps this teacher's photograph.
     *
     * The last use of that host, and it exists for one job: giving
     * teachers:download-photos something to fetch so the picture can be brought
     * here. Nothing that renders a page should call it — see photo_url.
     *
     * Reads the column directly. The accessor above deliberately no longer
     * returns the bare filename, and this is the only thing that still needs it.
     */
    public function legacyPhotoUrl(): ?string
    {
        $photo = trim((string) $this->getRawOriginal('photo'));

        // Blank, already an address, or a rooted path — none of which is the
        // legacy import's bare filename.
        if ($photo === '' || Str::startsWith($photo, ['http://', 'https://', '/'])) {
            return null;
        }

        return self::PHOTO_BASE_URL . rawurlencode($photo);
    }

    /** The legacy address, but only when it is safe for the server to request. */
    public function serverFetchableLegacyPhotoUrl(): ?string
    {
        $url = $this->legacyPhotoUrl();

        if ($url === null) {
            return null;
        }

        return OutboundUrl::rejectionReason($url) === null ? $url : null;
    }

    /**
     * The photograph, but only when the server itself is the one fetching it.
     *
     * photo_url hands back whatever absolute URL the column holds, which is
     * right for an <img> tag — the browser fetches that, from outside the
     * network, and a bad address costs a broken image and nothing else.
     *
     * Two paths are different: the share-card generator requests it with the
     * HTTP client, and the CV renderer runs dompdf with remote images enabled.
     * Both fetch from inside the network, where an address like
     * http://169.254.169.254/ or a database host is reachable and a public
     * visitor's is not. The column is written by the legacy import from another
     * system's data rather than typed into a form, so its contents are not ours
     * to trust. Anything that resolves to a private range is refused here and
     * the caller falls back to the initials block.
     *
     * The check does a DNS lookup, so it is deliberately not part of photo_url:
     * that one renders on every card of every directory page.
     */
    public function serverFetchablePhotoUrl(): ?string
    {
        $url = $this->photo_url;

        if (blank($url)) {
            return null;
        }

        return OutboundUrl::rejectionReason($url) === null ? $url : null;
    }

    /**
     * The photograph as a file on this machine, for the PDF renderer.
     *
     * The CV used to hand dompdf the same absolute URL the browser gets, and
     * for a photograph in our own storage that address points back at this
     * application. So generating a CV made the server issue an HTTP request to
     * itself and wait for the answer — and `artisan serve` runs a single
     * worker, which is already busy generating that CV. The request could never
     * be served, the PDF never finished, and the server stayed wedged for every
     * visitor afterwards: seventeen connections were queued behind one download
     * when this was found.
     *
     * Behind a multi-worker server it completes, but it is still a network
     * round trip, a second full request through the middleware stack, and a
     * timeout waiting to happen, to read a file that is sitting on the disk.
     *
     * So: our own photographs are read from the disk. Only the legacy host's
     * are fetched, by serverFetchablePhotoUrl, which is what that vetting was
     * written for.
     *
     * dompdf resolves local paths against its chroot, which the package sets to
     * base_path(); everything the media library writes lives under it.
     */
    public function localPhotoPath(): ?string
    {
        if (! $this->exists) {
            return null;
        }

        $media = $this->getFirstMedia('avatar');

        if ($media === null) {
            return null;
        }

        /*
         * The same 600px copy the profile page shows, not the master.
         *
         * A CV draws the photograph about 80px wide. Handing dompdf whatever
         * the photographer delivered means decoding a full studio JPEG to
         * produce a thumbnail, once per download, in the request. The profile
         * conversion is already the right size and is already on disk.
         *
         * Asked for by name only when it exists: 1,843 of the photographs on
         * file predate these conversions, and getPath() on an ungenerated one
         * points at nothing. Those fall through to the master, which is what
         * this method has always returned.
         */
        if ($media->hasGeneratedConversion('profile')) {
            $conversion = $media->getPath('profile');

            if (is_file($conversion)) {
                return $conversion;
            }
        }

        $path = $media->getPath();

        return is_file($path) ? $path : null;
    }

    /**
     * Only the teachers the public is allowed to see.
     *
     * The website has always applied these two conditions by hand in each
     * controller and Livewire component. The API adds a second set of callers
     * reading the same records, and a condition forgotten in one endpoint would
     * publish the other 872: of 2,000 teacher records, 1,128 are visible.
     *
     * So the rule lives here once, is used by every API query, and is covered by
     * a test that counts what the endpoints return against this scope.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('teachers.is_active', true)
            ->where('teachers.is_archived', false);
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
     * The research interests as a plain list of names.
     *
     * A method rather than an accessor, deliberately. `research_interests` was
     * one, and Eloquent studly-cases an attribute name before looking for its
     * accessor — so `getResearchInterestsAttribute` answered to
     * `$teacher->researchInterests` as well, and shadowed the relation of that
     * name. Every `->researchInterests->isNotEmpty()` in the views got an array
     * back instead of a collection.
     *
     * An array, not a collection, and that is the whole of the reason it is not
     * one: the cards call array_slice() on it to show the first two, and
     * theme_portrait asks empty() before printing "none". A collection breaks
     * the first outright and quietly fails the second — an object is never
     * empty, so the "no interests" line could not appear. The accessor this
     * replaced returned an array and every template was written against that.
     *
     * @return array<int, string>
     */
    public function researchInterestNames(): array
    {
        return $this->researchInterests
            ->pluck('interest')
            ->map(fn ($interest) => trim((string) $interest))
            ->filter()
            ->values()
            ->all();
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
            // affiliation and used_our_affiliation say which of these papers
            // this teacher wrote as one of ours — the ones from a previous
            // employer are theirs to list and not the university's to count.
            ->withPivot(['author_role', 'sort_order', 'incentive_amount', 'affiliation', 'used_our_affiliation'])
            ->orderBy('publications.sort_order')
            ->withTimestamps();
    }

    /**
     * The publications this teacher wrote under our own affiliation.
     *
     * Deliberately excludes the ones nothing has established. An import that
     * never recorded an affiliation is not evidence of one, and counting it
     * would let the university claim papers on the strength of a missing
     * column. `publications:backfill-author-affiliations` is what turns those
     * from unknown into an answer.
     */
    public function ourPublications(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->publications()->wherePivot('used_our_affiliation', true);
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
     * Get the research interests for the teacher.
     */
    public function researchInterests(): HasMany
    {
        return $this->hasMany(ResearchInterest::class)->orderBy('sort_order');
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
     * Scopus author identifiers known to be this teacher.
     *
     * Several is the normal case: Scopus builds author profiles by algorithm and
     * splits one person across them when a name is written differently or an
     * affiliation changes.
     */
    public function scopusAuthorIds(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ScopusAuthorId::class, 'authorable');
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
     * The photograph as a page should download it.
     *
     * The original is the master: whatever the photographer delivered, at
     * whatever size, uncropped. That is the right thing to keep and the wrong
     * thing to put in an <img>, because a directory page shows twenty-four of
     * them at once and a studio JPEG is measured in megabytes.
     *
     * Width only. Setting both dimensions is what a conversion does to crop,
     * and cropping here would undo the whole point of storing the original
     * untouched — the framing is the theme's decision, made in CSS, where it
     * can be changed without asking anyone to sit for another photograph.
     *
     * 600px because that is twice the widest a face is ever drawn: tiles start
     * at 14rem and the profile portrait is 11.5rem, so this is still sharp on a
     * retina screen with room to spare. The studio files are 600px wide to
     * begin with, which makes this a no-op for them and a real saving for
     * anything larger.
     *
     * DoNotUpsize, because most of what is on file is not a studio file. The
     * 1,048 photographs pulled off the old faculty site are a few kilobytes
     * each, and blowing those up to 600px would hand every visitor a bigger,
     * blurrier copy of a small picture. Named arguments, so the constraints
     * survive being written to the media row and read back — the transform that
     * restores these enums looks for them by name.
     *
     * Not queued: it is one resize, it runs while the upload request is still
     * open, and a queue that is not running would otherwise leave every new
     * photograph without one. Missing conversions are survivable anyway — see
     * photo_url, which falls back to the original.
     *
     * Replaces a 100x100 `thumb` and a 300x300 `medium` that nothing ever
     * asked for, and that were square for the same mistaken reason the uploader
     * was.
     */


    public function registerMediaConversions(?Media $media = null): void
    {
        // Full teacher profile
        $this->addMediaConversion('profile')
            ->width(
                width: 600,
                constraints: [
                    Constraint::PreserveAspectRatio,
                    Constraint::DoNotUpsize,
                ]
            )
            ->quality(90)
            ->performOnCollections('avatar')
            ->nonQueued();

        // Teacher directory / list
        $this->addMediaConversion('list')
            ->width(
                width: 300,
                constraints: [
                    Constraint::PreserveAspectRatio,
                    Constraint::DoNotUpsize,
                ]
            )
            ->quality(85)
            ->performOnCollections('avatar')
            ->nonQueued();

        // Square avatar
        $this->addMediaConversion('avatar')
            ->fit(Fit::Crop, 200, 200)
            ->quality(85)
            ->performOnCollections('avatar')
            ->nonQueued();

        // Small avatar
        $this->addMediaConversion('avatar-sm')
            ->fit(Fit::Crop, 100, 100)
            ->quality(85)
            ->performOnCollections('avatar')
            ->nonQueued();
    }
}
