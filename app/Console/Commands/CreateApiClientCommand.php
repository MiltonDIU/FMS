<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * An account and token for a website that reads the API from its own server.
 *
 * A separate account rather than somebody's own, so the website can be cut off
 * without signing a person out, and so its requests are told apart in the logs.
 *
 * The account gets a random password nobody is shown. A user with no role passes
 * canAccessPanel(), so a password anyone knew would open the admin panel too;
 * this way the token is the only way in, and it reaches only the read API.
 * password_set_at is filled for the same reason RequirePasswordChangeApi reads
 * it: there is no emailed password to replace.
 */
class CreateApiClientCommand extends Command
{
    protected $signature = 'api:client
                            {name : What is calling, e.g. "Main Website"}
                            {--email= : Account email (default api-<name>@fms.local)}
                            {--rotate : Revoke the client\'s existing tokens before issuing a new one}
                            {--revoke : Revoke every token of the client and issue none}';

    protected $description = 'Create an API account for a website and print a bearer token for it';

    public function handle(): int
    {
        $name = trim($this->argument('name'));
        $slug = Str::slug($name);
        $email = $this->option('email') ?: "api-{$slug}@fms.local";

        $user = User::where('email', $email)->first();

        if ($this->option('revoke')) {
            if (! $user) {
                $this->error("No API client with email {$email}.");

                return self::FAILURE;
            }

            $count = $user->tokens()->delete();
            $this->info("Revoked {$count} token(s) of {$email}.");

            return self::SUCCESS;
        }

        if (! $user) {
            $user = User::create([
                'name' => "API: {$name}",
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'is_active' => true,
            ]);

            $user->forceFill(['password_set_at' => now()])->save();

            $this->info("Created API client account {$email}.");
        } elseif ($user->roles()->exists() || $user->teacher()->exists()) {
            // Never hand a token for a real person's account out as a website's.
            $this->error("{$email} belongs to a person (it has a role or a teacher profile). Use another --email.");

            return self::FAILURE;
        }

        if ($this->option('rotate')) {
            $user->tokens()->delete();
        }

        $token = $user->createToken($slug)->plainTextToken;

        $this->newLine();
        $this->line('Token (shown once, keep it in the website\'s .env):');
        $this->line("FMS_API_TOKEN={$token}");
        $this->newLine();

        return self::SUCCESS;
    }
}
