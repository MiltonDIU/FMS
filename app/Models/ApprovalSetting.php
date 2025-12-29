<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalSetting extends Model
{
    protected $fillable = [
        'section_key',
        'section_label',
        'requires_approval',
        'description',
        'fields',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
        'fields' => 'array',
    ];

    /**
     * Get sections that require approval
     */
    public static function getApprovalRequired(): array
    {
        return static::where('requires_approval', true)
            ->where('is_active', true)
            ->pluck('section_key')
            ->toArray();
    }

    /**
     * Check if a section requires approval
     */
    public static function requiresApproval(string $sectionKey): bool
    {
        return static::where('section_key', $sectionKey)
            ->where('requires_approval', true)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get all active sections ordered by sort_order
     */
    public static function getActiveSections()
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
