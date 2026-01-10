<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentiveLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'publication_incentive_id',
        'changed_by',
        'action',
        'changes',
        'remarks',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    /**
     * Get the publication incentive.
     */
    public function publicationIncentive(): BelongsTo
    {
        return $this->belongsTo(PublicationIncentive::class);
    }

    /**
     * Get the user who made the change.
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get action label.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'Created',
            'updated' => 'Updated',
            'approved' => 'Approved',
            'paid' => 'Paid',
            'pending' => 'Set to Pending',
            default => ucfirst($this->action),
        };
    }
}
