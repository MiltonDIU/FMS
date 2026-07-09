<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membership extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'membership_organization_id',
        'membership_type_id',
        'membership_id',
        'record_type',   // 'membership' | 'affiliation'
        'position',      // specific role within the org
        'scope',         // 'local' | 'national' | 'international'
        'url',           // verification / profile link
        'start_date',
        'end_date',
        'status',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'is_active'   => 'boolean',
    ];

    /**
     * Default attribute values.
     */
    protected $attributes = [
        'record_type' => 'membership',
        'status'      => 'active',
        'is_active'   => true,
    ];

    /**
     * Get the teacher that owns the membership.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the organization for this membership.
     */
    public function membershipOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'membership_organization_id');
    }

    /**
     * Get the type for this membership.
     */
    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class);
    }
}
