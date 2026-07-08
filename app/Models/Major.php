<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Major extends Model
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

        if ($existing) {
            // Auto-approval logic:
            // If it is inactive AND the current teacher is not the creator, auto-approve it!
            if (!$existing->is_active && $teacherId && $existing->created_by !== $teacherId) {
                $existing->update([
                    'is_active'   => true,
                    'approved_by' => auth()->id(), // Approved by the current user
                ]);
            }
            return $existing;
        }

        // 2. Otherwise create a new inactive record
        return self::create([
            'name'       => $name,
            'is_active'  => false,
            'created_by' => $teacherId,
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
}
