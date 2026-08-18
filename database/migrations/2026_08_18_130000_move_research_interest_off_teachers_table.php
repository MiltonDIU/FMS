<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves what the column held into rows, then drops the column.
 *
 * `teachers.research_interest` was one text field holding a comma-separated
 * list, which every reader then split apart again — the model had an accessor
 * doing exactly that, and so the commas were load-bearing punctuation nobody
 * had agreed on. An interest with a comma in its own name had no way to be
 * written down.
 *
 * The split here is the same one the accessor did, so nothing is read
 * differently than it was; it is only recorded that way now. Order follows the
 * order they were written in.
 *
 * The reverse joins them back with ", ", which is lossy in the way the original
 * always was: descriptions have nowhere to go, and an interest containing a
 * comma comes back as two. That is the shape of the old column, not a fault in
 * the rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teachers', 'research_interest')) {
            return;
        }

        DB::table('teachers')
            ->select('id', 'research_interest')
            ->whereNotNull('research_interest')
            ->orderBy('id')
            ->chunk(500, function ($teachers) {
                $rows = [];

                foreach ($teachers as $teacher) {
                    $interests = array_values(array_filter(
                        array_map('trim', explode(',', (string) $teacher->research_interest)),
                        'strlen',
                    ));

                    foreach ($interests as $position => $interest) {
                        $rows[] = [
                            'teacher_id' => $teacher->id,
                            'interest' => $interest,
                            'sort_order' => $position,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('research_interests')->insert($rows);
                }
            });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('research_interest');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->text('research_interest')->nullable()->after('bio');
        });

        DB::table('research_interests')
            ->whereNull('deleted_at')
            ->orderBy('teacher_id')
            ->orderBy('sort_order')
            ->get(['teacher_id', 'interest'])
            ->groupBy('teacher_id')
            ->each(function ($rows, $teacherId) {
                DB::table('teachers')
                    ->where('id', $teacherId)
                    ->update(['research_interest' => $rows->pluck('interest')->implode(', ')]);
            });
    }
};
