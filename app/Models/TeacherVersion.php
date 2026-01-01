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
    public static bool $skipActivation = false;

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Handle is_active changes (for rollback feature)
        static::updating(function (TeacherVersion $version) {
            if (self::$skipActivation) {
                return;
            }

            // Check if is_active is being changed from false to true
            if ($version->isDirty('is_active') && $version->is_active === true) {
                // For rollback, status must be 'approved' or 'partially_approved'
                if (!in_array($version->status, ['approved', 'partially_approved', 'completed'])) {
                    throw new \Exception('Only approved/completed versions can be activated.');
                }

                self::$skipActivation = true;

                try {
                    // Deactivate all other versions for this teacher
                    static::where('teacher_id', $version->teacher_id)
                        ->where('id', '!=', $version->id)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);

                    // Apply ALL version data to teacher profile (rollback = complete restore)
                    app(TeacherVersionService::class)->applyVersionData($version);
                } finally {
                    self::$skipActivation = false;
                }
            }
        });
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


