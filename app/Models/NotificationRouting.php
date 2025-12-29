<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
     */
    public static function getRecipientsFor(string $triggerType, ?string $section = null): Collection
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
                    User::permission('approve:own-department-teacher')->get()
                ),
                default => null
            };
        }

        return $recipients->filter()->unique('id');
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
