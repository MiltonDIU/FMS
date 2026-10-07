<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The approval-settings row for the Area of Expertise section of the teacher form.
 *
 * A migration rather than only the seeder, because deploys run migrations and
 * never seeders. Without the row, ApprovalSetting::requiresApproval() answers
 * false for the section, and it would also be missing from the approval
 * settings screen and from notification routing, which both list sections from
 * this table.
 *
 * It starts as Research Interest stands on that server, since the two are the
 * same kind of list and whoever set that one decided how such a list is
 * treated. Either can be changed afterwards on the approval settings screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('approval_settings')->where('section_key', 'area_of_expertise')->exists()) {
            return;
        }

        $researchInterest = DB::table('approval_settings')->where('section_key', 'academic_info')->first();

        DB::table('approval_settings')->insert([
            'section_key' => 'area_of_expertise',
            'section_label' => 'Area of Expertise',
            'requires_approval' => $researchInterest->requires_approval ?? true,
            'description' => 'Areas of expertise from the research directory',
            'fields' => json_encode(['areasOfExpertise']),
            'sort_order' => $researchInterest->sort_order ?? 4,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('approval_settings')->where('section_key', 'area_of_expertise')->delete();
    }
};
