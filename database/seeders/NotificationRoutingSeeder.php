<?php

namespace Database\Seeders;

use App\Models\NotificationRouting;
use Illuminate\Database\Seeder;

class NotificationRoutingSeeder extends Seeder
{
    public function run(): void
    {
        $routings = [
            // Academic Info + Education → Department Head + Admin
            [
                'trigger_type' => 'teacher_profile_update',
                'trigger_sections' => ['academic_info', 'education'], // Multiple sections
                'recipient_type' => 'role',
                'recipient_identifiers' => ['super_admin', 'panel_user'], // Multiple roles
                'description' => 'Notify admins when academic info or education is updated',
            ],
            // Publications + Research → Department Head
            [
                'trigger_type' => 'teacher_profile_update',
                'trigger_sections' => ['publications', 'research_projects', 'research_info'], // Multiple sections
                'recipient_type' => 'department_head',
                'recipient_identifiers' => null,
                'description' => 'Notify department heads for research-related updates',
            ],
            // All approval sections → Super Admin
            [
                'trigger_type' => 'teacher_profile_update',
                'trigger_sections' => null, // null = all sections
                'recipient_type' => 'role',
                'recipient_identifiers' => ['super_admin'], // Array with single role
                'description' => 'Notify super admins for all approval requests',
            ],
        ];

        foreach ($routings as $routing) {
            NotificationRouting::create($routing);
        }
    }
}
