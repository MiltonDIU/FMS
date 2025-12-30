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
    ];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
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

        // Handle is_active changes
        static::updating(function (TeacherVersion $version) {
            // Skip if activation logic is already being handled
            if (self::$skipActivation) {
                return;
            }

            // Check if is_active is being changed from false to true
            if ($version->isDirty('is_active') && $version->is_active === true) {
                // Only allow approved versions to be activated
                if ($version->status !== 'approved') {
                    throw new \Exception('Only approved versions can be activated.');
                }

                // Set flag to prevent recursive calls
                self::$skipActivation = true;

                try {
                    // Deactivate all other versions for this teacher
                    static::where('teacher_id', $version->teacher_id)
                        ->where('id', '!=', $version->id)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);

                    // Apply version data to teacher profile
                    app(TeacherVersionService::class)->applyVersionData($version);
                } finally {
                    self::$skipActivation = false;
                }
            }
        });
    }

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
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include active versions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

