<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Periods of service at the university, and a leaving date.
 *
 * A teacher held one joining date and nothing to say when they left, so a
 * teacher who resigned, went abroad for a PhD and came back could not be
 * recorded without overwriting their first period. Each period is now a row;
 * the profile's joining date, new leaving date and employment status describe
 * the current (or last) one, and App\Support\TeacherServicePeriods keeps the
 * rows in step with them.
 *
 * Existing teachers get one period each, from their joining date: open for
 * those still serving, ended for those who left. The old site never recorded
 * when anyone left, so those ended periods carry no leaving date.
 */
return new class extends Migration
{
    /** Statuses that end an appointment; "archived" is the old site's leavers. */
    private const ENDING = ['retired', 'resigned', 'terminated', 'archived'];

    public function up(): void
    {
        Schema::create('teacher_service_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->boolean('ended')->default(false);
            $table->string('end_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->string('source', 20)->default('profile');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['teacher_id', 'ended']);
        });

        if (! Schema::hasColumn('teachers', 'leaving_date')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->date('leaving_date')->nullable()->after('joining_date');
            });
        }

        $statuses = DB::table('employment_statuses')->get(['id', 'slug', 'name'])->keyBy('id');
        $now = now();

        DB::table('teachers')
            ->whereNull('deleted_at')
            ->select(['id', 'joining_date', 'employment_status_id'])
            ->orderBy('id')
            ->chunk(500, function ($teachers) use ($statuses, $now) {
                $rows = [];
                foreach ($teachers as $teacher) {
                    $status = $statuses[$teacher->employment_status_id] ?? null;
                    $ended = in_array($status?->slug, self::ENDING, true);

                    $rows[] = [
                        'teacher_id' => $teacher->id,
                        'joined_on' => $teacher->joining_date ? substr((string) $teacher->joining_date, 0, 10) : null,
                        'left_on' => null,
                        'ended' => $ended,
                        'end_reason' => $ended ? $status->name : null,
                        'source' => 'migration',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('teacher_service_periods')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_service_periods');

        if (Schema::hasColumn('teachers', 'leaving_date')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->dropColumn('leaving_date');
            });
        }
    }
};
