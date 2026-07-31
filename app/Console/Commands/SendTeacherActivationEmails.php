<?php

namespace App\Console\Commands;

use App\Models\EmailTemplate;
use App\Models\Teacher;
use App\Services\TeacherActivationService;
use Database\Seeders\AccountActivationTemplateSeeder;
use Illuminate\Console\Command;

/**
 * Sends the one-time activation link to teachers who still need to sign in.
 *
 * Nothing is dispatched unless --send is passed. The default is a dry run that
 * reports who would be mailed and stops, because this command addresses over a
 * thousand real people and there is no recall once it has run.
 *
 *   php artisan teachers:send-activation                     preview only
 *   php artisan teachers:send-activation --days=14           preview, 14-day link
 *   php artisan teachers:send-activation --limit=5 --send    real, to 5 teachers
 *   php artisan teachers:send-activation --send              real, to everyone
 *
 * Start with --limit against a couple of addresses you control. Every run issues
 * fresh tokens, so an earlier email stops working as soon as a newer one goes.
 */
class SendTeacherActivationEmails extends Command
{
    protected $signature = 'teachers:send-activation
                            {--days= : Days the link stays valid (default '
                                . TeacherActivationService::DEFAULT_VALIDITY_DAYS . ')}
                            {--limit= : Only process this many teachers}
                            {--teacher=* : Restrict to these teacher ids}
                            {--send : Actually queue the emails. Without it nothing is sent}';

    protected $description = 'Email migrated teachers a one-time activation link (dry run unless --send)';

    /**
     * Beyond this many recipients, --send asks for confirmation first.
     */
    protected const CONFIRM_ABOVE = 25;

    public function handle(TeacherActivationService $activation): int
    {
        $days = (int) ($this->option('days') ?: TeacherActivationService::DEFAULT_VALIDITY_DAYS);

        if ($days < 1 || $days > 90) {
            $this->error('--days must be between 1 and 90.');

            return self::FAILURE;
        }

        $template = EmailTemplate::where('key', AccountActivationTemplateSeeder::KEY)->first();

        if (! $template) {
            $this->error('The account_activation template is missing.');
            $this->line('Run: php artisan db:seed --class=AccountActivationTemplateSeeder');

            return self::FAILURE;
        }

        if (! $template->is_active) {
            $this->error('The account_activation template is disabled in System Settings.');

            return self::FAILURE;
        }

        $query = $activation->pendingQuery();

        if ($ids = $this->option('teacher')) {
            $query->whereIn('id', $ids);
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $recipients = $query->get();
        $skipped = $activation->alreadyActiveQuery()->count();

        $this->newLine();
        $this->line('  Template  : ' . $template->name);
        $this->line('  Validity  : ' . $days . ' day(s)');
        $this->line('  Recipients: ' . $recipients->count());
        $this->line('  Skipped   : ' . $skipped . ' already have a password and a verified address');
        $this->newLine();

        if ($recipients->isEmpty()) {
            $this->info('Nobody to email.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Teacher', 'Email', 'Department'],
            $recipients->take(10)->map(fn (Teacher $t) => [
                $t->id,
                $t->full_name,
                $t->user?->email,
                $t->department?->code ?? '—',
            ])->all(),
        );

        if ($recipients->count() > 10) {
            $this->line('  ... and ' . ($recipients->count() - 10) . ' more');
            $this->newLine();
        }

        if (! $this->option('send')) {
            $this->warn('  DRY RUN — nothing was sent and no tokens were issued.');
            $this->line('  Re-run with --send once the list looks right.');

            return self::SUCCESS;
        }

        if ($recipients->count() > self::CONFIRM_ABOVE
            && ! $this->confirm('Queue activation emails for ' . $recipients->count() . ' teachers?', false)) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        $queued = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($recipients->count());
        $bar->start();

        foreach ($recipients as $teacher) {
            // Same call the send dialogs use, so a template behaves identically
            // whether it goes out from here or from the admin panel.
            $result = $activation->queueFor($teacher, $template->subject, $template->body, $days);

            str_starts_with($result, 'skipped') ? $skipped++ : $queued++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info($queued . ' activation email(s) queued.');

        if ($skipped > 0) {
            $this->warn($skipped . ' skipped while queueing.');
        }

        return self::SUCCESS;
    }
}
