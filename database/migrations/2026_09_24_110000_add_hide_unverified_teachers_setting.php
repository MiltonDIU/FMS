<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The teacher setting that keeps unverified profiles off the public site.
 *
 * Created here, switched off, rather than left for the first save of the
 * settings page: a row that page creates has no type, so Setting::get() would
 * hand back the string "false" — which reads as on. Typed as boolean from the
 * start, it reads as what it says.
 */
return new class extends Migration
{
    private const KEY = 'teacher_hide_unverified_publicly';

    public function up(): void
    {
        if (DB::table('settings')->where('key', self::KEY)->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'group' => 'teacher',
            'key' => self::KEY,
            'value' => 'false',
            'type' => 'boolean',
            'label' => 'Hide unverified profiles from the public',
            'description' => 'When on, teachers whose verification status is unverified are not shown on the public site or through the API.',
            'is_public' => false,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('setting.' . self::KEY);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();

        Cache::forget('setting.' . self::KEY);
    }
};
