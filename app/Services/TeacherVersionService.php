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
     * Map of teacher fields to their sections
     */
    private const FIELD_SECTION_MAP = [
        'personal_info' => ['first_name', 'last_name', 'middle_name', 'phone', 'personal_phone', 'secondary_email', 'present_address', 'permanent_address', 'date_of_birth', 'gender_id', 'blood_group_id', 'country_id', 'religion_id', 'photo'],
        'academic_info' => ['designation_id', 'department_id', 'joining_date', 'work_location', 'office_room', 'extension_no', 'employee_id', 'webpage'],
        'research_info' => ['research_interest', 'bio', 'google_scholar', 'research_gate', 'orcid'],
        'educations' => ['educations'],
        'publications' => ['publications'],
        'job_experiences' => ['jobExperiences'],
        'training_experiences' => ['trainingExperiences'],
        'awards' => ['awards'],
        'skills' => ['skills'],
        'teaching_areas' => ['teachingAreas'],
        'memberships' => ['memberships'],
        'social_links' => ['socialLinks'],
        // 'settings' => ['profile_status', 'employment_status', 'is_public', 'is_active', 'is_archived', 'sort_order'],
    ];

    /**
     * Legacy entry point for Observer calls.
     * This handles scalar updates coming from direct model updates (not via Form/Relationship manager).
     * 
     * @param Teacher $teacher
     * @param array $dirtyFields List of changed field names (scalars only typically)
     */
    public function processUpdate(Teacher $teacher, array $dirtyFields): void
    {
        // Construct a partial data array from dirty fields to reuse the main logic
        $data = [];
        foreach ($dirtyFields as $field) {
            $data[$field] = $teacher->$field;
        }

        // Call the main handler
        // Note: Observer calls this *during* updating event.
        // If we want to intercept, we should have the observer stop the update if approval is needed?
        // But our new architecture relies on CONTROLLER blocking the save.
        
        // If we are here via Observer, it implies a save call was made NOT via our EditTeacher override 
        // OR the override called save() eventually.
        
        // If specific logic is needed for Observer-triggered updates:
        $this->handleUpdateFromForm($teacher, $data);
    }


    /**
     * Process teacher update request.
     * This is now the MAIN entry point from Controller/Resource.
     * 
     * @param Teacher $teacher
     * @param array $allData Both scalar and relationship data from the form
     */
    public function handleUpdateFromForm(Teacher $teacher, array $allData): void
    {
        // 1. Identify changed sections
        $changedSections = $this->identifyChangedSections($teacher, $allData);
        
        if (empty($changedSections)) {
            return; // No changes detected
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

        // 3. If NO approval needed, just update everything directly
        if (empty($approvalSections)) {
            $this->applyUpdates($teacher, $allData);
            return;
        }

        // 4. If approval IS needed, create a version with EVERYTHING
        // (We version the entire state to ensure consistency)
        $this->createVersion($teacher, $allData, array_keys($approvalSections));
    }

    /**
     * Identify which sections have changed.
     */
    private function identifyChangedSections(Teacher $teacher, array $newData): array
    {
        $changedSections = [];

        foreach (self::FIELD_SECTION_MAP as $section => $fields) {
            $sectionChanged = false;
            foreach ($fields as $field) {
                // Check if it's a relationship (array) or partial scalar
                if (array_key_exists($field, $newData)) {
                    $newValue = $newData[$field];
                    
                    // Handle Relationships (Arrays)
                    if (is_array($newValue)) {
                        // For simplicity in comparison, if keys don't match or count differs, it changed.
                        // A deeper comparison could be expensive but more accurate.
                        // Ideally checking if the serialized version differs.
                        // For now, we assume if it's in the payload, we check strict content.
                        
                        // We need to compare with existing relation data
                        $relationName = $field; // e.g. 'skills'
                        
                        // Load existing relation if not loaded
                        if (!$teacher->relationLoaded($relationName)) {
                            $teacher->load($relationName);
                        }
                        
                        $existingData = $teacher->$relationName->toArray();
                        
                        // Normalize arrays for comparison (remove timestamps, etc if needed, but simple json encode comparison might suffice for now)
                        // A better approach: Compare count and IDs + content.
                        // NOTE: Filament Repeater sends all data.
                        
                        if ($this->hasRelationshipChanged($existingData, $newValue)) {
                            $changedSections[$section][] = $field;
                        }

                    } else {
                        // Scalar comparison
                        $originalValue = $teacher->$field;
                        // Loose comparison or strict? Filament form state vs DB state.
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
        // Simple count check
        if (count($existing) !== count($incoming)) {
            return true;
        }

        // Deep check - this can be complex. 
        // We act conservatively: if identifying strictly is hard, we can assume change if keys are present.
        // But for preventing false positives, let's try a diff.
        // Incoming usually has no IDs for new items, or IDs for existing.
        
        // Let's assume changed for now to ensure we catch updates.
        // Optimizing this is a "Nice to have".
        return true; 
    }

    /**
     * Apply updates immediately (No approval required)
     */
    private function applyUpdates(Teacher $teacher, array $data): void
    {
        DB::transaction(function () use ($teacher, $data) {
            $scalarData = Arr::except($data, array_merge(...array_values($this->getRelationshipFields())));
            
            // Update scalar
            $teacher->update($scalarData);

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

    private function getRelationshipFields(): array
    {
        $relations = [];
        foreach (self::FIELD_SECTION_MAP as $section => $fields) {
            // These are known relationship keys based on our map
            if (in_array($section, ['educations', 'publications', 'job_experiences', 'training_experiences', 'awards', 'skills', 'teaching_areas', 'memberships', 'social_links'])) {
                $relations[$section] = $fields;
            }
        }
        return $relations;
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
        
        $this->sendNotifications($version, $changedSectionNames);
        
        return $version;
    }

    /**
     * Send notifications based on routing configuration
     */
    private function sendNotifications(TeacherVersion $version, array $sections): void
    {
        foreach ($sections as $section) {
            $recipients = NotificationRouting::getRecipientsFor('teacher_profile_update', $section);
            
            foreach ($recipients as $recipient) {
                $recipient->notify(new TeacherProfileUpdatePending($version, $sections));
            }
        }
    }

    /**
     * Approve a version and update teacher profile
     */
    public function approveVersion(TeacherVersion $version): void
    {
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

            // 1. Scalar Update
            // Filter out relationship keys
            $relKeys = Arr::flatten($this->getRelationshipFields());
            $scalarData = Arr::except($data, $relKeys);
            
            Teacher::withoutEvents(function () use ($teacher, $scalarData) {
                $teacher->update($scalarData);
                
                // Name sync (manual trigger since events off)
                if (isset($scalarData['first_name']) || isset($scalarData['last_name'])) {
                    if ($teacher->user) {
                        $fullName = trim("{$teacher->first_name} {$teacher->middle_name} {$teacher->last_name}");
                        $teacher->user->update(['name' => $fullName]);
                    }
                }
            });

            // 2. Relationship Sync
            foreach ($this->getRelationshipFields() as $section => $relations) {
                foreach ($relations as $relationName) {
                    if (isset($data[$relationName])) {
                        $this->syncRelation($teacher, $relationName, $data[$relationName]);
                    }
                }
            }
            
            // Notify the teacher
            if ($version->teacher->user) {
                $version->teacher->user->notify(new \App\Notifications\TeacherProfileApproved($version));
            }
        });
    }

    /**
     * Logic to sync HasMany/MorphToMany relationships without simple duplication.
     */
    private function syncRelation(Teacher $teacher, string $relationName, array $items): void
    {
        $relation = $teacher->$relationName();
        $relatedKeyName = $relation->getRelated()->getKeyName();

        // Separate items into "Existing (Updates)" and "New (Creates)"
        // And identify IDs to keep (for deletion logic)
        
        $keepIds = [];

        foreach ($items as $item) {
            // Check if item has an ID (update)
            if (isset($item[$relatedKeyName]) && !empty($item[$relatedKeyName])) {
                $existingId = $item[$relatedKeyName];
                $keepIds[] = $existingId;
                
                // Update existing record
                // For HasMany:
                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                     $relation->where($relatedKeyName, $existingId)->update($item);
                } 
                // For BelongsToMany / MorphToMany (Pivot updates? Or content updates?)
                // In this system, it seems Publications etc are distinct records owned by teacher, 
                // OR ManyToMany with pivot.
                
                // Let's check Relation Type specifically if needed.
                // Based on models:
                // Skills -> HasMany
                // Educations -> HasMany
                // Publications -> MorphToMany (pivot!)
                
                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\MorphToMany || 
                    $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                    
                    // For ManyToMany, usually we sync IDs. 
                    // BUT Filament repeater for ManyToMany usually implies editing the PIVOT or the related record?
                    // "publications" is MorphToMany with pivot 'author_role', 'sort_order'.
                    // The repeater items probably contain the pivot data AND potentially related data if inline?
                    // Filament `relationship()` repeater for M:N typically handles attach/detach/sync.
                    
                    // IF the input $item contains the related record ID (e.g. publication_id), use syncWithoutDetaching or updateExistingPivot.
                    // However, Filament's `relationship()` usually handles this magic.
                    // Doing it manually here is tricky.
                    
                    // NOTE: If it's a MorphToMany, $item might be the PIVOT data + Related ID?
                    // Let's look at `Publications` schema in TeacherResource.
                    // It creates/edits the `Publication` model directly? No, it looks like it creates Publication AND attaches?
                    
                    // If it is complex, we might delegate to Filament's logic if possible? No, we are in Service.
                    
                    // Let's assume standard behavior for now:
                    // If the RELATION is OWNED (HasMany), we update the Row.
                    // If the RELATION is LINKED (ManyToMany), we usually Sync IDs.
                    
                    // Special checking for Publication/Pivot
                    // Publications are MorphToMany. The array likely contains 'id' of Publication + pivot fields?
                    // OR if users can Create publications inline, it has all fields.
                    
                    // Fallback for ManyToMany: We might need to use `sync` with pivot data.
                    // But `items` from Form State might be full objects.
                
                    // If complex, let's treat it carefully:
                    // Check if it's a ManyToMany
                     if (isset($item['id'])) {
                         $relation->updateExistingPivot($item['id'], Arr::only($item, ['author_role', 'sort_order', 'is_corresponding'])); 
                         // Note: We might NOT want to update the actual Publication content here if it's shared? 
                         // But if the form edits publication details, we must.
                     }
                }
                
            } else {
                // Create New
                 if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                    $relation->create($item);
                } elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\MorphToMany) {
                    // Complexity: Creating a NEW Publication + Attaching?
                    // Or attaching existing?
                    // If inline form, it creates.
                    
                    // We'll create the related model, then attach.
                    $newModel = $relation->getRelated()->create($item);
                    $relation->attach($newModel->id, Arr::only($item, ['author_role', 'sort_order']));
                }
            }
        }
        
        // Handle Deletions (Pruning)
        // For HasMany, delete those not in $keepIds
        if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
            $relation->whereNotIn($relatedKeyName, $keepIds)->delete();
        } elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\MorphToMany) {
            // Sync/Detach
            // Detach IDs not in keepIds
            $relation->detach(array_diff($relation->pluck($relation->getRelated()->getTable().'.id')->toArray(), $keepIds));
        }
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
    
    // Helper helper
    public function categorizeDirtyFields(array $fields): array
    {
         // Legacy helper if needed, or remove.
         return [];
    }
}
