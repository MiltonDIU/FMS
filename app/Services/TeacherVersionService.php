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
        'basic_info' => ['department_id', 'designation_id', 'employee_id', 'webpage', 'joining_date', 'work_location', 'first_name', 'middle_name', 'last_name', 'bio'],
        
        // Tab 2: Contact Info
        'contact_info' => ['phone', 'personal_phone', 'extension_no', 'office_room', 'secondary_email', 'present_address', 'permanent_address'],
        
        // Tab 3: Personal Details
        'personal_details' => ['date_of_birth', 'gender_id', 'blood_group_id', 'country_id', 'religion_id'],
        
        // Tab 4: Academic Info
        'academic_info' => ['research_interest'],
        
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
        'settings' => ['profile_status', 'employment_status', 'is_public', 'is_active', 'is_archived', 'sort_order'],
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
        'awards', 'skills', 'teachingAreas', 'memberships', 'socialLinks'
    ];

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
     * @return bool True if changes were detected and processed, false if no changes
     */
    public function handleUpdateFromForm(Teacher $teacher, array $allData): bool
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
            if (ApprovalSetting::requiresApproval($section)) {
                $approvalSections[$section] = $fields;
            } else {
                $autoUpdateSections[$section] = $fields;
            }
        }

        \Log::info('TeacherVersionService: Approval check', [
            'approval_sections' => array_keys($approvalSections),
            'auto_update_sections' => array_keys($autoUpdateSections),
        ]);

        // 3. If NO approval needed, just update everything directly
        if (empty($approvalSections)) {
            $this->applyUpdates($teacher, $allData);
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
            $this->applyUpdates($teacher, $autoData);
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
                    if (in_array($field, self::RELATION_NAMES)) {
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
            $scalarData = Arr::except($data, array_merge(...array_values($this->getRelationshipFields())));
            
            self::$ignoreObserver = true;
            
            try {
                $teacher->update($scalarData);
            } finally {
                self::$ignoreObserver = false;
            }

            // Update relations
            foreach ($this->getRelationshipFields() as $section => $relations) {
                foreach ($relations as $relationName) {
                    if (isset($data[$relationName])) {
                        $this->syncRelation($teacher, $relationName, $data[$relationName]);
                    }
                }
            }
        });

        // Notify if Third Party Update
        if (auth()->check() && $teacher->user && auth()->id() !== $teacher->user_id) {
            try {
                $teacher->user->notify(new \App\Notifications\TeacherProfileUpdatedByAdmin($teacher, auth()->user()));
            } catch (\Exception $e) {
                \Log::error('Failed to send TeacherProfileUpdatedByAdmin notification: ' . $e->getMessage());
            }
        }
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
            $recipients = NotificationRouting::getRecipientsFor('teacher_profile_update', $section);
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
            if ($this->canUserApproveSection(auth()->user(), $section)) {
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
     * Logic to sync HasMany/MorphToMany relationships
     */
    private function syncRelation(Teacher $teacher, string $relationName, array $items): void
    {
        \Log::info("syncRelation called for: {$relationName}", [
            'items_count' => count($items),
            'sample_keys' => !empty($items) ? array_keys($items[0] ?? []) : [],
        ]);

        $relation = $teacher->$relationName();
        $relatedModel = $relation->getRelated();
        $relatedKeyName = $relatedModel->getKeyName(); // Usually 'id'
        $fillable = $relatedModel->getFillable();
        
        // Get current existing IDs for this teacher's relation
        // Use qualified table.column name to avoid ambiguity in joined queries (MorphToMany)
        $tableName = $relatedModel->getTable();
        $existingIds = $relation->pluck("{$tableName}.{$relatedKeyName}")->toArray();
        
        $keepIds = [];
        $processedItems = 0;

        foreach ($items as $index => $item) {
            // Skip empty items
            if (empty($item) || !is_array($item)) {
                continue;
            }
            
            $processedItems++;
            
            // Filter out virtual/computed fields (starting with _) and keep only fillable fields
            // Also exclude author virtual fields for publications
            $excludeVirtualFields = ['first_author_id', 'corresponding_author_id', 'co_author_ids', 'teachers'];
            $cleanData = collect($item)
                ->filter(function ($value, $key) use ($fillable, $relatedKeyName, $excludeVirtualFields) {
                    // Exclude id, virtual fields starting with _, author virtual fields, and non-fillable fields
                    return !str_starts_with($key, '_') 
                        && $key !== $relatedKeyName 
                        && $key !== 'id'  // Explicitly exclude 'id' 
                        && !in_array($key, $excludeVirtualFields)
                        && (empty($fillable) || in_array($key, $fillable));
                })
                ->toArray();
            
            // Get the ID from item (could be 'id' or the model's primary key name)
            $itemId = $item[$relatedKeyName] ?? $item['id'] ?? null;
            
            \Log::info("syncRelation item {$index}", [
                'item_id' => $itemId,
                'has_id' => !empty($itemId),
                'clean_data_keys' => array_keys($cleanData),
                'clean_data' => $cleanData,
            ]);

            $record = null;

            if (!empty($itemId)) {
                // UPDATE existing record
                $keepIds[] = $itemId;
                
                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                    // Use find() and update for more reliable updating
                    $existingRecord = $relatedModel::find($itemId);
                    if ($existingRecord) {
                        $existingRecord->fill($cleanData);
                        $existingRecord->save();
                        $record = $existingRecord;
                        \Log::info("Updated HasMany record", ['id' => $itemId, 'updated' => true]);
                    } else {
                        \Log::warning("HasMany record not found for update", ['id' => $itemId]);
                    }
                } elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\MorphToMany || 
                          $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                    // For MorphToMany, update related record directly
                    $existingRecord = $relatedModel::find($itemId);
                    if ($existingRecord) {
                        $existingRecord->fill($cleanData);
                        $existingRecord->save();
                        $record = $existingRecord;
                        
                        // Update pivot if needed
                        $pivotData = Arr::only($item, ['author_role', 'sort_order', 'is_corresponding']);
                        if (!empty($pivotData)) {
                            $relation->updateExistingPivot($itemId, $pivotData);
                        }
                        \Log::info("Updated MorphToMany record", ['id' => $itemId]);
                    }
                }
            } else {
                // CREATE new record
                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                    $newRecord = $relation->create($cleanData);
                    $keepIds[] = $newRecord->$relatedKeyName;
                    $record = $newRecord;
                    \Log::info("Created HasMany record", ['new_id' => $newRecord->$relatedKeyName]);
                } elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\MorphToMany ||
                          $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                    $newModel = $relatedModel::create($cleanData);
                    $pivotData = Arr::only($item, ['author_role', 'sort_order', 'is_corresponding']);
                    $relation->attach($newModel->$relatedKeyName, $pivotData);
                    $keepIds[] = $newModel->$relatedKeyName;
                    $record = $newModel;
                    \Log::info("Created MorphToMany record", ['new_id' => $newModel->$relatedKeyName]);
                }
            }

            // Special handling for publications - sync authors
            if ($relationName === 'publications' && $record) {
                $this->syncPublicationAuthors($record, $item);
            }
        }
        
        // Handle Deletions - remove records that are NOT in keepIds
        $idsToDelete = array_diff($existingIds, $keepIds);
        
        \Log::info("syncRelation deletion check for {$relationName}", [
            'existing_ids' => $existingIds,
            'keep_ids' => $keepIds,
            'ids_to_delete' => $idsToDelete,
        ]);
        
        if (!empty($idsToDelete)) {
            if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                $deletedCount = $relatedModel::whereIn($relatedKeyName, $idsToDelete)->delete();
                \Log::info("Deleted HasMany records", ['deleted_count' => $deletedCount, 'deleted_ids' => $idsToDelete]);
            } elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\MorphToMany ||
                      $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                $relation->detach($idsToDelete);
                \Log::info("Detached MorphToMany records", ['detached_ids' => $idsToDelete]);
            }
        }
        
        \Log::info("syncRelation complete for {$relationName}", [
            'processed_items' => $processedItems,
            'kept_ids' => count($keepIds),
            'deleted_ids' => count($idsToDelete),
        ]);
    }

    /**
     * Sync publication authors (first_author_id, corresponding_author_id, co_author_ids)
     */
    private function syncPublicationAuthors(\App\Models\Publication $publication, array $item): void
    {
        $syncData = [];

        // First Author
        if (!empty($item['first_author_id'])) {
            $syncData[$item['first_author_id']] = ['author_role' => 'first', 'sort_order' => 1];
        }

        // Corresponding Author
        if (!empty($item['corresponding_author_id'])) {
            // If already added (e.g. same as first), update role; last one wins
            $existing = $syncData[$item['corresponding_author_id']] ?? [];
            $syncData[$item['corresponding_author_id']] = array_merge($existing, ['author_role' => 'corresponding', 'sort_order' => 2]);
        }

        // Co-Authors
        if (!empty($item['co_author_ids']) && is_array($item['co_author_ids'])) {
            foreach ($item['co_author_ids'] as $index => $coAuthorId) {
                // Don't overwrite higher priority roles
                if (!isset($syncData[$coAuthorId])) {
                    $syncData[$coAuthorId] = ['author_role' => 'co_author', 'sort_order' => 3 + $index];
                }
            }
        }

        // Sync teachers
        if (!empty($syncData)) {
            $publication->teachers()->sync($syncData);
            \Log::info("syncPublicationAuthors: Synced authors for publication", [
                'publication_id' => $publication->id,
                'sync_data' => $syncData,
            ]);
        } else {
            // Clear all authors if no author data provided
            $publication->teachers()->sync([]);
            \Log::info("syncPublicationAuthors: Cleared authors for publication", [
                'publication_id' => $publication->id,
            ]);
        }
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
            if ($this->canUserApproveSection(auth()->user(), $section)) {
                $authorizedSections[] = $section;
            }
        }

        if (empty($authorizedSections)) {
            throw new \Exception("You do not have permission to reject any of the pending sections.");
        }

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
        if (!$this->canUserApproveSection(auth()->user(), $section)) {
            throw new \Exception("You are not authorized to approve the '{$section}' section.");
        }

        // Validate section is pending
        if (!$version->isSectionPending($section)) {
            throw new \Exception("Section '{$section}' is not pending approval.");
        }

        DB::transaction(function () use ($version, $section) {
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
        if (!$this->canUserApproveSection(auth()->user(), $section)) {
            throw new \Exception("You are not authorized to reject the '{$section}' section.");
        }

        // Validate section is pending
        if (!$version->isSectionPending($section)) {
            throw new \Exception("Section '{$section}' is not pending approval.");
        }

        DB::transaction(function () use ($version, $section, $remarks) {
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
    public function canUserApproveSection(\App\Models\User $user, string $section): bool
    {
        // Strictly follow NotificationRouting configuration
        // Even Super Admins must be explicitly added to the routing table if they need approval rights
        $allowedRecipients = \App\Models\NotificationRouting::getRecipientsFor('teacher_profile_update', $section);
        
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

        // Get fields for this section from FIELD_SECTION_MAP
        $sectionFields = self::FIELD_SECTION_MAP[$section] ?? [];
        
        // Check if this section contains relations
        $isRelationSection = in_array($section, array_keys($this->getRelationshipFields()));
        
        if ($isRelationSection) {
            // Handle relation data
            $relationNames = $this->getRelationshipFields()[$section] ?? [];
            foreach ($relationNames as $relationName) {
                if (isset($data[$relationName]) && is_array($data[$relationName])) {
                    $items = array_values($data[$relationName]);
                    $this->syncRelation($teacher, $relationName, $items);
                }
            }
        } else {
            // Handle scalar data
            $scalarData = [];
            foreach ($sectionFields as $field) {
                if (isset($data[$field]) && !in_array($field, self::MEDIA_FIELDS)) {
                    $scalarData[$field] = $data[$field];
                }
            }
            
            if (!empty($scalarData)) {
                Teacher::withoutEvents(function () use ($teacher, $scalarData) {
                    $teacher->update($scalarData);
                    
                    // Name sync if applicable
                    if (isset($scalarData['first_name']) || isset($scalarData['last_name'])) {
                        if ($teacher->user) {
                            $fullName = trim("{$teacher->first_name} {$teacher->middle_name} {$teacher->last_name}");
                            $teacher->user->update(['name' => $fullName]);
                        }
                    }
                });
            }
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
     * Activate any approved version (rollback feature)
     * This allows restoring the teacher profile to any previous version's state
     */
    public function activateVersion(TeacherVersion $version): void
    {
        // Allow activating approved, partially_approved, or completed versions
        if (!in_array($version->status, ['approved', 'partially_approved', 'completed'])) {
            throw new \Exception('Only approved/completed versions can be activated for rollback.');
        }

        \Log::info('activateVersion called (rollback)', [
            'version_id' => $version->id,
            'version_number' => $version->version_number,
        ]);

        DB::transaction(function () use ($version) {
            // Deactivate current active version
            TeacherVersion::where('teacher_id', $version->teacher_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
            
            // Activate this version
            $version->update([
                'is_active' => true,
            ]);
            
            // Apply Data to Teacher (same logic as approveVersion)
            $this->applyVersionData($version);
            
            \Log::info('activateVersion complete (rollback)', [
                'version_id' => $version->id,
            ]);
        });
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

        // Get all relation field names to exclude from scalar update
        $relationFields = self::RELATION_NAMES;
        $scalarData = Arr::except($data, array_merge($relationFields, self::MEDIA_FIELDS));
        
        Teacher::withoutEvents(function () use ($teacher, $scalarData) {
            $teacher->update($scalarData);
            
            // Name sync
            if (isset($scalarData['first_name']) || isset($scalarData['last_name'])) {
                if ($teacher->user) {
                    $fullName = trim("{$teacher->first_name} {$teacher->middle_name} {$teacher->last_name}");
                    $teacher->user->update(['name' => $fullName]);
                }
            }
        });

        // Relationship Sync
        foreach (self::RELATION_NAMES as $relationName) {
            if (isset($data[$relationName]) && is_array($data[$relationName])) {
                $items = array_values($data[$relationName]);
                $this->syncRelation($teacher, $relationName, $items);
            }
        }
    }
}

