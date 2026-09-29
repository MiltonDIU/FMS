<?php

namespace App\Models;

use App\Services\TeacherVersionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeacherVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'version_number',
        'data',
        'previous_data',
        'replaced_data',
        'change_summary',
        'status',
        'is_active',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_remarks',
        // Section-level approval fields
        'approved_sections',
        'pending_sections',
        'rejected_sections',
        'section_remarks',
        'changed_sections',
    ];

    protected $casts = [
        'data' => 'array',
        'previous_data' => 'array',
        'replaced_data' => 'array',
        'is_active' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        // Section-level approval casts
        'approved_sections' => 'array',
        'pending_sections' => 'array',
        'rejected_sections' => 'array',
        'section_remarks' => 'array',
        'changed_sections' => 'array',
    ];

    /**
     * Flag to prevent recursive observer calls
     */
    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
    }

    // ==========================================
    // Section-Level Approval Helper Methods
    // ==========================================

    /**
     * Check if a specific section is approved
     */
    public function isSectionApproved(string $section): bool
    {
        return in_array($section, $this->approved_sections ?? []);
    }

    /**
     * Check if a specific section is pending
     */
    public function isSectionPending(string $section): bool
    {
        return in_array($section, $this->pending_sections ?? []);
    }

    /**
     * Check if a specific section is rejected
     */
    public function isSectionRejected(string $section): bool
    {
        return in_array($section, $this->rejected_sections ?? []);
    }

    /**
     * Check if all sections have been decided (approved or rejected)
     */
    public function isFullyDecided(): bool
    {
        return empty($this->pending_sections);
    }

    /**
     * Check if all changed sections are approved
     */
    public function isFullyApproved(): bool
    {
        return $this->isFullyDecided() && empty($this->rejected_sections);
    }

    /**
     * Get remark for a specific section
     */
    public function getSectionRemark(string $section): ?string
    {
        return ($this->section_remarks ?? [])[$section] ?? null;
    }

    /**
     * What a decided section replaced: its state when it was approved or
     * rejected, else when the change was submitted. Null when neither was
     * recorded — versions created before the history was kept. A pending
     * section has no stored "before"; compare it with the live profile.
     */
    public function beforeStateFor(string $section): ?array
    {
        return ($this->replaced_data ?? [])[$section]
            ?? ($this->previous_data ?? [])[$section]
            ?? null;
    }

    /**
     * Whether the version holds the whole profile rather than only the
     * sections it changed: every repeated section is present in its data.
     */
    public function isFullSnapshot(): bool
    {
        $data = $this->data ?? [];

        foreach (TeacherVersionService::RELATION_NAMES as $relation) {
            if (! array_key_exists($relation, $data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the profile can be rolled back to this version: an approved
     * one (it holds the whole submitted form) or a full snapshot.
     */
    public function isRestorable(): bool
    {
        return in_array($this->status, ['approved', 'partially_approved', 'completed'], true)
            || ($this->status === 'applied_directly' && $this->isFullSnapshot());
    }

    /** Whether this version is about the given user's own profile. */
    public function belongsToUser(?User $user): bool
    {
        $teacherId = $user?->teacher?->id;

        return $teacherId !== null && (int) $this->teacher_id === (int) $teacherId;
    }

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Get the teacher that owns this version.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the user who submitted this version.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user who reviewed this version.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope a query to only include pending versions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include approved versions.
     */
    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['approved', 'partially_approved', 'completed']);
    }

    /**
     * Scope to changes published without approval.
     */
    public function scopeAppliedDirectly($query)
    {
        return $query->where('status', 'applied_directly');
    }

    /**
     * Scope a query to only include active versions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for versions with pending sections
     */
    public function scopeHasPendingSections($query)
    {
        return $query->whereJsonLength('pending_sections', '>', 0);
    }
}
