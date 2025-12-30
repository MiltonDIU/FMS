<?php

namespace Database\Seeders;

use App\Models\NotificationRouting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationRoutingSeeder extends Seeder
{
    public function run(): void
    {
        // ===== DELETE ALL EXISTING ROUTINGS FIRST =====
        DB::table('notification_routings')->truncate();

        // ===== DEFINE ROUTING RULES =====
        $routings = [
            // Rule 1: All approval-required sections notify Super Admin
            [
                'trigger_type' => 'teacher_profile_update',
                'trigger_sections' => null, // null = all sections
                'recipient_type' => 'role',
                'recipient_identifiers' => ['super_admin'],
                'description' => 'Super Admin receives all pending approval notifications',
                'is_active' => true,
            ],
            
            // Rule 2: Education changes notify specific roles
            [
                'trigger_type' => 'teacher_profile_update',
                'trigger_sections' => ['educations', 'publications'],
                'recipient_type' => 'role',
                'recipient_identifiers' => ['super_admin', 'admin'],
                'description' => 'Education and Publication updates notify admins',
                'is_active' => true,
            ],
            
            // Rule 3: Basic info (department/designation) notify HR
            [
                'trigger_type' => 'teacher_profile_update',
                'trigger_sections' => ['basic_info'],
                'recipient_type' => 'role',
                'recipient_identifiers' => ['super_admin'],
                'description' => 'Department/designation changes require verification',
                'is_active' => true,
            ],
            
            // Rule 4: Job experience needs approval
            [
                'trigger_type' => 'teacher_profile_update',
                'trigger_sections' => ['job_experiences'],
                'recipient_type' => 'role',
                'recipient_identifiers' => ['super_admin'],
                'description' => 'Job experience updates require verification',
                'is_active' => true,
            ],
        ];

        // Insert all routings
        foreach ($routings as $routing) {
            NotificationRouting::create($routing);
        }
    }
}
