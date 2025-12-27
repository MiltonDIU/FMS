<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MembershipOrganization;

class MembershipOrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = [
            // 🇧🇩 Bangladesh
            [
                'name' => 'Bangladesh Computer Society (BCS)',
                'description' => 'Professional body for computing and IT professionals in Bangladesh.',
            ],
            [
                'name' => 'Institution of Engineers, Bangladesh (IEB)',
                'description' => 'National organization of engineers in Bangladesh.',
            ],
            [
                'name' => 'Bangladesh Physical Society (BPS)',
                'description' => 'Organization for physicists and physics educators.',
            ],
            [
                'name' => 'Bangladesh Mathematical Society (BMS)',
                'description' => 'Professional society for mathematicians in Bangladesh.',
            ],
            [
                'name' => 'Bangladesh Chemical Society',
                'description' => 'Association of chemists and chemical researchers.',
            ],
            [
                'name' => 'Bangladesh Academy of Sciences (BAS)',
                'description' => 'Premier scientific academy in Bangladesh.',
            ],
            [
                'name' => 'Bangladesh Economic Association (BEA)',
                'description' => 'Professional association of economists.',
            ],
            [
                'name' => 'Bangladesh Association for the Advancement of Science (BAAS)',
                'description' => 'Umbrella organization for scientific advancement.',
            ],

            // 🌍 International
            [
                'name' => 'IEEE – Institute of Electrical and Electronics Engineers',
                'description' => 'World’s largest technical professional organization.',
            ],
            [
                'name' => 'ACM – Association for Computing Machinery',
                'description' => 'Global association for computing professionals and researchers.',
            ],
            [
                'name' => 'IET – Institution of Engineering and Technology',
                'description' => 'International professional engineering institution.',
            ],
            [
                'name' => 'American Chemical Society (ACS)',
                'description' => 'Leading global organization for chemical professionals.',
            ],
            [
                'name' => 'American Physical Society (APS)',
                'description' => 'Professional body for physicists worldwide.',
            ],
            [
                'name' => 'Royal Society of Chemistry (RSC)',
                'description' => 'International chemical science organization.',
            ],
            [
                'name' => 'AAAS – American Association for the Advancement of Science',
                'description' => 'International nonprofit advancing science and innovation.',
            ],
            [
                'name' => 'International Society for Technology in Education (ISTE)',
                'description' => 'Global community for educators using technology.',
            ],
            [
                'name' => 'Academy of Management (AOM)',
                'description' => 'Professional association for management scholars.',
            ],
            [
                'name' => 'World Medical Association (WMA)',
                'description' => 'International organization of physicians.',
            ],
        ];

        foreach ($organizations as $org) {
            MembershipOrganization::firstOrCreate(
                ['name' => $org['name']],
                [
                    'description'   => $org['description'],
                    'is_active'     => true,
                    'created_by'    => null,
                    'activated_at'  => now(),
                    'activated_by'  => null,
                ]
            );
        }
    }
}
