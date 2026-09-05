<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One press of a send button, and everyone it addressed.
 *
 * The counts are deliberately not stored as columns. A recipient's state keeps
 * changing after the batch is written — a queued message is sent, a sent one is
 * opened days later — so a stored total would be wrong more often than right.
 * withStats() derives them in one query instead.
 */
class EmailBatch extends Model
{
    /** Where a batch was sent from. */
    public const SOURCE_INDIVIDUAL = 'individual';

    public const SOURCE_SELECTED = 'selected';

    public const SOURCE_FILTERED = 'filtered';

    public const SOURCE_CONSOLE = 'console';

    /** A follow-up to an earlier batch, aimed at the people it did not reach. */
    public const SOURCE_RESEND = 'resend';

    protected $fillable = [
        'subject',
        'body',
        'email_template_id',
        'template_name',
        'sent_by',
        'source',
        'filters',
        'uses_activation_link',
        'link_validity_days',
        'total_recipients',
    ];

    protected $casts = [
        'filters' => 'array',
        'uses_activation_link' => 'boolean',
        'link_validity_days' => 'integer',
        'total_recipients' => 'integer',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailBatchRecipient::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Start this teacher's row in the batch.
     *
     * The name and address are copied onto the row rather than read back
     * through the teacher, so the record still reads correctly after somebody
     * is renamed, given a new address, or removed altogether.
     */
    public function addRecipient(Teacher $teacher): EmailBatchRecipient
    {
        return $this->recipients()->create([
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->full_name,
            'email' => $teacher->user?->email ?? $teacher->email,
            'status' => EmailBatchRecipient::STATUS_QUEUED,
            'queued_at' => Carbon::now(),
            'track_token' => EmailBatchRecipient::newToken(),
        ]);
    }

    /**
     * Add the per-status counts every screen showing a batch needs.
     *
     * One query for all of them, rather than a count per column per row, which
     * is what makes a list of a hundred batches load at all.
     */
    public function scopeWithStats(Builder $query): Builder
    {
        return $query->withCount([
            'recipients as queued_count' => fn (Builder $q) => $q
                ->where('status', EmailBatchRecipient::STATUS_QUEUED),
            'recipients as sent_count' => fn (Builder $q) => $q
                ->where('status', EmailBatchRecipient::STATUS_SENT),
            'recipients as failed_count' => fn (Builder $q) => $q
                ->where('status', EmailBatchRecipient::STATUS_FAILED),
            'recipients as skipped_count' => fn (Builder $q) => $q
                ->where('status', EmailBatchRecipient::STATUS_SKIPPED),
            'recipients as opened_count' => fn (Builder $q) => $q
                ->whereNotNull('opened_at'),
            'recipients as clicked_count' => fn (Builder $q) => $q
                ->whereNotNull('clicked_at'),
        ]);
    }

    /**
     * Delivered but not yet read.
     *
     * The number the chasing is actually done from, so it is worked out in one
     * place rather than subtracted by hand on each screen.
     */
    public function unopenedCount(): int
    {
        return $this->recipients()
            ->where('status', EmailBatchRecipient::STATUS_SENT)
            ->whereNull('opened_at')
            ->count();
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_INDIVIDUAL => 'One teacher',
            self::SOURCE_SELECTED => 'Selected rows',
            self::SOURCE_FILTERED => 'Faculty / department filter',
            self::SOURCE_CONSOLE => 'Console command',
            self::SOURCE_RESEND => 'Follow-up to an earlier batch',
            default => $this->source,
        };
    }
}
