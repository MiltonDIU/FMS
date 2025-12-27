<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipOrganization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
        'activated_at',
        'activated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
    ];

    /**
     * Get the teacher who created this organization.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by');
    }

    /**
     * Get the teacher who activated this organization.
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'activated_by');
    }

    /**
     * Get the memberships for this organization.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Scope to get only active organizations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if duplicate name exists and activate if so.
     * This method is called after a new organization is created.
     */
    public static function checkAndActivateDuplicate(string $name, ?int $teacherId = null): void
    {
        $organizations = static::where('name', $name)->get();
        
        // If more than one organization with the same name exists, activate all
        if ($organizations->count() > 1) {
            static::where('name', $name)->update([
                'is_active' => true,
                'activated_at' => now(),
                'activated_by' => $teacherId,
            ]);
        }
    }
}
