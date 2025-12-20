<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class FMSSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * 1. Define Roles
         */
        $roles = [
            'super_admin',
            'admin',
            'registrar',
            'teacher',
            'research_team',
        ];

        // Create roles only if not exists
        foreach ($roles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName],
                ['guard_name' => 'web']
            );
        }

        /**
         * 2. Create Super Admin User
         */
        $superAdmin = User::updateOrCreate(
            ['email' => 'milton2913@gmail.com'],
            [
                'name' => 'Milton (Super Admin)',
                'password' => Hash::make('123456789'),
            ]
        );

        // Assign role only if not already assigned
        if (! $superAdmin->hasRole('super_admin')) {
            $superAdmin->assignRole('super_admin');
        }

        /**
         * 3. Create One User Per Role
         */
        $users = [
            'admin' => [
                'email' => 'admin@fms.diu.edu.bd',
                'name'  => 'System Admin',
            ],
            'registrar' => [
                'email' => 'registrar@fms.diu.edu.bd',
                'name'  => 'Registrar Officer',
            ],
            'teacher' => [
                'email' => 'teacher@fms.diu.edu.bd',
                'name'  => 'Faculty Teacher',
            ],
            'research_team' => [
                'email' => 'researcher@fms.diu.edu.bd',
                'name'  => 'Research Staff',
            ],
        ];

        foreach ($users as $role => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('123456789'),
                ]
            );

            // Avoid duplicate role assignment
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }
    }
}
