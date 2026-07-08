<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationalInstitution extends Model
{
    protected $fillable = ['name', 'is_active', 'created_by', 'approved_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Case-insensitive find or create with auto-approval logic.
     *
     * @param string $name
     * @param int|null $teacherId
     * @return self
     */
    public static function findOrCreateWithAutoApproval(string $name, ?int $teacherId): self
    {
        $name = trim($name);
        
        // 1. Search for case-insensitive match
        $existing = self::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

        // Check if current user is admin/staff (doesn't have a teacher account or has admin permission)
        $isAdmin = auth()->check() && !auth()->user()->hasRole('teacher');

        if ($existing) {
            // Auto-approval logic:
            // If it is inactive, and the request is by a different teacher OR an admin, activate it!
            if (!$existing->is_active && ($isAdmin || ($teacherId && $existing->created_by !== $teacherId))) {
                $existing->update([
                    'is_active'   => true,
                    'approved_by' => auth()->id(),
                ]);
            }
            return $existing;
        }

        // 2. Otherwise create a new record
        return self::create([
            'name'        => $name,
            'is_active'   => $isAdmin, // Admins create active records directly!
            'created_by'  => $teacherId,
            'approved_by' => $isAdmin ? auth()->id() : null,
        ]);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'educations', 'educational_institution_id', 'teacher_id')->distinct();
    }
}
