<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * The email that gets a migrated teacher into the system for the first time.
 *
 * Kept separate from EmailTemplateSeeder so re-running it cannot overwrite
 * wording the administration has edited in the other three templates.
 *
 * Distinct from profile_verification_request: that one asks a teacher who can
 * already sign in to review their data, while this one is the way in. Every
 * account arrived from the migration with an unusable password, so the link
 * signs the teacher in and then requires them to choose a password.
 *
 * {activation_link} is deliberately not {verification_link}. The verification
 * link points at a page behind the panel's login, which an account with no
 * usable password can never reach.
 *
 * Usage: php artisan db:seed --class=AccountActivationTemplateSeeder
 */
class AccountActivationTemplateSeeder extends Seeder
{
    public const KEY = 'account_activation';

    public function run(): void
    {
        $body = <<<'BODY'
Dear {teacher_name},

Your faculty profile has been set up on the Faculty Management System, and your
information has been carried over from our previous records.

To get started, open the link below. It will sign you in and ask you to choose
your own password — you do not need an existing one.

{activation_link}

This link is valid for {link_validity_days} day(s) and can only be used once. If
it expires before you use it, contact the administration and a new one will be
sent.

Once inside, please review your profile and complete anything that is missing.
Your profile is currently {profile_score} complete.

Employee ID: {employee_id}
Department: {department}
Designation: {designation}

Please do not forward this email — anyone who opens the link can sign in as you
until you set your password.

Best regards,
Administration
BODY;

        EmailTemplate::updateOrCreate(
            ['key' => self::KEY],
            [
                'name' => 'Account Activation (First Sign-In)',
                'key' => self::KEY,
                'subject' => 'Set up your Faculty Management System account',
                'body' => $body,
                'variables_json' => [
                    '{teacher_name}'        => 'Full name of the faculty member',
                    '{employee_id}'         => 'Employee ID',
                    '{department}'          => 'Department name',
                    '{designation}'         => 'Designation name',
                    '{profile_score}'       => 'Current profile completion percentage',
                    '{activation_link}'     => 'One-time sign-in link that also prompts for a new password',
                    '{link_validity_days}'  => 'How many days the link stays valid',
                ],
                'is_active' => true,
            ],
        );

        $this->command->info('Account activation template seeded.');
    }
}
