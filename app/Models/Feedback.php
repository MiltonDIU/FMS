<?php

namespace App\Models;

use App\Notifications\FeedbackReceived;
use App\Notifications\FeedbackUpdated;
use App\Policies\FeedbackPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Something a user reported or asked for, and where it has got to.
 *
 * Every change goes through a method here rather than being assigned at the
 * call site, because each one has to do three things together: write the
 * message that records it, move the status and whose turn it is, and tell the
 * other side. A status changed without the message would leave the thread
 * unable to say who did it; one changed without the notification would leave
 * the teacher never knowing.
 *
 * "Resolved" is not the end. It asks the teacher to check, and only their
 * confirmation — or the team closing it — makes it closed. Saying it still
 * does not work puts it back in progress.
 */
class Feedback extends Model
{
    protected $table = 'feedback';

    /** Switches the teachers' Feedback menu on and off. */
    public const ENABLED_SETTING = 'feedback_enabled';

    /** Also email the submitter when the team answers, not only notify in the panel. */
    public const EMAIL_UPDATES_SETTING = 'feedback_email_updates';

    public const TYPES = [
        'bug' => 'Problem / error',
        'feature' => 'New feature',
        'improvement' => 'Improvement',
        'question' => 'Question',
        'other' => 'Other',
    ];

    public const STATUS_NEW = 'new';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_DECLINED = 'declined';

    /** In the order an item moves through them. */
    public const STATUSES = [
        self::STATUS_NEW => 'New',
        self::STATUS_IN_REVIEW => 'Under review',
        self::STATUS_PLANNED => 'Planned',
        self::STATUS_IN_PROGRESS => 'In progress',
        self::STATUS_RESOLVED => 'Done — please check',
        self::STATUS_CLOSED => 'Closed',
        self::STATUS_DECLINED => 'Will not be done',
    ];

    /** Nothing more is expected from anybody. */
    public const FINISHED = [self::STATUS_CLOSED, self::STATUS_DECLINED];

    public const PRIORITIES = [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    public const WAITING_ON_STAFF = 'staff';

    public const WAITING_ON_SUBMITTER = 'submitter';

    protected $fillable = [
        'user_id',
        'teacher_id',
        'submitter_name',
        'type',
        'subject',
        'message',
        'page_url',
        'attachments',
        'status',
        'priority',
        'waiting_on',
        'reopened_count',
        'resolved_at',
        'closed_at',
        'last_activity_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'reopened_count' => 'integer',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FeedbackMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public static function isEnabled(): bool
    {
        return filter_var(Setting::get(self::ENABLED_SETTING, true), FILTER_VALIDATE_BOOLEAN);
    }

    public static function emailsUpdates(): bool
    {
        return filter_var(Setting::get(self::EMAIL_UPDATES_SETTING, true), FILTER_VALIDATE_BOOLEAN);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED, true);
    }

    public function reference(): string
    {
        return '#' . $this->id;
    }

    public static function statusColour(?string $status): string
    {
        return match ($status) {
            self::STATUS_NEW => 'info',
            self::STATUS_IN_REVIEW, self::STATUS_PLANNED => 'primary',
            self::STATUS_IN_PROGRESS => 'warning',
            self::STATUS_RESOLVED, self::STATUS_CLOSED => 'success',
            self::STATUS_DECLINED => 'danger',
            default => 'gray',
        };
    }

    public static function priorityColour(?string $priority): string
    {
        return match ($priority) {
            'urgent' => 'danger',
            'high' => 'warning',
            'low' => 'gray',
            default => 'info',
        };
    }

    /**
     * A new item from a user, and word of it to everybody who answers them.
     *
     * @param  array{type:string,subject:string,message:string,page_url?:?string,attachments?:?array}  $data
     */
    public static function submit(User $user, array $data): self
    {
        $feedback = DB::transaction(fn (): self => static::create([
            'user_id' => $user->id,
            'teacher_id' => $user->teacher?->id,
            'submitter_name' => static::nameOf($user),
            'type' => $data['type'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'page_url' => $data['page_url'] ?? null,
            'attachments' => array_values($data['attachments'] ?? []) ?: null,
            'status' => self::STATUS_NEW,
            'priority' => 'normal',
            'waiting_on' => self::WAITING_ON_STAFF,
            'last_activity_at' => Carbon::now(),
        ]));

        $feedback->tellStaff('New feedback', $user);

        return $feedback;
    }

    /**
     * The team answers, moves the status, or both.
     *
     * An internal note changes nothing the submitter can see — not the status,
     * not whose turn it is — and tells nobody outside the team.
     */
    public function staffReply(User $author, ?string $body, ?string $status = null, bool $internal = false, array $attachments = []): FeedbackMessage
    {
        $status = $internal ? null : $status;
        $statusChanged = $status !== null && $status !== $this->status;

        $message = $this->record($author, $body, true, $internal, $statusChanged ? $status : null, $attachments);

        if ($internal) {
            return $message;
        }

        if ($statusChanged) {
            $this->moveTo($status);
        }

        $this->forceFill([
            'waiting_on' => $this->isFinished() ? null : self::WAITING_ON_SUBMITTER,
            'last_activity_at' => Carbon::now(),
        ])->save();

        $this->user?->notify(new FeedbackUpdated($this, $message));

        return $message;
    }

    /** The submitter adds to their own item while it is still open. */
    public function submitterReply(User $author, string $body, array $attachments = []): FeedbackMessage
    {
        $message = $this->record($author, $body, false, false, null, $attachments);

        $this->forceFill([
            'waiting_on' => self::WAITING_ON_STAFF,
            'last_activity_at' => Carbon::now(),
        ])->save();

        $this->tellStaff('New reply on feedback', $author);

        return $message;
    }

    /** The submitter checked the fix and it works. */
    public function confirmResolved(User $author, ?string $body = null): FeedbackMessage
    {
        $message = $this->record($author, filled($body) ? $body : 'Checked — it works. Thank you.', false, false, self::STATUS_CLOSED);

        $this->moveTo(self::STATUS_CLOSED);
        $this->forceFill(['waiting_on' => null, 'last_activity_at' => Carbon::now()])->save();

        $this->tellStaff('Feedback confirmed fixed', $author);

        return $message;
    }

    /** The submitter checked the fix and it does not work. Back to the team. */
    public function reopen(User $author, string $body, array $attachments = []): FeedbackMessage
    {
        $message = $this->record($author, $body, false, false, self::STATUS_IN_PROGRESS, $attachments);

        $this->moveTo(self::STATUS_IN_PROGRESS);
        $this->forceFill([
            'waiting_on' => self::WAITING_ON_STAFF,
            'reopened_count' => $this->reopened_count + 1,
            'last_activity_at' => Carbon::now(),
        ])->save();

        $this->tellStaff('Feedback reopened — still not working', $author);

        return $message;
    }

    protected function record(User $author, ?string $body, bool $staff, bool $internal, ?string $statusTo, array $attachments = []): FeedbackMessage
    {
        return $this->messages()->create([
            'user_id' => $author->id,
            'author_name' => static::nameOf($author),
            'is_staff' => $staff,
            'is_internal' => $internal,
            'body' => filled($body) ? $body : null,
            'status_from' => $statusTo !== null ? $this->status : null,
            'status_to' => $statusTo,
            'attachments' => array_values($attachments) ?: null,
        ]);
    }

    protected function moveTo(string $status): void
    {
        $this->forceFill([
            'status' => $status,
            'resolved_at' => $status === self::STATUS_RESOLVED ? Carbon::now() : $this->resolved_at,
            'closed_at' => in_array($status, self::FINISHED, true) ? Carbon::now() : null,
        ]);
    }

    protected function tellStaff(string $title, User $actor): void
    {
        static::handlers()
            ->reject(fn (User $user): bool => $user->is($actor))
            ->each(fn (User $user) => $user->notify(new FeedbackReceived($this, $title)));
    }

    /**
     * Everybody who answers feedback: holders of Update:Feedback, directly or
     * through a role. Queried by hand because Spatie's permission() scope
     * throws when the permission row does not exist yet.
     *
     * @return Collection<int, User>
     */
    public static function handlers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q
                ->whereHas('permissions', fn (Builder $p) => $p->where('name', FeedbackPolicy::UPDATE))
                ->orWhereHas('roles.permissions', fn (Builder $p) => $p->where('name', FeedbackPolicy::UPDATE)))
            ->get();
    }

    /** As the teacher list writes it, for a teacher; the account name otherwise. */
    public static function nameOf(User $user): string
    {
        return $user->teacher?->display_name ?? $user->name ?? $user->email ?? 'User';
    }

    public function scopeWaitingOnStaff(Builder $query): Builder
    {
        return $query->where('waiting_on', self::WAITING_ON_STAFF)->whereNotIn('status', self::FINISHED);
    }

    public function scopeWaitingOnSubmitter(Builder $query): Builder
    {
        return $query->where('waiting_on', self::WAITING_ON_SUBMITTER)->whereNotIn('status', self::FINISHED);
    }
}
