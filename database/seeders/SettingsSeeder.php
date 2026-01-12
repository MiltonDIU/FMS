<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Usage: php artisan db:seed --class=SettingsSeeder
     */
    public function run(): void
    {
        $settings = [
            // Teacher Settings
            [
                'group' => 'teacher',
                'key' => 'teacher_default_password',
                'value' => 'pass@123456',
                'type' => 'string',
                'label' => 'Default Teacher Password',
                'description' => 'Default password for newly created teacher accounts',
                'is_public' => false,
                'sort_order' => 1,
            ],
            [
                'group' => 'teacher',
                'key' => 'teacher_send_welcome_email',
                'value' => 'false',
                'type' => 'boolean',
                'label' => 'Send Welcome Email',
                'description' => 'Send login credentials email when teacher account is created',
                'is_public' => false,
                'sort_order' => 2,
            ],
            // System Settings
            [
                'group' => 'system',
                'key' => 'check_package_updates',
                'value' => 'false',
                'type' => 'boolean',
                'label' => 'Check Package Updates',
                'description' => 'Enable checking for latest package versions on dashboard',
                'is_public' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($settings as $setting) {
            // Use updateOrInsert to avoid duplicates
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Settings seeded successfully.');
    }
}
