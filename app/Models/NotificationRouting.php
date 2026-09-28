<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationRouting extends Model
{
    protected $fillable = [
        'trigger_type',
        'trigger_sections', // Changed to plural for array
        'recipient_type',
        'recipient_identifiers', // Changed to plural for array
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'trigger_sections' => 'array', // Cast to array
        'recipient_identifiers' => 'array', // Cast to array
    ];

    /**
     * Get recipients for a specific trigger
     *
     * $teacher is whose profile the trigger is about. A "department_head"
     * routing resolves only against it — the heads of that teacher's own
     * department — so without a teacher it resolves to nobody.
     */
    public static function getRecipientsFor(string $triggerType, ?string $section = null, ?Teacher $teacher = null): Collection
    {
        $query = static::where('trigger_type', $triggerType)
            ->where('is_active', true);

        // If section specified, filter routings that include this section
        if ($section) {
            $query->where(function ($q) use ($section) {
                $q->whereJsonContains('trigger_sections', $section)
                  ->orWhereNull('trigger_sections'); // Include "all sections" rules
            });
        }

        $routings = $query->get();
        $recipients = collect();

        foreach ($routings as $routing) {
            match ($routing->recipient_type) {
                'role' => $recipients = $recipients->merge(
                    // Support multiple roles
                    collect((array) ($routing->recipient_identifiers ?? []))->flatMap(fn($role) =>
                        User::role($role)->get()
                    )
                ),
                'user' => $recipients = $recipients->merge(
                    // Support multiple users
                    User::whereIn('id', (array) ($routing->recipient_identifiers ?? []))->get()
                ),
                'department_head' => $recipients = $recipients->merge(
                    static::departmentHeadsFor($teacher)
                ),
                default => null
            };
        }

        return $recipients->filter()->unique('id');
    }

    /**
     * The heads of a teacher's home department.
     *
     * Holding approve:own-department-teacher is not enough on its own: every
     * head holds it, so the permission alone let the head of one department
     * approve changes to another department's teachers. The user must also
     * hold an active, unended administrative role over the teacher's home
     * department. The teacher is left out even when they are a head there, so
     * nobody approves a change to their own profile.
     */
    protected static function departmentHeadsFor(?Teacher $teacher): Collection
    {
        if (! $teacher?->department_id) {
            return collect();
        }

        return User::permission('approve:own-department-teacher')
            ->whereIn('id', DB::table('administrative_role_user')
                ->select('user_id')
                ->where('department_id', $teacher->department_id)
                ->where('is_active', true)
                ->whereNull('end_date')
                ->whereNull('deleted_at'))
            ->when($teacher->user_id, fn ($query, $userId) => $query->whereKeyNot($userId))
            ->get();
    }

    /**
     * Scope to get active routings
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by trigger type
     */
    public function scopeForTrigger($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    /**
     * Scope to filter by section
     */
    public function scopeForSection($query, string $section)
    {
        return $query->whereJsonContains('trigger_sections', $section);
    }
}
