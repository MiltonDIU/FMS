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
    private const FIELD_SECTION_MAP = [
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
    private const MEDIA_FIELDS = ['photo', 'documents'];
    
    /**
     * Known Laravel relationship names
     */
    private const RELATION_NAMES = [
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
     */
    public function handleUpdateFromForm(Teacher $teacher, array $allData): void
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
            \Log::info('TeacherVersionService: No changes detected, returning');
            return;
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
            return;
        }

        // 4. If approval IS needed, create a version with EVERYTHING
        $version = $this->createVersion($teacher, $allData, array_keys($approvalSections));
        
        \Log::info('TeacherVersionService: Version created', [
            'version_id' => $version->id,
            'stored_data_keys' => array_keys($version->data ?? []),
        ]);
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
                    
                    // Skip media fields - they are handled by Spatie, not as Laravel relationships
                    if (in_array($field, self::MEDIA_FIELDS)) {
                        continue;
                    }
                    
                    // Check if this is a known relationship (array data)
                    if (is_array($newValue) && in_array($field, self::RELATION_NAMES)) {
                        // Load relation if not loaded
                        if (!$teacher->relationLoaded($field)) {
                            $teacher->load($field);
                        }
                        
                        $existingData = $teacher->$field->toArray();
                        
                        if ($this->hasRelationshipChanged($existingData, $newValue)) {
                            $changedSections[$section][] = $field;
                        }
                    } elseif (!is_array($newValue)) {
                        // Scalar comparison
                        $originalValue = $teacher->$field;
                        if ($originalValue != $newValue) {
                            $changedSections[$section][] = $field;
                        }
                    }
                }
            }
        }

        return $changedSections;
    }

    private function hasRelationshipChanged(array $existing, array $incoming): bool
    {
        if (count($existing) !== count($incoming)) {
            return true;
        }
        // Conservative: if data is present, assume change
        return true;
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
            'change_summary' => 'Updated sections: ' . implode(', ', $changedSectionNames),
            'status' => 'pending',
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
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
     * Approve a version and update teacher profile
     */
    public function approveVersion(TeacherVersion $version): void
    {
        \Log::info('approveVersion called', [
            'version_id' => $version->id,
            'data_keys' => array_keys($version->data ?? []),
        ]);

        DB::transaction(function () use ($version) {
            // Deactivate current active version
            TeacherVersion::where('teacher_id', $version->teacher_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
            
            // Activate this version
            $version->update([
                'status' => 'approved',
                'is_active' => true,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
            
            // Apply Data to Teacher
            $teacher = $version->teacher;
            $data = $version->data;
            
            \Log::info('approveVersion: Starting data apply', [
                'teacher_id' => $teacher->id,
                'data_keys' => array_keys($data ?? []),
            ]);

            // Get all relation field names to exclude from scalar update
            $relationFields = self::RELATION_NAMES;
            $scalarData = Arr::except($data, array_merge($relationFields, self::MEDIA_FIELDS));
            
            \Log::info('approveVersion: Scalar data to update', [
                'scalar_keys' => array_keys($scalarData),
            ]);
            
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

            // 2. Relationship Sync - iterate through known relation names
            \Log::info('approveVersion: Starting relationship sync', [
                'relation_names' => self::RELATION_NAMES,
            ]);
            
            foreach (self::RELATION_NAMES as $relationName) {
                if (isset($data[$relationName]) && is_array($data[$relationName])) {
                    $items = array_values($data[$relationName]); // Normalize keys
                    
                    \Log::info("approveVersion: Syncing relation {$relationName}", [
                        'items_count' => count($items),
                    ]);
                    
                    $this->syncRelation($teacher, $relationName, $items);
                } else {
                    \Log::info("approveVersion: No data for relation {$relationName}");
                }
            }
            
            // Notify the teacher
            if ($version->teacher->user) {
                $version->teacher->user->notify(new \App\Notifications\TeacherProfileApproved($version));
            }
            
            \Log::info('approveVersion: Complete');
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
            $cleanData = collect($item)
                ->filter(function ($value, $key) use ($fillable, $relatedKeyName) {
                    // Exclude id, virtual fields starting with _, and non-fillable fields
                    return !str_starts_with($key, '_') 
                        && $key !== $relatedKeyName 
                        && $key !== 'id'  // Explicitly exclude 'id' 
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

            if (!empty($itemId)) {
                // UPDATE existing record
                $keepIds[] = $itemId;
                
                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                    // Use find() and update for more reliable updating
                    $existingRecord = $relatedModel::find($itemId);
                    if ($existingRecord) {
                        $existingRecord->fill($cleanData);
                        $existingRecord->save();
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
                    \Log::info("Created HasMany record", ['new_id' => $newRecord->$relatedKeyName]);
                } elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\MorphToMany ||
                          $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                    $newModel = $relatedModel::create($cleanData);
                    $pivotData = Arr::only($item, ['author_role', 'sort_order', 'is_corresponding']);
                    $relation->attach($newModel->$relatedKeyName, $pivotData);
                    $keepIds[] = $newModel->$relatedKeyName;
                    \Log::info("Created MorphToMany record", ['new_id' => $newModel->$relatedKeyName]);
                }
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
     * Reject a version
     */
    public function rejectVersion(TeacherVersion $version, string $remarks): void
    {
        $version->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ]);
        
        // Notify the teacher
        if ($version->teacher->user) {
            $version->teacher->user->notify(new \App\Notifications\TeacherProfileRejected($version, $remarks));
        }
    }

    /**
     * Activate any approved version (rollback feature)
     * This allows restoring the teacher profile to any previous version's state
     */
    public function activateVersion(TeacherVersion $version): void
    {
        // Only allow activating approved versions
        if ($version->status !== 'approved') {
            throw new \Exception('Only approved versions can be activated. Please approve this version first.');
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

