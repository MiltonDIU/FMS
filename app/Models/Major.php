<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Major extends Model
{
    use Concerns\RecordsActivity;
    use Concerns\RecordsCreator;

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
        $isAdmin = auth()->check() && !auth()->user()->holdsOnlyTeacherRole();

        // created_by holds a users id; see Concerns\RecordsCreator.
        $userId = static::creatorUserFor($teacherId);

        if ($existing) {
            // Auto-approval logic:
            // If it is inactive, and the request is by somebody other than whoever added it OR an admin, activate it!
            if (!$existing->is_active && ($isAdmin || ($userId && (int) $existing->created_by !== (int) $userId))) {
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
            'created_by'  => $userId,
            'approved_by' => $isAdmin ? auth()->id() : null,
        ]);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'educations', 'major_id', 'teacher_id')
            ->whereNull('teachers.deleted_at')
            ->where('teachers.is_archived', false)
            ->distinct();
    }
}
