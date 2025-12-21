<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TeacherLookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            NationalitySeeder::class,
            GenderSeeder::class,
            BloodGroupSeeder::class,
            ReligionSeeder::class,
        ]);
    }
}
