<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;

class MailConfigService
{
    /**
     * Configure mail settings from database.
     */
    public static function configure(): void
    {
        try {
            if (Schema::hasTable('settings')) {
                $mailSettings = [
                    'mail.default' => Setting::get('mail_mailer'),
                    'mail.mailers.smtp.host' => Setting::get('mail_host'),
                    'mail.mailers.smtp.port' => Setting::get('mail_port'),
                    'mail.mailers.smtp.username' => Setting::get('mail_username'),
                    'mail.mailers.smtp.password' => Setting::get('mail_password'),
                    'mail.mailers.smtp.encryption' => Setting::get('mail_encryption'),
                    'mail.from.address' => Setting::get('mail_from_address'),
                    'mail.from.name' => Setting::get('mail_from_name'),
                ];

                foreach ($mailSettings as $key => $value) {
                    if (!empty($value)) {
                        Config::set($key, $value);
                    }
                }
            }
        } catch (\Exception $e) {
            // Register settings failed (migration not run yet?)
        }
    }
}
