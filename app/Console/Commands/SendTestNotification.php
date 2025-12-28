<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TestNotification;
use Illuminate\Console\Command;

class SendTestNotification extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:notification {user_id=1}';

    /**
     * The console command description.
     */
    protected $description = 'Send a test database notification to verify Filament notifications are working';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $userId = $this->argument('user_id');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return;
        }

        $user->notify(new TestNotification(
            title: 'System Test',
            body: 'Database notifications are working correctly! ✅'
        ));

        $this->info("Test notification sent to {$user->name}!");
        $this->info("Check the notification bell in the Filament admin panel.");
    }
}
