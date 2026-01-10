<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class PublicationIncentive extends Model
{
    use HasFactory;

    protected $fillable = [
        'publication_id',
        'total_amount',
        'status',
        'approved_by',
        'paid_by',
        'approved_at',
        'paid_at',
        'remarks',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        // Log creation
        static::created(function (PublicationIncentive $incentive) {
            // Get author incentive breakdown
            $authorBreakdown = $incentive->publication->teachers->map(fn($t) => [
                'name' => $t->full_name,
                'role' => $t->pivot->author_role,
                'amount' => (float) ($t->pivot->incentive_amount ?? 0),
            ])->toArray();

            $incentive->logs()->create([
                'changed_by' => Auth::id(),
                'action' => 'created',
                'changes' => [
                    'total_amount' => $incentive->total_amount,
                    'status' => $incentive->status,
                    'author_breakdown' => $authorBreakdown,
                ],
                'remarks' => $incentive->remarks,
            ]);
        });

        // Log updates
        static::updated(function (PublicationIncentive $incentive) {
            $changes = [];

            if ($incentive->isDirty('total_amount')) {
                $changes['total_amount'] = [
                    'old' => $incentive->getOriginal('total_amount'),
                    'new' => $incentive->total_amount,
                ];
            }

            if ($incentive->isDirty('status')) {
                $changes['status'] = [
                    'old' => $incentive->getOriginal('status'),
                    'new' => $incentive->status,
                ];
            }

            if ($incentive->isDirty('remarks')) {
                $changes['remarks'] = [
                    'old' => $incentive->getOriginal('remarks'),
                    'new' => $incentive->remarks,
                ];
            }

            // Always include current author breakdown on status change
            if ($incentive->isDirty('status')) {
                $changes['author_breakdown'] = $incentive->publication->teachers->map(fn($t) => [
                    'name' => $t->full_name,
                    'role' => $t->pivot->author_role,
                    'amount' => (float) ($t->pivot->incentive_amount ?? 0),
                ])->toArray();
            }

            if (!empty($changes)) {
                $action = 'updated';
                if ($incentive->isDirty('status')) {
                    $action = $incentive->status; // approved, paid, etc.
                }

                $incentive->logs()->create([
                    'changed_by' => Auth::id(),
                    'action' => $action,
                    'changes' => $changes,
                    'remarks' => $incentive->remarks,
                ]);
            }
        });
    }

    /**
     * Get the publication.
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * Get the user who approved.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who paid.
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Get the incentive logs.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(IncentiveLog::class)->orderByDesc('created_at');
    }

    /**
     * Calculate sum of author incentive amounts.
     */
    public function getAuthorsIncentiveSumAttribute(): float
    {
        return $this->publication
            ->teachers()
            ->get()
            ->sum(fn($teacher) => (float) $teacher->pivot->incentive_amount ?? 0);
    }

    /**
     * Validate total matches authors sum.
     */
    public function validateTotalMatchesAuthors(): bool
    {
        return bccomp((string) $this->total_amount, (string) $this->authors_incentive_sum, 2) === 0;
    }
}
