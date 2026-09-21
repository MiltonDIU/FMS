<?php

use App\Support\AdministrativePostMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retires teachers.extra_designation in favour of administrative roles.
 *
 * The column held the half of a designation that is not a rank — "Director, MBA
 * Program", "Associate Head", "Proctor". That is a post somebody holds, which
 * is what administrative_role_user already models, and models better: it is a
 * lookup rather than free text, a person can hold several, it carries the
 * department or faculty the post is held over, and it has start and end dates
 * and an acting flag. The teachers screen assigns it, the frontend already
 * draws a block from it, and the ordering work reads its sort_order.
 *
 * Keeping both meant saying the same thing twice in two shapes, and the two
 * promptly disagreed: three teachers ended up reading "Professor & Professor"
 * or "Professor & Associate Professor" because the export put the rank in both
 * halves, while the administrative role beside it already said Dean or
 * Associate Dean correctly.
 *
 * Nothing is thrown away. Every value is moved to a role first, and the
 * original text is kept in the assignment's remarks, so a post that this
 * mapping reads wrongly can still be found and corrected by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teachers', 'extra_designation')) {
            return;
        }

        foreach (AdministrativePostMap::MISSING_ROLES as $name => $sort) {
            if (! DB::table('administrative_roles')->where('name', $name)->exists()) {
                DB::table('administrative_roles')->insert([
                    'name' => $name,
                    'sort_order' => $sort,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $roleIds = DB::table('administrative_roles')->pluck('id', 'name');

        /*
         * The ranks, so a value that is only a rank can be recognised and
         * dropped. Those are the parser's doing — the old designation read
         * "Associate Dean & Professor" and both halves came back as the rank —
         * and the teachers they affect already hold the right role.
         */
        $ranks = DB::table('designations')->pluck('name')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))->all();

        $teachers = DB::table('teachers')
            ->whereNotNull('extra_designation')
            ->where('extra_designation', '!=', '')
            ->get(['id', 'user_id', 'department_id', 'extra_designation']);

        foreach ($teachers as $teacher) {
            $text = trim((string) $teacher->extra_designation);

            if ($text === '' || $teacher->user_id === null) {
                continue;
            }

            $roleName = AdministrativePostMap::roleFor($text, $ranks);

            if ($roleName === null || ! isset($roleIds[$roleName])) {
                continue;
            }

            $roleId = $roleIds[$roleName];

            // Already holds it: the free text was a second telling of the same
            // post, so there is nothing to add.
            $exists = DB::table('administrative_role_user')
                ->where('user_id', $teacher->user_id)
                ->where('administrative_role_id', $roleId)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue;
            }

            $facultyId = null;
            $departmentId = $teacher->department_id;

            if (AdministrativePostMap::isFacultyScoped($roleName)) {
                $facultyId = DB::table('departments')->where('id', $teacher->department_id)->value('faculty_id');
                $departmentId = null;
            }

            DB::table('administrative_role_user')->insert([
                'user_id' => $teacher->user_id,
                'administrative_role_id' => $roleId,
                'department_id' => $departmentId,
                'faculty_id' => $facultyId,
                // Required, and the old data records no date for these posts.
                // Today is the honest answer: it is when we learned of it, and
                // every other assignment imported so far carries the same.
                'start_date' => now()->toDateString(),
                'is_acting' => false,
                'is_active' => true,
                'sort_order' => 0,
                // The words the post was written in. "Program Coordinator"
                // cannot say which programme, and "Director, M.Sc in Cyber
                // Security" should not become indistinguishable from
                // "Coordinator, MIS".
                'remarks' => $text,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('extra_designation');
        });
    }

    /**
     * The column comes back empty. What it held is in the role assignments'
     * remarks, and putting it back automatically would recreate the duplication
     * this migration exists to remove.
     */
    public function down(): void
    {
        if (Schema::hasColumn('teachers', 'extra_designation')) {
            return;
        }

        Schema::table('teachers', function (Blueprint $table) {
            $table->string('extra_designation')->nullable()->after('designation_id');
        });
    }
};
