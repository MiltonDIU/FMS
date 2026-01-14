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
            // Mail Configuration
            [
                'group' => 'system',
                'key' => 'mail_mailer',
                'value' => 'smtp',
                'type' => 'string',
                'label' => 'Mailer',
                'description' => 'Mail driver (smtp, log)',
                'is_public' => false,
                'sort_order' => 10,
            ],
            [
                'group' => 'system',
                'key' => 'mail_host',
                'value' => 'smtp.gmail.com',
                'type' => 'string',
                'label' => 'Mail Host',
                'description' => 'SMTP Host',
                'is_public' => false,
                'sort_order' => 11,
            ],
            [
                'group' => 'system',
                'key' => 'mail_port',
                'value' => '587',
                'type' => 'integer',
                'label' => 'Mail Port',
                'description' => 'SMTP Port',
                'is_public' => false,
                'sort_order' => 12,
            ],
            [
                'group' => 'system',
                'key' => 'mail_username',
                'value' => '',
                'type' => 'string',
                'label' => 'Mail Username',
                'description' => 'SMTP Username',
                'is_public' => false,
                'sort_order' => 13,
            ],
            [
                'group' => 'system',
                'key' => 'mail_password',
                'value' => '',
                'type' => 'string',
                'label' => 'Mail Password',
                'description' => 'SMTP Password',
                'is_public' => false,
                'sort_order' => 14,
            ],
            [
                'group' => 'system',
                'key' => 'mail_encryption',
                'value' => 'tls',
                'type' => 'string',
                'label' => 'Mail Encryption',
                'description' => 'SMTP Encryption',
                'is_public' => false,
                'sort_order' => 15,
            ],
            [
                'group' => 'system',
                'key' => 'mail_from_address',
                'value' => 'hello@example.com',
                'type' => 'string',
                'label' => 'From Address',
                'description' => 'Global From Address',
                'is_public' => false,
                'sort_order' => 16,
            ],
            [
                'group' => 'system',
                'key' => 'mail_from_name',
                'value' => 'FMS',
                'type' => 'string',
                'label' => 'From Name',
                'description' => 'Global From Name',
                'is_public' => false,
                'sort_order' => 17,
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
