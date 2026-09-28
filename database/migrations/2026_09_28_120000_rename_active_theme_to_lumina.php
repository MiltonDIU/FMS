<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Update active_theme if it was set to theme_new
        if (Setting::get('active_theme') === 'theme_new') {
            Setting::set('active_theme', 'theme_lumina');
        }

        // Migrate any theme_theme_new_* settings to theme_theme_lumina_*
        $settings = Setting::where('key', 'like', 'theme_theme_new_%')->get();
        foreach ($settings as $setting) {
            $newKey = str_replace('theme_theme_new_', 'theme_theme_lumina_', $setting->key);
            Setting::set($newKey, $setting->value);
            $setting->delete();
        }
    }

    public function down(): void
    {
        if (Setting::get('active_theme') === 'theme_lumina') {
            Setting::set('active_theme', 'theme_new');
        }

        $settings = Setting::where('key', 'like', 'theme_theme_lumina_%')->get();
        foreach ($settings as $setting) {
            $newKey = str_replace('theme_theme_lumina_', 'theme_theme_new_', $setting->key);
            Setting::set($newKey, $setting->value);
            $setting->delete();
        }
    }
};
