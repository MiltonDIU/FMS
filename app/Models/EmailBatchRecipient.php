<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One person in one batch, and what became of their copy.
 *
 * Every state change goes through a method here rather than being assigned at
 * the call site, because these rows are written from a queue worker: the jobs
 * that update them run outside any request, often minutes apart, and the pixel
 * and link routes update them from a stranger's browser.
 */
class EmailBatchRecipient extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /** For the status filters and badges, in the order they read best. */
    public const STATUSES = [
        self::STATUS_SENT => 'Sent',
        self::STATUS_QUEUED => 'Still queued',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_SKIPPED => 'Skipped',
    ];

    protected $fillable = [
        'email_batch_id',
        'teacher_id',
        'teacher_name',
        'email',
        'status',
        'skip_reason',
        'error',
        'queued_at',
        'sent_at',
        'failed_at',
        'opened_at',
        'clicked_at',
        'open_count',
        'click_count',
        'track_token',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'open_count' => 'integer',
        'click_count' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(EmailBatch::class, 'email_batch_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    /**
     * Handed to the mail server without complaint.
     */
    public function markSent(): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'sent_at' => Carbon::now(),
            'failed_at' => null,
            'error' => null,
        ])->save();
    }

    /**
     * Refused, or the job threw before it got that far.
     *
     * The message is trimmed because a mail transport exception can carry the
     * server's whole reply, and the column is read in a table cell.
     */
    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'failed_at' => Carbon::now(),
            'error' => Str::limit($error, 1000),
        ])->save();
    }

    /**
     * Never sent, and why — the part that used to vanish into a notification.
     */
    public function markSkipped(string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_SKIPPED,
            'skip_reason' => $reason,
        ])->save();
    }

    /**
     * The tracking pixel was fetched.
     *
     * opened_at keeps the first fetch and is never overwritten, so "when did
     * they read it" is not quietly replaced by "when did they last look at it".
     */
    public function registerOpen(): void
    {
        $this->forceFill([
            'opened_at' => $this->opened_at ?? Carbon::now(),
            'open_count' => $this->open_count + 1,
        ])->save();
    }

    public function registerClick(): void
    {
        $this->forceFill([
            'clicked_at' => $this->clicked_at ?? Carbon::now(),
            'click_count' => $this->click_count + 1,

            // A click is a stronger proof of reading than the pixel, which the
            // recipient's mail client may never fetch. Without this, somebody
            // who clicked straight through with images off would still be
            // counted among those who had not read it.
            'opened_at' => $this->opened_at ?? Carbon::now(),
        ])->save();
    }

    /**
     * Delivered but unread — the people a follow-up is aimed at.
     */
    public function scopeUnopened(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT)->whereNull('opened_at');
    }

    public function statusColour(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => $this->opened_at ? 'success' : 'info',
            self::STATUS_QUEUED => 'warning',
            self::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }
}
