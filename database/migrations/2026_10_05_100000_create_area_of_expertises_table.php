<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher's areas of expertise, kept apart from their research interests.
 *
 * They had been sharing `research_interests`, and they are not the same thing
 * from the same place. What sat in that table came almost entirely from the
 * Directorate of Research's researchers.json — its `expertise` list, brought in
 * by import:researcher-profiles. Research interests proper come from the old
 * site's teacher.currentResearch, which export:old-teachers-research-interests
 * now parses. Two sources writing one list would leave nobody able to say which
 * entry came from where, or to re-run either import without doubling the other.
 *
 * Same shape as research_interests and teaching_areas: a row each, an optional
 * description, an order somebody chose.
 *
 * The move takes only what researchers.json accounts for — a row is moved when
 * its teacher's portfolio handle and its text both appear in that file. On the
 * dev database that is 853 of 855 rows; the two left are ones a teacher typed
 * in by hand, and those are research interests. Matching on the file rather
 * than on is_researcher keeps a researcher's own hand-added interests where
 * they are. Without the file the table is still created and nothing is moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_of_expertises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            $table->text('expertise');
            $table->text('description')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('teacher_id');
        });

        $fromFile = $this->expertiseInResearchersFile();

        if ($fromFile === []) {
            return;
        }

        // By id, not by offset: rows are deleted as they move, and an offset
        // would skip past whatever slid into the gap — 353 of 853 on the dev data.
        DB::table('research_interests as r')
            ->join('teachers as t', 't.id', '=', 'r.teacher_id')
            ->select('r.*', 't.webpage')
            ->chunkById(500, function ($rows) use ($fromFile) {
                $moving = $rows->filter(fn ($row) => isset(
                    $fromFile[mb_strtolower(trim((string) $row->webpage)) . '|' . mb_strtolower(trim((string) $row->interest))]
                ));

                if ($moving->isEmpty()) {
                    return;
                }

                DB::table('area_of_expertises')->insert($moving->map(fn ($row) => [
                    'teacher_id' => $row->teacher_id,
                    'expertise' => $row->interest,
                    'description' => $row->description,
                    'sort_order' => $row->sort_order,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'deleted_at' => $row->deleted_at,
                ])->values()->all());

                // Moved, not copied — a soft delete would leave them counted as
                // research interests by anything reading the table withTrashed.
                DB::table('research_interests')->whereIn('id', $moving->pluck('id'))->delete();
            }, 'r.id', 'id');
    }

    /**
     * Every row goes back, which is lossless: the two tables have the same
     * columns, and research_interests held all of these before.
     */
    public function down(): void
    {
        DB::table('area_of_expertises')->chunkById(500, function ($rows) {
            DB::table('research_interests')->insert($rows->map(fn ($row) => [
                'teacher_id' => $row->teacher_id,
                'interest' => $row->expertise,
                'description' => $row->description,
                'sort_order' => $row->sort_order,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => $row->deleted_at,
            ])->all());
        });

        Schema::dropIfExists('area_of_expertises');
    }

    /**
     * "handle|expertise" for every expertise entry in researchers.json, lower-cased.
     *
     * The handle is read out of the portfolio URL the same way
     * import:researcher-profiles reads it, since that is how those rows were
     * attached to a teacher in the first place.
     *
     * @return array<string, true>
     */
    private function expertiseInResearchersFile(): array
    {
        $file = public_path('documents/old publication/researchers.json');

        if (! file_exists($file)) {
            return [];
        }

        $records = json_decode((string) file_get_contents($file), true);
        $keys = [];

        foreach (is_array($records) ? $records : [] as $record) {
            if (! preg_match('~/([^/]+)\.html?~i', trim((string) ($record['diuPortfolio'] ?? '')), $matches)) {
                continue;
            }

            foreach ($record['expertise'] ?? [] as $expertise) {
                $keys[mb_strtolower($matches[1]) . '|' . mb_strtolower(trim((string) $expertise))] = true;
            }
        }

        return $keys;
    }
};
