<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An address the system no longer sends to, and why.
 *
 * Checked in three places, from the most informative to the last resort:
 * TeacherActivationService::queueFor skips it before anything is queued (the
 * delivery report then shows "skipped" with the reason), the two send jobs
 * check again for mail queued before the address was added, and the
 * BlockSuppressedEmails listener stops anything else — approval notices,
 * welcome mail — at the mailer itself.
 *
 * Removing an entry is how an address is allowed again, once corrected.
 */
class EmailSuppression extends Model
{
    public const REASON_BOUNCED = 'bounced';

    public const REASON_INVALID = 'invalid';

    public const REASON_COMPLAINT = 'complaint';

    public const REASON_OTHER = 'other';

    public const REASONS = [
        self::REASON_BOUNCED => 'Bounced',
        self::REASON_INVALID => 'Fake / does not exist',
        self::REASON_COMPLAINT => 'Asked to stop / marked as spam',
        self::REASON_OTHER => 'Other',
    ];

    protected $fillable = [
        'email',
        'reason',
        'note',
        'teacher_id',
        'email_batch_recipient_id',
        'added_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $suppression): void {
            $suppression->added_by ??= auth()->id();

            // Link it to the teacher it belongs to, so the list reads as people
            // rather than bare addresses. Teacher mail goes to the account's
            // address; the teachers table has no email column of its own.
            $suppression->teacher_id ??= Teacher::query()
                ->whereHas('user', fn ($q) => $q->where('email', $suppression->email))
                ->value('id');
        });
    }

    public static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = static::normalize($value);
    }

    public static function isSuppressed(?string $email): bool
    {
        $email = static::normalize($email);

        return $email !== '' && static::query()->where('email', $email)->exists();
    }

    /**
     * The skip reason a send records for this address, or null if it may be sent to.
     */
    public static function skipReasonFor(?string $email): ?string
    {
        $email = static::normalize($email);

        if ($email === '') {
            return null;
        }

        $reason = static::query()->where('email', $email)->value('reason');

        return $reason === null
            ? null
            : 'blocked address (' . strtolower(self::REASONS[$reason] ?? $reason) . ')';
    }

    /**
     * Put an address on the list. An address already there keeps its first
     * entry — the original reason and who recorded it are the useful part.
     */
    public static function suppress(
        string $email,
        string $reason = self::REASON_BOUNCED,
        ?string $note = null,
        ?EmailBatchRecipient $recipient = null,
    ): self {
        return static::query()->firstOrCreate(
            ['email' => static::normalize($email)],
            [
                'reason' => $reason,
                'note' => $note,
                'teacher_id' => $recipient?->teacher_id,
                'email_batch_recipient_id' => $recipient?->id,
            ],
        );
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(EmailBatchRecipient::class, 'email_batch_recipient_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
