<?php

namespace App\Services;

use App\Models\ApprovalSetting;
use App\Models\Teacher;
use App\Models\TeacherVersion;
use App\Models\NotificationRouting;
use App\Models\User;
use App\Notifications\TeacherProfileUpdatePending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class TeacherVersionService
{
    /**
     * Map of section keys to their field/relation names
     * IMPORTANT: Section keys MUST match ApprovalSettings table section_key values
     * 
     * Based on TeacherForm.php tabs
     */
    public const FIELD_SECTION_MAP = [
        // Tab 1: Basic Info (removed 'photo' - it's a media field)
        'basic_info' => ['department_id', 'designation_id', 'employee_id', 'webpage', 'joining_date', 'work_location', 'name_prefix_id', 'first_name', 'middle_name', 'last_name', 'academicSuffixes', 'scopus_id', 'bio'],
        
        // Tab 2: Contact Info
        'contact_info' => ['phone', 'personal_phone', 'extension_no', 'office_room', 'secondary_email', 'present_address', 'permanent_address'],
        
        // Tab 3: Personal Details
        'personal_details' => ['date_of_birth', 'gender_id', 'blood_group_id', 'country_id', 'religion_id'],
        
        // Tab 4: Research Interest (Relation) — the section key stays
        // `academic_info` because approval settings are stored under it.
        'academic_info' => ['researchInterests'],

        // Area of Expertise (Relation) — the research directory's list, kept
        // apart from research interests and approved on its own.
        'area_of_expertise' => ['areasOfExpertise'],

        // Tab 5: Educations (Relation)
        'educations' => ['educations'],
        
        // Tab 6: Publications (Relation)
        'publications' => ['publications'],
        
        // Tab 7: Job Experience (Relation)
        'job_experiences' => ['jobExperiences'],
        
        // Tab 8: Training Experience (Relation)
        'training_experiences' => ['trainingExperiences'],
        
        // Tab 9: Awards (Relation)
        'awards' => ['awards'],
        
        // Tab 10: Skills (Relation)
        'skills' => ['skills'],
        
        // Tab 11: Teaching Areas (Relation)
        'teaching_areas' => ['teachingAreas'],
        
        // Tab 12: Memberships (Relation)
        'memberships' => ['memberships'],
        
        // Tab 13: Social Links (Relation)
        'social_links' => ['socialLinks'],
        
        // Tab 15: Settings
        // The form's field names, not the old column: `employment_status` no
        // longer exists, and a field missing from here is invisible to change
        // detection — a save that only touched the job type was reported as
        // "no changes" and dropped.
        'settings' => ['profile_status', 'employment_status_id', 'leaving_date', 'job_type_id', 'login_allowed', 'is_public', 'is_active', 'is_archived', 'sort_order'],
    ];

    /**
     * Fields that are Spatie Media Library collections (NOT Laravel relationships)
     */
    public const MEDIA_FIELDS = ['photo', 'documents'];
    
    /**
     * Known Laravel relationship names
     */
    public const RELATION_NAMES = [
        'educations', 'publications', 'jobExperiences', 'trainingExperiences',
        'awards', 'skills', 'teachingAreas', 'researchInterests', 'areasOfExpertise', 'memberships', 'socialLinks'
    ];

    /**
     * Many-to-many relations, which arrive as a list of ids rather than rows.
     *
     * Kept apart from RELATION_NAMES because everything in that list is a
     * hasMany whose form data is whole records to create, update or delete.
     * A pivot is a set: the form sends [3, 1] and the answer is to sync those
     * two ids, not to build two rows.
     *
     * Order is the value, not an accident of it — "PhD, MBA" is not "MBA,
     * PhD" — so the position each id was chosen in becomes its sort_order.
     */
    public const PIVOT_RELATIONS = ['academicSuffixes'];

    /**
     * Static flag to prevent Observer recursion
     */
    public static bool $ignoreObserver = false;

    /**
     * Legacy entry point for Observer calls.
     * This handles scalar updates coming from direct model updates (not via Form/Relationship manager).
     */
    public function processUpdate(Teacher $teacher, array $dirtyFields): void
    {
        $data = [];
        foreach ($dirtyFields as $field) {
            $data[$field] = $teacher->$field;
        }
        $this->handleUpdateFromForm($teacher, $data);
    }

    /**
     * Process teacher update request.
     * This is the MAIN entry point from Controller/Resource.
     * 
     * $skipApproval applies every change at once, whatever the approval
     * settings say. Approval exists so that a teacher's own edits are checked
     * before they are published; an administrator editing a profile is the
     * checker, and routing their change back to themselves only meant that
     * switches like Publicly Visible appeared to save and did nothing.
     *
     * @return bool True if changes were detected and processed, false if no changes
     */
    public function handleUpdateFromForm(Teacher $teacher, array $allData, bool $skipApproval = false, bool $fullSnapshot = false): bool
    {
        // DEBUG: Log incoming data keys to verify relations are included
        \Log::info('TeacherVersionService: Incoming data keys', [
            'keys' => array_keys($allData),
            'has_educations' => isset($allData['educations']),
            'has_skills' => isset($allData['skills']),
            'educations_count' => isset($allData['educations']) ? count($allData['educations']) : 0,
            'skills_count' => isset($allData['skills']) ? count($allData['skills']) : 0,
        ]);
        
        // 1. Identify changed sections
        $changedSections = $this->identifyChangedSections($teacher, $allData);
        
        \Log::info('TeacherVersionService: Changed sections identified', [
            'sections' => array_keys($changedSections),
        ]);

        if (empty($changedSections)) {
            \Log::info('TeacherVersionService: No changes detected, returning false');
            return false;
        }

        // 2. Check which sections require approval
        $approvalSections = [];
        $autoUpdateSections = [];

        foreach ($changedSections as $section => $fields) {
            if (! $skipApproval && ApprovalSetting::requiresApproval($section)) {
                $approvalSections[$section] = $fields;
            } else {
                $autoUpdateSections[$section] = $fields;
            }
        }

        \Log::info('TeacherVersionService: Approval check', [
            'approval_sections' => array_keys($approvalSections),
            'auto_update_sections' => array_keys($autoUpdateSections),
            'skip_approval' => $skipApproval,
        ]);

        // 3. If NO approval needed, just update everything directly
        if (empty($approvalSections)) {
            // A full snapshot when asked for, or when the teacher has no
            // restore point yet, so every profile has at least one.
            $this->applyDirectly(
                $teacher,
                $allData,
                array_keys($changedSections),
                $fullSnapshot || ! $this->hasRestorePoint($teacher),
            );
            return true;
        }

        // 4. If approval IS needed:
        
        // 4.1 Apply Auto-Update sections immediately (Mixed Scenario)
        if (!empty($autoUpdateSections)) {
            $autoUpdateKeys = [];
            foreach ($autoUpdateSections as $fields) {
                foreach ($fields as $field) {
                    $autoUpdateKeys[] = $field;
                }
            }
            $autoData = \Illuminate\Support\Arr::only($allData, $autoUpdateKeys);
            $this->applyDirectly($teacher, $autoData, array_keys($autoUpdateSections));
        }

        // 4.2 Create version for Pending sections
        $version = $this->createVersion($teacher, $allData, array_keys($approvalSections));
        
        \Log::info('TeacherVersionService: Version created', [
            'version_id' => $version->id,
            'stored_data_keys' => array_keys($version->data ?? []),
        ]);

        return true;
    }

    /**
     * Identify which sections have changed.
     */
    private function identifyChangedSections(Teacher $teacher, array $newData): array
    {
        $changedSections = [];

        foreach (self::FIELD_SECTION_MAP as $section => $fields) {
            foreach ($fields as $field) {
                if (array_key_exists($field, $newData)) {
                    $newValue = $newData[$field];
                    
                    if (in_array($field, self::MEDIA_FIELDS)) {
                        continue;
                    }
                    
                    // STRICT SEPARATION: Check if field is a relation OR scalar
                    if (in_array($field, self::PIVOT_RELATIONS, true)) {
                        // A set of ids, compared in order because the order is
                        // what the teacher wrote their qualifications in.
                        if (! $teacher->relationLoaded($field)) {
                            $teacher->load($field);
                        }

                        $existingIds = $teacher->$field->pluck('id')->map(fn ($id): int => (int) $id)->all();
                        $incomingIds = array_map('intval', array_values(array_filter((array) $newValue)));

                        if ($existingIds !== $incomingIds) {
                            \Log::info("Pivot Mismatch in {$section}.{$field}", [
                                'original' => $existingIds,
                                'new' => $incomingIds,
                            ]);
                            $changedSections[$section][] = $field;
                        }
                    } elseif (in_array($field, self::RELATION_NAMES)) {
                        // Handle Relation
                        $incomingData = is_array($newValue) ? $newValue : []; 
                        
                        if (!$teacher->relationLoaded($field)) {
                            $teacher->load($field);
                        }
                        
                        $existingData = $teacher->$field->toArray();
                        
                        // Pass field name for logging context
                        if ($this->hasRelationshipChanged($existingData, $incomingData, $field)) {
                            $changedSections[$section][] = $field;
                        }
                    } else {
                        // Handle Scalar
                        $originalValue = $teacher->$field;
                        
                        // Normalize for comparison
                        $normOriginal = $this->normalizeValue($originalValue);
                        $normNew = $this->normalizeValue($newValue);

                        // Date normalization: if input is YYYY-MM-DD and DB starts with it
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normNew) && str_starts_with($normOriginal, $normNew)) {
                             continue;
                        }

                        if ($normOriginal !== $normNew) {
                            \Log::info("Scalar Mismatch in {$section}.{$field}", [
                                'original' => $normOriginal,
                                'new' => $normNew,
                            ]);
                            $changedSections[$section][] = $field;
                        }
                    }
                }
            }
        }
        
        return $changedSections;
    }

    private function hasRelationshipChanged(array $existing, array $incoming, string $relationName = ''): bool
    {
        // First check count - if different, definitely changed
        if (count($existing) !== count($incoming)) {
            \Log::info("Relation Count Mismatch in {$relationName}", [
                'existing_count' => count($existing),
                'incoming_count' => count($incoming)
            ]);
            return true;
        }

        // Build a map of existing items by ID for efficient lookup
        $existingById = [];
        foreach ($existing as $existingItem) {
            $id = $existingItem['id'] ?? null;
            if ($id !== null) {
                $existingById[$id] = $existingItem;
            }
        }

        // Fields to skip during comparison (metadata, timestamps, virtual, pivot)
        $skipFields = [
            'id', 'teacher_id', 'created_at', 'updated_at', 'deleted_at',
            'pivot', 'laravel_through_key', 'teachers', 
            'authorable_type', 'authorable_id', 'publication_id', 'incentive_amount', // Pivot/Polymorphic fields
            'first_author_id', 'corresponding_author_id', 'co_author_ids',
            '_degree_level_id', // Virtual field in educations
        ];

        foreach ($incoming as $incomingItem) {
            $incomingId = $incomingItem['id'] ?? null;
            
            if ($incomingId === null) {
                // New item (no ID) - this is a change
                \Log::info("Relation New Item in {$relationName} (no ID)");
                return true;
            }

            if (!isset($existingById[$incomingId])) {
                // Item ID not found in existing - this is a change (shouldn't happen normally)
                \Log::info("Relation Item ID not found in {$relationName}", ['id' => $incomingId]);
                return true;
            }

            $existingItem = $existingById[$incomingId];

            // Compare each field in incoming item against existing
            foreach ($incomingItem as $key => $val) {
                // Skip metadata and virtual fields
                if (in_array($key, $skipFields) || str_starts_with($key, '_')) {
                    continue;
                }

                // Skip if key doesn't exist in DB record
                if (!array_key_exists($key, $existingItem)) {
                    continue;
                }

                $dbVal = $existingItem[$key];

                $normDb = $this->normalizeValue($dbVal);
                $normIn = $this->normalizeValue($val);

                if ($normDb !== $normIn) {
                    \Log::info("Relation Value Mismatch in {$relationName}, id {$incomingId}, key {$key}", [
                        'db_value' => $normDb,
                        'in_value' => $normIn,
                    ]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Normalize value for comparison handles booleans, nulls, and strings
     */
    private function normalizeValue($value): string
    {
        if (is_array($value)) {
            // If value is array (e.g. JSON cast field), serialize for comparison
            // Sorting keys might be needed for strict check, but json_encode is a good start
            return json_encode($value);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_null($value)) {
            return '';
        }
        
        $stringValue = trim((string)$value);

        // Handle empty string
        if ($stringValue === '') {
            return '';
        }

        // Handle Numerics (ignore insignificant zeros/decimal points)
        // 5 == 5.00
        if (is_numeric($stringValue)) {
            return (string)(float)$stringValue;
        }

        // Handle Time Format (H:i:s vs H:i)
        // matches 7:30, 07:30, 7:30:00, 07:30:00
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $stringValue)) {
            // Normalize to H:i
            $parts = explode(':', $stringValue);
            $h = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $m = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            return "{$h}:{$m}";
        }

        // Handle ISO Dates (truncate time part if just a date comparison is intended?)
        // Often DB returns "2024-11-28 00:00:00" or "2024-11-28T00:00:00.000000Z"
        // And form returns "2024-11-28"
        // Try to verify if it's a date-like string
        // A related row's toArray() serialises its dates in UTC, so a date of
        // 2 September comes back as "2026-09-01T18:00:00Z" here (+06). Cut to
        // its first ten characters that is the 1st, and every row carrying a
        // date read as edited on every save. Back to local time first.
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/', $stringValue)) {
            return \Illuminate\Support\Carbon::parse($stringValue)
                ->setTimezone(config('app.timezone'))
                ->format('Y-m-d');
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $stringValue, $matches)) {
            // If the string is purely a date, return it
            if ($stringValue === $matches[1]) {
                return $stringValue;
            }
            // If it has time 00:00:00, consider it just a date for safer comparison?
            // Risk: If time IS important, this mimics loose date comparison.
            // But usually DatePicker returns Y-m-d.
            // Let's return just Y-m-d if the time is 00:00:00 OR we want loose comparison
            // To be safe, we can return the Y-m-d part as a normalized 'Date' representation
            // ONLY IF the other side matches this logic.
            // Since we process both DB and Input through this,
            // "2024-11-28" -> "2024-11-28"
            // "2024-11-28 00:00:00" -> "2024-11-28"
            return $matches[1];
        }

        return $stringValue;
    }

    /**
     * Apply updates immediately (No approval required)
     */
    private function applyUpdates(Teacher $teacher, array $data): void
    {
        DB::transaction(function () use ($teacher, $data) {
            $scalarData = Arr::except(
                $data,
                array_merge(self::PIVOT_RELATIONS, self::MEDIA_FIELDS, ...array_values($this->getRelationshipFields())),
            );

            $this->writeScalars($teacher, $scalarData);

            // Pivots, synced with the order they were given in. The pages do
            // not let the form save relationships itself — everything routes
            // through here — so without this the qualifications were dropped.
            $this->syncPivots($teacher, Arr::only($data, self::PIVOT_RELATIONS));

            // Update relations
            foreach ($this->getRelationshipFields() as $section => $relations) {
                foreach ($relations as $relationName) {
                    if (isset($data[$relationName])) {
                        $this->syncRelation($teacher, $relationName, $data[$relationName]);
                    }
                }
            }
        });
    }

    /**
     * Apply a change that needs no approval, and keep a record of it.
     *
     * An administrator's edit, or a section that does not require approval,
     * used to change the profile and leave nothing behind: nobody could say
     * afterwards what a field had been or who changed it. The sections are
     * captured first, then written, then filed as an "applied_directly"
     * version carrying both states.
     */
    private function applyDirectly(Teacher $teacher, array $data, array $sections, bool $fullSnapshot = false): void
    {
        $before = $this->snapshotSections($teacher, $sections);

        $this->applyUpdates($teacher, $data);

        $version = $this->recordDirectChange($teacher, $data, $sections, $before, $fullSnapshot);

        $this->notifyTeacherOfDirectChange($teacher, $version);
    }

    /**
     * Tell the teacher when someone else changed their profile, naming the
     * sections and linking to the record of the change. Sent after the record
     * exists; it used to go out before, with nothing to link to.
     */
    private function notifyTeacherOfDirectChange(Teacher $teacher, TeacherVersion $version): void
    {
        if (! auth()->check() || ! $teacher->user || auth()->id() === $teacher->user_id) {
            return;
        }

        try {
            $teacher->user->notify(new \App\Notifications\TeacherProfileUpdatedByAdmin($teacher, auth()->user(), $version));
        } catch (\Exception $e) {
            \Log::error('Failed to send TeacherProfileUpdatedByAdmin notification: ' . $e->getMessage());
        }
    }

    /**
     * Whether the teacher already has a version the profile can be rolled
     * back to. Until there is one, a direct save keeps a full snapshot on its
     * own, so every profile gets at least one restore point.
     */
    private function hasRestorePoint(Teacher $teacher): bool
    {
        return TeacherVersion::where('teacher_id', $teacher->id)
            ->where(function ($query) {
                $query->whereIn('status', ['approved', 'partially_approved', 'completed'])
                    ->orWhere(function ($query) {
                        $query->where('status', 'applied_directly');
                        foreach (self::RELATION_NAMES as $relation) {
                            $query->whereNotNull("data->{$relation}");
                        }
                    });
            })
            ->exists();
    }

    /**
     * Keep the whole profile as a restore point without changing anything —
     * asked for on a save that had nothing to change.
     */
    public function recordFullSnapshot(Teacher $teacher, array $data): TeacherVersion
    {
        return $this->recordDirectChange($teacher, $data, [], [], true);
    }

    /**
     * $fullSnapshot keeps the whole form rather than only the changed
     * sections, which makes the version a restore point.
     */
    private function recordDirectChange(Teacher $teacher, array $data, array $sections, array $before, bool $fullSnapshot = false): TeacherVersion
    {
        $fields = collect($sections)
            ->flatMap(fn (string $section): array => self::FIELD_SECTION_MAP[$section] ?? [])
            ->all();

        $latestVersion = $teacher->versions()->latest('version_number')->first();

        // The change is live now, so this record is the teacher's active
        // version — the same as an approved one becomes.
        TeacherVersion::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return TeacherVersion::create([
            'teacher_id' => $teacher->id,
            'version_number' => ($latestVersion?->version_number ?? 0) + 1,
            'data' => $fullSnapshot ? $data : Arr::only($data, $fields),
            'previous_data' => $before,
            'change_summary' => $sections ? implode(', ', $sections) : 'Full snapshot',
            'status' => 'applied_directly',
            'is_active' => true,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'changed_sections' => $sections,
            'pending_sections' => [],
            'approved_sections' => $sections,
            'rejected_sections' => [],
            'section_remarks' => [],
        ]);
    }

    /**
     * Each section's current state, in the form the comparison screen reads.
     *
     * @return array<string, array>
     */
    private function snapshotSections(Teacher $teacher, array $sections): array
    {
        $snapshot = [];
        foreach ($sections as $section) {
            $snapshot[$section] = $this->sectionSnapshot($teacher, $section);
        }

        return $snapshot;
    }

    /**
     * One section of the profile as it stands in the database.
     *
     * Read from the stored attributes rather than toArray(): a related row's
     * toArray() writes its dates in UTC, so a date of 2 September is recorded
     * as the 1st here (+06). JSON columns are decoded so they compare with
     * what the form sends.
     */
    public function sectionSnapshot(Teacher $teacher, string $section): array
    {
        $fields = self::FIELD_SECTION_MAP[$section] ?? [];
        $relation = collect($fields)->first(fn (string $field): bool => in_array($field, self::RELATION_NAMES, true));

        if ($relation !== null) {
            return $teacher->$relation()->get()
                ->map(fn ($row): array => $this->storedAttributes($row))
                ->values()
                ->all();
        }

        $stored = $this->storedAttributes($teacher);
        $data = [];
        foreach ($fields as $field) {
            if (in_array($field, self::MEDIA_FIELDS, true)) {
                continue;
            }

            $data[$field] = in_array($field, self::PIVOT_RELATIONS, true)
                ? $teacher->$field()->get()->pluck('id')->map(fn ($id): int => (int) $id)->all()
                : ($stored[$field] ?? null);
        }

        return $data;
    }

    private function storedAttributes(\Illuminate\Database\Eloquent\Model $model): array
    {
        $attributes = $model->getAttributes();

        foreach ($model->getCasts() as $key => $cast) {
            if (is_string($attributes[$key] ?? null)
                && in_array(strtolower((string) $cast), ['array', 'json', 'collection', 'object'], true)) {
                $attributes[$key] = json_decode($attributes[$key], true);
            }
        }

        if ($model->relationLoaded('pivot') && $model->getRelation('pivot')) {
            $attributes['pivot'] = $model->getRelation('pivot')->getAttributes();
        }

        return $attributes;
    }

    /**
     * Keep what each section held at the moment it is decided: what an
     * approval replaces, or what a rejection left in place.
     */
    private function recordReplacedState(TeacherVersion $version, array $sections): void
    {
        $replaced = $version->replaced_data ?? [];
        foreach ($sections as $section) {
            $replaced[$section] = $this->sectionSnapshot($version->teacher, $section);
        }

        $version->update(['replaced_data' => $replaced]);
    }

    private function getRelationshipFields(): array
    {
        return [
            'educations' => ['educations'],
            'publications' => ['publications'],
            'job_experiences' => ['jobExperiences'],
            'training_experiences' => ['trainingExperiences'],
            'awards' => ['awards'],
            'skills' => ['skills'],
            'teaching_areas' => ['teachingAreas'],
            'academic_info' => ['researchInterests'],
            'area_of_expertise' => ['areasOfExpertise'],
            'memberships' => ['memberships'],
            'social_links' => ['socialLinks'],
        ];
    }

    /**
     * Create a new version for approval
     */
    private function createVersion(Teacher $teacher, array $allData, array $changedSectionNames): TeacherVersion
    {
        $latestVersion = $teacher->versions()->latest('version_number')->first();
        $newVersionNumber = ($latestVersion?->version_number ?? 0) + 1;
        
        $version = TeacherVersion::create([
            'teacher_id' => $teacher->id,
            'version_number' => $newVersionNumber,
            'data' => $allData, // Store EVERYTHING
            // The changed sections as they stood when the change was submitted
            'previous_data' => $this->snapshotSections($teacher, $changedSectionNames),
            'change_summary' => implode(', ', $changedSectionNames),
            'status' => 'pending',
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
            // Section-level approval initialization
            'changed_sections' => $changedSectionNames,
            'pending_sections' => $changedSectionNames, // All sections start as pending
            'approved_sections' => [],
            'rejected_sections' => [],
            'section_remarks' => [],
        ]);
        
        // Send notifications to approvers
        $this->sendNotifications($version, $changedSectionNames);
        
        return $version;
    }

    /**
     * Send notifications based on routing configuration
     */
    private function sendNotifications(TeacherVersion $version, array $sections): void
    {
        $allRecipients = collect();
        
        foreach ($sections as $section) {
            $recipients = NotificationRouting::getRecipientsFor('teacher_profile_update', $section, $version->teacher);
            $allRecipients = $allRecipients->merge($recipients);
        }
        
        // Send to unique recipients only
        $uniqueRecipients = $allRecipients->unique('id');
        
        foreach ($uniqueRecipients as $recipient) {
            try {
                $recipient->notify(new TeacherProfileUpdatePending($version, $sections));
            } catch (\Exception $e) {
                \Log::error('Failed to send TeacherProfileUpdatePending notification to ' . $recipient->email . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Approve authorized sections of a version
     * (Replaces 'Approve All' - approves everything the user has permission for)
     */
    public function approveVersion(TeacherVersion $version): void
    {
        \Log::info('approveVersion called', [
            'version_id' => $version->id,
            'user_id' => auth()->id(),
        ]);

        $pendingSections = $version->pending_sections ?? [];
        $authorizedSections = [];

        // Identify authorized sections
        foreach ($pendingSections as $section) {
            if ($this->canUserApproveSection(auth()->user(), $section, $version->teacher)) {
                $authorizedSections[] = $section;
            }
        }

        if (empty($authorizedSections)) {
            throw new \Exception("You do not have permission to approve any of the pending sections.");
        }

        DB::transaction(function () use ($version, $authorizedSections) {
             // Deactivate current active version (only if this becomes the active one - but partial approval keeps it pending usually)
             // Wait, if we partial approve, previous version stays active until this one is FULLY approved?
             // Or can we have mixed state? 
             // Current design: Data is applied IMMEDIATELY upon section approval.
             // So we don't need to deactivate previous version globally unless status changes to full approved.
             // Actually, the teacher profile is single source of truth.
             // So we just apply data. 

             $this->recordReplacedState($version, $authorizedSections);

             // Move approved sections
             $currentPending = $version->pending_sections ?? [];
             $newPending = array_values(array_diff($currentPending, $authorizedSections));
             $approvedSections = array_merge($version->approved_sections ?? [], $authorizedSections);
             
             // Update version
             $version->update([
                 'pending_sections' => $newPending,
                 'approved_sections' => $approvedSections,
                 'reviewed_by' => auth()->id(),
                 'reviewed_at' => now(),
             ]);
             
             // Apply Data for authorized sections
             foreach ($authorizedSections as $section) {
                 $this->applySectionData($version, $section);
             }
             
             // Update status
             $this->updateVersionStatus($version);
            
             \Log::info('approveVersion: Authorized sections applied', [
                 'sections' => $authorizedSections,
             ]);
             
             // Notify teacher if fully approved (handled by updateVersionStatus? No, explicitly here)
             if ($version->refresh()->status === 'approved' && $version->teacher->user) {
                 $version->teacher->user->notify(new \App\Notifications\TeacherProfileApproved($version));
             }
        });
    }

    /**
     * Write one repeated section. The list is the complete section, so any
     * existing row it leaves out is removed; the pages see to that by loading
     * every row a window held back before they save.
     */
    private function syncRelation(Teacher $teacher, string $relationName, array $items, ?User $actor = null): void
    {
        \App\Support\TeacherRelationWriter::save($teacher, $relationName, $items, actor: $actor);
    }

    /**
     * Reject authorized sections of a version
     */
    public function rejectVersion(TeacherVersion $version, string $remarks): void
    {
        $pendingSections = $version->pending_sections ?? [];
        $authorizedSections = [];

        // Identify authorized sections
        foreach ($pendingSections as $section) {
            if ($this->canUserApproveSection(auth()->user(), $section, $version->teacher)) {
                $authorizedSections[] = $section;
            }
        }

        if (empty($authorizedSections)) {
            throw new \Exception("You do not have permission to reject any of the pending sections.");
        }

        $this->recordReplacedState($version, $authorizedSections);

        // Move authorized pending sections to rejected
        $currentPending = $version->pending_sections ?? [];
        $newPending = array_values(array_diff($currentPending, $authorizedSections));
        $rejectedSections = array_merge($version->rejected_sections ?? [], $authorizedSections);
        
        // Add remarks for each rejected section
        $sectionRemarks = $version->section_remarks ?? [];
        foreach ($authorizedSections as $section) {
            $sectionRemarks[$section] = $remarks; // Apply same remark to all
        }
        
        $version->update([
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_remarks' => $remarks, // Legacy/Global remark
            'pending_sections' => $newPending,
            'rejected_sections' => $rejectedSections,
            'section_remarks' => $sectionRemarks,
        ]);
        
        // Update version status
        $this->updateVersionStatus($version);
        
        // Notify the teacher about rejected sections
        if ($version->teacher->user) {
            $sectionList = implode(', ', array_map(fn($s) => ucwords(str_replace('_', ' ', $s)), $authorizedSections));
            $version->teacher->user->notify(new \App\Notifications\TeacherProfileRejected($version, 
                "Sections rejected: {$sectionList}. Reason: {$remarks}"
            ));
        }
    }

    // ==========================================
    // Section-Level Approval Methods
    // ==========================================

    /**
     * Approve a specific section within a version
     * This immediately applies the section's data to the teacher profile
     */
    public function approveSection(TeacherVersion $version, string $section): void
    {
        // Permission Check
        if (!$this->canUserApproveSection(auth()->user(), $section, $version->teacher)) {
            throw new \Exception("You are not authorized to approve the '{$section}' section.");
        }

        // Validate section is pending
        if (!$version->isSectionPending($section)) {
            throw new \Exception("Section '{$section}' is not pending approval.");
        }

        DB::transaction(function () use ($version, $section) {
            $this->recordReplacedState($version, [$section]);

            // Move section from pending to approved
            $pendingSections = array_values(array_diff($version->pending_sections ?? [], [$section]));
            $approvedSections = array_merge($version->approved_sections ?? [], [$section]);
            
            // Update version
            $version->update([
                'pending_sections' => $pendingSections,
                'approved_sections' => $approvedSections,
            ]);
            
            // Apply ONLY this section's data to teacher profile
            $this->applySectionData($version, $section);
            
            // Update version status based on remaining pending sections
            $this->updateVersionStatus($version);
            
            \Log::info("Section approved and applied", [
                'version_id' => $version->id,
                'section' => $section,
                'approver_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Reject a specific section within a version
     */
    public function rejectSection(TeacherVersion $version, string $section, string $remarks = ''): void
    {
        // Permission Check
        if (!$this->canUserApproveSection(auth()->user(), $section, $version->teacher)) {
            throw new \Exception("You are not authorized to reject the '{$section}' section.");
        }

        // Validate section is pending
        if (!$version->isSectionPending($section)) {
            throw new \Exception("Section '{$section}' is not pending approval.");
        }

        DB::transaction(function () use ($version, $section, $remarks) {
            $this->recordReplacedState($version, [$section]);

            // Move section from pending to rejected
            $pendingSections = array_values(array_diff($version->pending_sections ?? [], [$section]));
            $rejectedSections = array_merge($version->rejected_sections ?? [], [$section]);
            
            // Store section-specific remark
            $sectionRemarks = $version->section_remarks ?? [];
            if ($remarks) {
                $sectionRemarks[$section] = $remarks;
            }
            
            // Update version
            $version->update([
                'pending_sections' => $pendingSections,
                'rejected_sections' => $rejectedSections,
                'section_remarks' => $sectionRemarks,
            ]);
            
            // Update version status
            $this->updateVersionStatus($version);
            
            // Notify teacher about section rejection
            if ($version->teacher->user) {
                $version->teacher->user->notify(new \App\Notifications\TeacherProfileRejected($version, 
                    "Section '{$section}' was rejected. " . ($remarks ?: 'No remarks provided.')
                ));
            }
            
            \Log::info("Section rejected", [
                'version_id' => $version->id,
                'section' => $section,
                'rejector_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Check if a user is authorized to approve/reject a specific section
     */
    public function canUserApproveSection(\App\Models\User $user, string $section, ?Teacher $teacher = null): bool
    {
        // Strictly follow NotificationRouting configuration
        // Even Super Admins must be explicitly added to the routing table if they need approval rights
        // The teacher narrows a "department head" routing to that teacher's own department's heads
        $allowedRecipients = \App\Models\NotificationRouting::getRecipientsFor('teacher_profile_update', $section, $teacher);
        
        return $allowedRecipients->contains('id', $user->id);
    }

    /**
     * Apply only a specific section's data to the teacher profile
     */
    private function applySectionData(TeacherVersion $version, string $section): void
    {
        $teacher = $version->teacher;
        $data = $version->data;

        if (empty($data)) {
            return;
        }

        $sectionFields = self::FIELD_SECTION_MAP[$section] ?? [];

        // Rows are written as the person who submitted them: whether a paper
        // may be marked featured is their permission, not the approver's.
        $submitter = $version->submitted_by ? User::find($version->submitted_by) : null;

        foreach ($sectionFields as $field) {
            if (in_array($field, self::RELATION_NAMES, true) && is_array($data[$field] ?? null)) {
                $this->syncRelation($teacher, $field, array_values($data[$field]), $submitter);
            }
        }

        // array_key_exists, not isset: a field the change cleared is null, and
        // isset() read that as "not in this version", so clearing a phone
        // number or an address was approved and then never happened.
        $scalars = [];
        foreach ($sectionFields as $field) {
            if (array_key_exists($field, $data)
                && ! in_array($field, self::MEDIA_FIELDS, true)
                && ! in_array($field, self::RELATION_NAMES, true)
                && ! in_array($field, self::PIVOT_RELATIONS, true)) {
                $scalars[$field] = $data[$field];
            }
        }

        $this->writeScalars($teacher, $scalars);

        // basic_info carries the qualifications, which are a pivot and were
        // left out when that section was approved.
        $this->syncPivots($teacher, Arr::only($data, array_intersect($sectionFields, self::PIVOT_RELATIONS)));
    }

    /**
     * The teacher's own columns, from an approved or restored version.
     *
     * Not withoutEvents(): that also silenced the observer's status cascade,
     * so an approved change of employment status never reached is_active or
     * is_archived. $ignoreObserver stops only the versioning, which is the part
     * that must not run again here.
     *
     * @param  array<string, mixed>  $scalars
     */
    private function writeScalars(Teacher $teacher, array $scalars): void
    {
        if ($scalars === []) {
            return;
        }

        self::$ignoreObserver = true;

        try {
            // The observer's updated() keeps the account name in step.
            $teacher->update($scalars);
        } finally {
            self::$ignoreObserver = false;
        }
    }

    /**
     * @param  array<string, mixed>  $pivots  relation name => ids in the order chosen
     */
    private function syncPivots(Teacher $teacher, array $pivots): void
    {
        foreach ($pivots as $relation => $ids) {
            if (! in_array($relation, self::PIVOT_RELATIONS, true) || ! is_array($ids)) {
                continue;
            }

            $teacher->$relation()->sync(
                collect(array_values(array_filter($ids)))
                    ->mapWithKeys(fn ($id, int $position): array => [(int) $id => ['sort_order' => $position]])
                    ->all()
            );
        }
    }

    /**
     * Update version status based on section approvals
     */
    private function updateVersionStatus(TeacherVersion $version): void
    {
        $version->refresh();
        
        $hasPending = !empty($version->pending_sections);
        $hasApproved = !empty($version->approved_sections);
        $hasRejected = !empty($version->rejected_sections);
        
        if ($hasPending) {
            // Still has pending sections
            $status = $hasApproved ? 'partially_approved' : 'pending';
        } else {
            // All sections decided
            if ($hasRejected && !$hasApproved) {
                $status = 'rejected';
            } elseif ($hasApproved && !$hasRejected) {
                $status = 'approved';
            } else {
                // Mix of approved and rejected
                $status = 'completed';
            }
        }
        
        $version->update(['status' => $status]);
        
        // Auto-activate if fully passed (approved or completed)
        // This ensures the version is marked as the "current" active version
        if (in_array($status, ['approved', 'completed']) && !$version->is_active) {
            DB::transaction(function () use ($version) {
                 TeacherVersion::where('teacher_id', $version->teacher_id)
                    ->where('id', '!=', $version->id)
                    ->update(['is_active' => false]);
                 
                 $version->update(['is_active' => true]);
                 
                 \Log::info('Version auto-activated', ['version_id' => $version->id]);
            });
        }
    }

    /**
     * Sections a rollback leaves alone. A publication belongs to every one of
     * its authors and is kept by the research team; rolling one teacher back
     * must not detach papers added to their profile since.
     */
    public const ROLLBACK_EXCLUDED_SECTIONS = ['publications'];

    /**
     * Roll the profile back to a restore point: every section it holds,
     * except those rejected in it and the publications.
     *
     * The rollback is a change like any other, so it is recorded as one —
     * who did it, when, and what each section held before — and the teacher
     * is told, with a link to it.
     */
    public function activateVersion(TeacherVersion $version): void
    {
        if (! $version->isRestorable()) {
            throw new \Exception('This version holds only the sections it changed, so the profile cannot be restored to it.');
        }

        $teacher = $version->teacher;
        $sections = array_values(array_diff(
            array_keys(self::FIELD_SECTION_MAP),
            $version->rejected_sections ?? [],
            self::ROLLBACK_EXCLUDED_SECTIONS,
        ));

        $record = DB::transaction(function () use ($version, $teacher, $sections) {
            $before = $this->snapshotSections($teacher, $sections);

            foreach ($sections as $section) {
                $this->applySectionData($version, $section);
            }

            $after = $this->snapshotSections($teacher->fresh(), $sections);
            $changed = array_values(array_filter(
                $sections,
                fn (string $section): bool => json_encode($this->withoutTimestamps($before[$section]))
                    !== json_encode($this->withoutTimestamps($after[$section])),
            ));

            TeacherVersion::where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $version->update(['is_active' => true]);

            $fields = collect($changed)
                ->flatMap(fn (string $section): array => self::FIELD_SECTION_MAP[$section] ?? [])
                ->all();
            $latestVersion = $teacher->versions()->latest('version_number')->first();

            return TeacherVersion::create([
                'teacher_id' => $teacher->id,
                'version_number' => ($latestVersion?->version_number ?? 0) + 1,
                'data' => Arr::only($version->data ?? [], $fields),
                'previous_data' => Arr::only($before, $changed),
                'change_summary' => "Rollback to version {$version->version_number}",
                'status' => 'applied_directly',
                'is_active' => false,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'changed_sections' => $changed,
                'pending_sections' => [],
                'approved_sections' => $changed,
                'rejected_sections' => [],
                'section_remarks' => [],
            ]);
        });

        $this->notifyTeacherOfDirectChange($teacher, $record);
    }

    /** A snapshot without the timestamps a rewrite of the same rows changes. */
    private function withoutTimestamps(array $snapshot): array
    {
        $strip = fn (array $row): array => array_diff_key($row, array_flip(['created_at', 'updated_at']));

        return array_is_list($snapshot)
            ? array_map(fn ($row) => is_array($row) ? $strip($row) : $row, $snapshot)
            : $strip($snapshot);
    }
    /**
     * Apply version data to teacher profile
     * PUBLIC method - can be called from model observer or controller
     * Shared logic used by approveVersion, activateVersion, and model events
     */
    public function applyVersionData(TeacherVersion $version): void
    {
        $teacher = $version->teacher;
        $data = $version->data;
        
        if (empty($data)) {
            \Log::warning('applyVersionData: No data to apply', [
                'version_id' => $version->id,
            ]);
            return;
        }

        // Every section the version holds, except those rejected in it: those
        // were never on the profile, so restoring this version must not put
        // them there. It used to write the whole snapshot, rejected parts and
        // all — and passed the qualifications array to update() as a column.
        $rejected = $version->rejected_sections ?? [];

        foreach (array_keys(self::FIELD_SECTION_MAP) as $section) {
            if (! in_array($section, $rejected, true)) {
                $this->applySectionData($version, $section);
            }
        }
    }
}

