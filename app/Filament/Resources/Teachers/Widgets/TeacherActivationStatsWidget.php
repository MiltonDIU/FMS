<?php

namespace App\Filament\Resources\Teachers\Widgets;

use App\Models\EmailBatchRecipient;
use App\Models\EmailSuppression;
use App\Models\Teacher;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Where the activation emails stand: how many teachers were sent a link, how
 * many have finished, and what is holding up the rest.
 *
 * "Sent" is teachers.activation_email_sent_at, which TeacherActivationService
 * stamps every time it issues a link. It covers every send, including the ones
 * from before email batches were recorded — the batch tables only describe
 * sends since they existed, so they are used for what only they know (opens,
 * mail-server failures) and not for the headline number.
 *
 * Every teacher who was sent a link falls in exactly one of four states, so
 * activated + opened-link + waiting + expired adds up to the sent count:
 *   activated     password chosen and address confirmed
 *   opened link   clicked the link (address confirmed) but never set a password
 *   waiting       link not used yet and still valid
 *   expired       link not used and past its expiry — needs a fresh email
 *
 * Bounces are not detected automatically. Mail goes out over SMTP, which only
 * reports a refusal at the moment of sending (counted here as delivery failed);
 * a bounce arrives later as a reply in the sender's mailbox. Whoever reads it
 * marks the address in the delivery report, and it joins Blocked Emails, whose
 * size is shown alongside.
 */
class TeacherActivationStatsWidget extends BaseWidget
{
    protected ?string $heading = 'Account Activation';

    protected ?string $description = 'Activation emails sent to teachers and how many have finished signing up.';

    // The numbers move when a teacher clicks a link, not by the second.
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $sent = $this->sent()->count();

        $activated = $this->sent()->whereHas('user', fn (Builder $u) => $u
            ->whereNotNull('password_set_at')
            ->whereNotNull('email_verified_at'))->count();

        $notActivated = fn (): Builder => $this->sent()->where(fn (Builder $q) => $q
            ->whereDoesntHave('user')
            ->orWhereHas('user', fn (Builder $u) => $u
                ->whereNull('password_set_at')
                ->orWhereNull('email_verified_at')));

        $openedLink = $notActivated()->whereNotNull('verification_token_used_at')->count();

        $waiting = $notActivated()
            ->whereNull('verification_token_used_at')
            ->where('verification_token_expires_at', '>', now())
            ->count();

        $expired = $notActivated()
            ->whereNull('verification_token_used_at')
            ->where(fn (Builder $q) => $q
                ->whereNull('verification_token_expires_at')
                ->orWhere('verification_token_expires_at', '<=', now()))
            ->count();

        [$opened, $failed] = $this->deliveryCounts();

        $share = fn (int $n): string => $sent > 0 ? round($n / $sent * 100) . '%' : '—';

        return [
            Stat::make('Activation Email Sent', number_format($sent))
                ->description($sent > 0
                    ? number_format($opened) . ' opened the email (tracked sends only)'
                    : 'No activation email sent yet')
                ->icon('heroicon-o-paper-airplane')
                ->color('info'),

            Stat::make('Activated', number_format($activated))
                ->description($share($activated) . ' of those sent — password set and email confirmed')
                ->icon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('Awaiting Activation', number_format($waiting + $openedLink))
                ->description(number_format($waiting) . ' link still valid · ' . number_format($openedLink) . ' opened link, no password yet')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Link Expired', number_format($expired))
                ->description('Never used before expiry — send a fresh activation email')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($expired > 0 ? 'danger' : 'gray'),

            Stat::make('Delivery Failed', number_format($failed))
                ->description('Refused by the mail server on the latest send · '
                    . number_format(EmailSuppression::count()) . ' address(es) blocked as bounced/fake')
                ->icon('heroicon-o-x-circle')
                ->color($failed > 0 ? 'danger' : 'gray'),
        ];
    }

    /**
     * Teachers who have been sent an activation link at least once.
     */
    protected function sent(): Builder
    {
        return Teacher::query()->whereNotNull('activation_email_sent_at');
    }

    /**
     * Opens and failures, read from each teacher's most recent activation send.
     *
     * The latest one only: a teacher whose first email failed and whose second
     * went through is not a delivery problem any more, and counting every row
     * would report the same person once per attempt.
     *
     * @return array{0: int, 1: int}  [opened, failed]
     */
    protected function deliveryCounts(): array
    {
        $latest = DB::table('email_batch_recipients as r')
            ->join('email_batches as b', 'b.id', '=', 'r.email_batch_id')
            ->where('b.uses_activation_link', true)
            ->whereNotNull('r.teacher_id')
            ->where('r.status', '!=', EmailBatchRecipient::STATUS_SKIPPED)
            ->groupBy('r.teacher_id')
            ->selectRaw('MAX(r.id)');

        $row = EmailBatchRecipient::query()
            ->whereIn('id', $latest)
            ->selectRaw('SUM(opened_at IS NOT NULL) as opened, SUM(status = ?) as failed', [EmailBatchRecipient::STATUS_FAILED])
            ->first();

        return [(int) ($row->opened ?? 0), (int) ($row->failed ?? 0)];
    }
}
