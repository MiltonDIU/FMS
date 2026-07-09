<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\Country;

class MembershipOrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bangladeshId = Country::where('slug', 'bangladesh')->first()?->id ?? 18;
        $usId = Country::where('slug', 'united-states')->first()?->id ?? 250;
        $ukId = Country::where('slug', 'united-kingdom')->first()?->id ?? 249;
        $franceId = Country::where('slug', 'france')->first()?->id ?? 78;

        $organizations = [
            // 🇧🇩 Bangladesh
            [
                'name' => 'Bangladesh Computer Society (BCS)',
                'country_id' => $bangladeshId,
            ],
            [
                'name' => 'Institution of Engineers, Bangladesh (IEB)',
                'country_id' => $bangladeshId,
            ],
            [
                'name' => 'Bangladesh Physical Society (BPS)',
                'country_id' => $bangladeshId,
            ],
            [
                'name' => 'Bangladesh Mathematical Society (BMS)',
                'country_id' => $bangladeshId,
            ],
            [
                'name' => 'Bangladesh Chemical Society',
                'country_id' => $bangladeshId,
            ],
            [
                'name' => 'Bangladesh Academy of Sciences (BAS)',
                'country_id' => $bangladeshId,
            ],
            [
                'name' => 'Bangladesh Economic Association (BEA)',
                'country_id' => $bangladeshId,
            ],
            [
                'name' => 'Bangladesh Association for the Advancement of Science (BAAS)',
                'country_id' => $bangladeshId,
            ],

            // 🌍 International
            [
                'name' => 'IEEE – Institute of Electrical and Electronics Engineers',
                'country_id' => $usId,
            ],
            [
                'name' => 'ACM – Association for Computing Machinery',
                'country_id' => $usId,
            ],
            [
                'name' => 'IET – Institution of Engineering and Technology',
                'country_id' => $ukId,
            ],
            [
                'name' => 'American Chemical Society (ACS)',
                'country_id' => $usId,
            ],
            [
                'name' => 'American Physical Society (APS)',
                'country_id' => $usId,
            ],
            [
                'name' => 'Royal Society of Chemistry (RSC)',
                'country_id' => $ukId,
            ],
            [
                'name' => 'AAAS – American Association for the Advancement of Science',
                'country_id' => $usId,
            ],
            [
                'name' => 'International Society for Technology in Education (ISTE)',
                'country_id' => $usId,
            ],
            [
                'name' => 'Academy of Management (AOM)',
                'country_id' => $usId,
            ],
            [
                'name' => 'World Medical Association (WMA)',
                'country_id' => $franceId,
            ],
        ];

        foreach ($organizations as $org) {
            Organization::firstOrCreate(
                [
                    'name' => $org['name'],
                    'country_id' => $org['country_id'],
                ],
                [
                    'is_professional_body' => true,
                    'is_active'            => true,
                    'created_by'           => null,
                ]
            );
        }
    }
}
