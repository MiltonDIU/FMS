<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds "Lecturer (Senior Scale)" as a designation in its own right.
 *
 * It is the grade the university awards now where it used to award "Senior
 * Lecturer": the same tier as Lecturer, one scale above it. The old name has to
 * stay — the people who hold it still hold it, and the HR system still sends it
 * — so this adds a row rather than renaming one.
 *
 * Both rank and sort_order are copied from "Senior Lecturer", because the two
 * names describe one grade and should therefore list in the same place. Nothing
 * existing is renumbered: sort_order is editable in the admin panel and
 * overwriting it here would throw away whatever ordering somebody has set.
 *
 * Idempotent, so it does nothing on an install where DesignationSeeder has
 * already written the row.
 */
return new class extends Migration
{
    private const NAME = 'Lecturer (Senior Scale)';

    public function up(): void
    {
        if (DB::table('designations')->where('name', self::NAME)->exists()) {
            return;
        }

        // A fresh install runs migrations before seeders, so the table is empty
        // and there is no list to add a row to yet. DesignationSeeder writes the
        // whole list including this one; adding it here first only gives it an
        // id ahead of Professor's and a rank guessed from nothing.
        if (! DB::table('designations')->exists()) {
            return;
        }

        // Senior Lecturer is the grade this one replaces, so its place in the
        // list is the right place. Lecturer is the fallback for an institution
        // that never had a Senior Lecturer row.
        $base = DB::table('designations')->where('name', 'Senior Lecturer')->first()
            ?: DB::table('designations')->where('name', 'Lecturer')->first();

        DB::table('designations')->insert([
            'name'        => self::NAME,
            'short_name'  => 'Lect. (Sr. Scale)',
            'rank'        => $base->rank ?? 4,
            'sort_order'  => $base->sort_order ?? 4,
            'description' => 'Lecturer on the senior scale: the same tier as Lecturer, '
                . 'one pay scale above it. Replaces the grade formerly titled Senior Lecturer, '
                . 'which is kept for the faculty members who still hold that title.',
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        $id = DB::table('designations')->where('name', self::NAME)->value('id');

        if (! $id) {
            return;
        }

        // Teachers point at this row with a foreign key, so removing it while
        // anybody holds it would fail as a constraint error. Deactivating is the
        // honest reversal in that case.
        if (DB::table('teachers')->where('designation_id', $id)->exists()) {
            DB::table('designations')->where('id', $id)->update([
                'is_active'  => false,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('designations')->where('id', $id)->delete();
    }
};
