<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Issue (or revoke) the token HR's system uses to report departures.
 *
 * A command rather than a screen: the token is shown once, and handing it to
 * another system is a deliberate act by somebody with access to the server.
 *
 * It belongs to an ordinary account set aside for HR, so the version history
 * says who made each change. Not a teacher's account — a teacher's own login
 * should not carry the power to take colleagues off the site.
 */
class IssueHrApiTokenCommand extends Command
{
    public const ABILITY = 'hr:departures';

    protected $signature = 'teachers:issue-hr-token
        {email : The account set aside for HR}
        {--name=hr-departures : A name for the token, to tell it apart later}
        {--revoke : Revoke this account\'s HR tokens instead of issuing one}';

    protected $description = 'Issue the API token HR uses to report that a teacher has left (shown once)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account has that email.');

            return self::FAILURE;
        }

        $hrTokens = $user->tokens()->whereJsonContains('abilities', self::ABILITY);

        if ($this->option('revoke')) {
            $this->info('Revoked ' . $hrTokens->delete() . ' token(s).');

            return self::SUCCESS;
        }

        if ($user->teacher) {
            $this->error('That is a teacher\'s account. Use one set aside for HR.');

            return self::FAILURE;
        }

        if (! $user->is_active) {
            $this->error('That account is not active.');

            return self::FAILURE;
        }

        $token = $user->createToken($this->option('name'), [self::ABILITY]);

        $this->info('Token for POST /api/v1/hr/departures — it will not be shown again:');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
