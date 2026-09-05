<?php

namespace App\Services;

use App\Jobs\SendCustomTemplatedEmailJob;
use App\Jobs\SendTeacherActivationEmailJob;
use App\Models\EmailBatchRecipient;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Issues and redeems the one-time links that let migrated teachers in.
 *
 * Every account came over from the old system with an unusable password, so this
 * link is the only door. That makes it worth as much as a password while it is
 * live, which is why it expires, is single-use, and is refused for any teacher
 * who would not be allowed to sign in normally.
 */
class TeacherActivationService
{
    /**
     * Default validity when the caller does not say.
     *
     * Long enough that a teacher who reads mail a few days late is not locked
     * out, short enough that an old message in a mailbox stops being a way in.
     */
    public const DEFAULT_VALIDITY_DAYS = 7;

    /**
     * Teachers who still need to activate.
     *
     * Two exclusions carry the request's intent: anyone who has already chosen a
     * password and confirmed their address is done, and re-mailing them would
     * only be confusing. The rest of the conditions mirror
     * User::canAccessPanel(), because a link that signs someone in must not
     * reach an account that is not allowed to sign in — otherwise it becomes a
     * way around the access rules rather than a way through them.
     */
    public function pendingQuery(): Builder
    {
        return Teacher::query()
            ->with(['user', 'department', 'designation'])
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where('login_allowed', true)
            ->whereHas('user', function (Builder $q) {
                $q->whereNotNull('email')
                    ->where('is_active', true)
                    // Already onboarded: has a password of their own choosing
                    // and a confirmed address.
                    ->where(function (Builder $done) {
                        $done->whereNull('password_set_at')
                            ->orWhereNull('email_verified_at');
                    });
            })
            // An employment status that forbids login forbids activation too.
            ->where(function (Builder $q) {
                $q->whereDoesntHave('employmentStatus')
                    ->orWhereHas('employmentStatus', fn (Builder $s) => $s->where('allow_login', true));
            });
    }

    /**
     * Teachers skipped because they are already set up.
     */
    public function alreadyActiveQuery(): Builder
    {
        return Teacher::query()
            ->whereHas('user', fn (Builder $q) => $q
                ->whereNotNull('password_set_at')
                ->whereNotNull('email_verified_at'));
    }

    /**
     * Does this message rely on an activation link?
     *
     * The placeholder is the signal rather than the template key, so an
     * administrator who writes their own wording — or copies the activation
     * template into the send dialog and edits it — still gets a working link
     * instead of the literal text {activation_link} in the recipient's inbox.
     */
    public function needsActivationLink(string ...$parts): bool
    {
        foreach ($parts as $part) {
            if (str_contains($part, '{activation_link}')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Has this teacher already finished onboarding?
     *
     * Used to skip recipients when sending in bulk: someone with a password of
     * their own and a confirmed address does not need another way in, and
     * mailing them one would only be confusing.
     */
    public function isAlreadyActivated(Teacher $teacher): bool
    {
        $user = $teacher->user;

        return $user
            && $user->password_set_at !== null
            && $user->email_verified_at !== null;
    }

    /**
     * Mint a fresh token for a teacher and return the link to email them.
     *
     * A new token is issued on every send rather than reusing the stored one, so
     * an earlier email stops working the moment a newer one goes out. Whoever
     * holds the old message cannot sign in.
     */
    public function issueLink(Teacher $teacher, int $validityDays): string
    {
        $teacher->forceFill([
            'verification_token' => Str::random(64),
            'verification_token_expires_at' => Carbon::now()->addDays($validityDays),
            'verification_token_used_at' => null,
            'activation_email_sent_at' => Carbon::now(),
        ])->save();

        return route('teacher.activate', ['token' => $teacher->verification_token]);
    }

    /**
     * Queue one message for a teacher, choosing the right job for it.
     *
     * The send dialogs and the console command all come through here so a
     * template behaves the same wherever it is sent from. A message asking for
     * {activation_link} gets a freshly minted token; anything else goes out on
     * the ordinary path.
     *
     * $recipient is the row in the batch this message belongs to. It is handed
     * down rather than created here so that a skip is written to the same row
     * the send would have used: a recipient who was skipped has to stay in the
     * batch and say why, or the numbers stop adding up and the reason is lost.
     *
     * $skipActivated extends the activation email's rule to ordinary ones, for
     * the reminders that are only meant for teachers who have not signed in yet.
     * An activation message skips them whatever is asked, because a link that
     * signs somebody in has no business reaching an account that already has a
     * password.
     *
     * @return string  'activation', 'general', or why it was skipped
     */
    public function queueFor(
        Teacher $teacher,
        string $subject,
        string $body,
        int $validityDays,
        ?EmailBatchRecipient $recipient = null,
        bool $skipActivated = false,
    ): string {
        if (! $this->needsActivationLink($subject, $body)) {
            if ($skipActivated && $this->isAlreadyActivated($teacher)) {
                return $this->skip($recipient, 'already activated');
            }

            if (blank($teacher->user?->email) && blank($teacher->email)) {
                return $this->skip($recipient, 'no email');
            }

            SendCustomTemplatedEmailJob::dispatch($teacher, $subject, $body, $recipient?->id);

            return 'general';
        }

        if ($this->isAlreadyActivated($teacher)) {
            return $this->skip($recipient, 'already activated');
        }

        if (blank($teacher->user?->email)) {
            return $this->skip($recipient, 'no email');
        }

        // Minted before dispatching, so a queue failure cannot leave a live link
        // that nobody was ever told about.
        $link = $this->issueLink($teacher, $validityDays);

        SendTeacherActivationEmailJob::dispatch(
            $teacher,
            $subject,
            $body,
            $link,
            $validityDays,
            $recipient?->id,
        );

        return 'activation';
    }

    /**
     * Record a skip on the batch row and report it in the same breath.
     */
    protected function skip(?EmailBatchRecipient $recipient, string $reason): string
    {
        $recipient?->markSkipped($reason);

        return 'skipped: ' . $reason;
    }

    /**
     * Find the teacher a token belongs to, or null if it cannot be redeemed.
     *
     * Covers all three ways a token stops working — unknown, spent, expired —
     * with one answer, so the caller cannot accidentally tell a visitor which of
     * them applies.
     */
    public function resolveToken(string $token): ?Teacher
    {
        if (strlen($token) < 32) {
            return null;
        }

        $teacher = Teacher::query()
            ->with('user')
            ->where('verification_token', $token)
            ->first();

        if (! $teacher
            || $teacher->verification_token_used_at !== null
            || $teacher->verification_token_expires_at === null
            || $teacher->verification_token_expires_at->isPast()) {
            return null;
        }

        return $teacher;
    }

    /**
     * Spend the token and record that the address was proven.
     *
     * Clicking the link is proof the teacher reads that mailbox, which is
     * exactly what email_verified_at is for — and it has been null on every
     * account since the migration.
     */
    public function redeem(Teacher $teacher): void
    {
        $teacher->forceFill([
            'verification_token' => null,
            'verification_token_used_at' => Carbon::now(),
        ])->save();

        $user = $teacher->user;

        if ($user && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => Carbon::now()])->save();
        }
    }
}
