<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\DegreeType;
use App\Models\DegreeLevel;

class DegreeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, ensure degree levels exist
       // $this->call(DegreeLevelSeeder::class);

        // Get all degree levels for mapping
        $degreeLevels = DegreeLevel::all()->pluck('id', 'slug')->toArray();

        $degreeTypes = [
            // High School
            ['code' => 'SSC', 'name' => 'Secondary School Certificate', 'level' => 'high-school', 'sort_order' => 1],
            ['code' => 'HSC', 'name' => 'Higher Secondary Certificate', 'level' => 'high-school', 'sort_order' => 2],
            ['code' => 'O-Level', 'name' => 'Ordinary Level (GCE)', 'level' => 'high-school', 'sort_order' => 3],
            ['code' => 'A-Level', 'name' => 'Advanced Level (GCE)', 'level' => 'high-school', 'sort_order' => 4],
            ['code' => 'IB-DP', 'name' => 'International Baccalaureate Diploma', 'level' => 'high-school', 'sort_order' => 5],
            ['code' => 'HS-Diploma', 'name' => 'High School Diploma', 'level' => 'high-school', 'sort_order' => 6],
            ['code' => 'Voc-HS', 'name' => 'Vocational High School', 'level' => 'high-school', 'sort_order' => 7],

            // Diploma/Certificate Level
            ['code' => 'DipEng', 'name' => 'Diploma in Engineering', 'level' => 'diploma', 'sort_order' => 1],
            ['code' => 'DipCom', 'name' => 'Diploma in Commerce', 'level' => 'diploma', 'sort_order' => 2],
            ['code' => 'DipCS', 'name' => 'Diploma in Computer Science', 'level' => 'diploma', 'sort_order' => 3],
            ['code' => 'DipNur', 'name' => 'Diploma in Nursing', 'level' => 'diploma', 'sort_order' => 4],
            ['code' => 'DipHM', 'name' => 'Diploma in Hotel Management', 'level' => 'diploma', 'sort_order' => 5],
            ['code' => 'Cert', 'name' => 'Certificate Program', 'level' => 'diploma', 'sort_order' => 6],

            // Associate Level
            ['code' => 'AA', 'name' => 'Associate of Arts', 'level' => 'associate', 'sort_order' => 1],
            ['code' => 'AS', 'name' => 'Associate of Science', 'level' => 'associate', 'sort_order' => 2],
            ['code' => 'AAS', 'name' => 'Associate of Applied Science', 'level' => 'associate', 'sort_order' => 3],
            ['code' => 'AFA', 'name' => 'Associate of Fine Arts', 'level' => 'associate', 'sort_order' => 4],
            ['code' => 'ADN', 'name' => 'Associate Degree in Nursing', 'level' => 'associate', 'sort_order' => 5],

            // Bachelor's Degrees
            ['code' => 'BSc', 'name' => 'Bachelor of Science', 'level' => 'bachelor', 'sort_order' => 1],
            ['code' => 'BA', 'name' => 'Bachelor of Arts', 'level' => 'bachelor', 'sort_order' => 2],
            ['code' => 'BBA', 'name' => 'Bachelor of Business Administration', 'level' => 'bachelor', 'sort_order' => 3],
            ['code' => 'BEng', 'name' => 'Bachelor of Engineering', 'level' => 'bachelor', 'sort_order' => 4],
            ['code' => 'BTech', 'name' => 'Bachelor of Technology', 'level' => 'bachelor', 'sort_order' => 5],
            ['code' => 'MBBS', 'name' => 'Bachelor of Medicine, Bachelor of Surgery', 'level' => 'bachelor', 'sort_order' => 6],
            ['code' => 'LLB', 'name' => 'Bachelor of Laws', 'level' => 'bachelor', 'sort_order' => 7],
            ['code' => 'BArch', 'name' => 'Bachelor of Architecture', 'level' => 'bachelor', 'sort_order' => 8],
            ['code' => 'BPharm', 'name' => 'Bachelor of Pharmacy', 'level' => 'bachelor', 'sort_order' => 9],
            ['code' => 'BFA', 'name' => 'Bachelor of Fine Arts', 'level' => 'bachelor', 'sort_order' => 10],
            ['code' => 'BEd', 'name' => 'Bachelor of Education', 'level' => 'bachelor', 'sort_order' => 11],
            ['code' => 'BSS', 'name' => 'Bachelor of Social Science', 'level' => 'bachelor', 'sort_order' => 12],
            ['code' => 'BCom', 'name' => 'Bachelor of Commerce', 'level' => 'bachelor', 'sort_order' => 13],
            ['code' => 'BDes', 'name' => 'Bachelor of Design', 'level' => 'bachelor', 'sort_order' => 14],
            ['code' => 'BSW', 'name' => 'Bachelor of Social Work', 'level' => 'bachelor', 'sort_order' => 15],
            ['code' => 'BVSc', 'name' => 'Bachelor of Veterinary Science', 'level' => 'bachelor', 'sort_order' => 16],
            ['code' => 'BDS', 'name' => 'Bachelor of Dental Surgery', 'level' => 'bachelor', 'sort_order' => 17],
            ['code' => 'BN', 'name' => 'Bachelor of Nursing', 'level' => 'bachelor', 'sort_order' => 18],
            ['code' => 'B.Optom', 'name' => 'Bachelor of Optometry', 'level' => 'bachelor', 'sort_order' => 19],
            ['code' => 'BPT', 'name' => 'Bachelor of Physiotherapy', 'level' => 'bachelor', 'sort_order' => 20],

            // Master's Degrees
            ['code' => 'MSc', 'name' => 'Master of Science', 'level' => 'master', 'sort_order' => 1],
            ['code' => 'MA', 'name' => 'Master of Arts', 'level' => 'master', 'sort_order' => 2],
            ['code' => 'MBA', 'name' => 'Master of Business Administration', 'level' => 'master', 'sort_order' => 3],
            ['code' => 'MEng', 'name' => 'Master of Engineering', 'level' => 'master', 'sort_order' => 4],
            ['code' => 'MTech', 'name' => 'Master of Technology', 'level' => 'master', 'sort_order' => 5],
            ['code' => 'MPhil', 'name' => 'Master of Philosophy', 'level' => 'master', 'sort_order' => 6],
            ['code' => 'LLM', 'name' => 'Master of Laws', 'level' => 'master', 'sort_order' => 7],
            ['code' => 'MArch', 'name' => 'Master of Architecture', 'level' => 'master', 'sort_order' => 8],
            ['code' => 'MPharm', 'name' => 'Master of Pharmacy', 'level' => 'master', 'sort_order' => 9],
            ['code' => 'MFA', 'name' => 'Master of Fine Arts', 'level' => 'master', 'sort_order' => 10],
            ['code' => 'MEd', 'name' => 'Master of Education', 'level' => 'master', 'sort_order' => 11],
            ['code' => 'MPH', 'name' => 'Master of Public Health', 'level' => 'master', 'sort_order' => 12],
            ['code' => 'MDS', 'name' => 'Master of Dental Surgery', 'level' => 'master', 'sort_order' => 13],
            ['code' => 'MS', 'name' => 'Master of Surgery', 'level' => 'master', 'sort_order' => 14],
            ['code' => 'MSS', 'name' => 'Master of Social Science', 'level' => 'master', 'sort_order' => 15],
            ['code' => 'MCom', 'name' => 'Master of Commerce', 'level' => 'master', 'sort_order' => 16],
            ['code' => 'MSW', 'name' => 'Master of Social Work', 'level' => 'master', 'sort_order' => 17],
            ['code' => 'MRes', 'name' => 'Master of Research', 'level' => 'master', 'sort_order' => 18],
            ['code' => 'MScEng', 'name' => 'Master of Science in Engineering', 'level' => 'master', 'sort_order' => 19],
            ['code' => 'MLA', 'name' => 'Master of Landscape Architecture', 'level' => 'master', 'sort_order' => 20],

            // Doctoral Degrees
            ['code' => 'PhD', 'name' => 'Doctor of Philosophy', 'level' => 'doctoral', 'sort_order' => 1],
            ['code' => 'DPhil', 'name' => 'Doctor of Philosophy', 'level' => 'doctoral', 'sort_order' => 2],
            ['code' => 'EdD', 'name' => 'Doctor of Education', 'level' => 'doctoral', 'sort_order' => 3],
            ['code' => 'DBA', 'name' => 'Doctor of Business Administration', 'level' => 'doctoral', 'sort_order' => 4],
            ['code' => 'MD', 'name' => 'Doctor of Medicine', 'level' => 'doctoral', 'sort_order' => 5],
            ['code' => 'DSc', 'name' => 'Doctor of Science', 'level' => 'doctoral', 'sort_order' => 6],
            ['code' => 'JSD', 'name' => 'Doctor of Juridical Science', 'level' => 'doctoral', 'sort_order' => 7],
            ['code' => 'DLitt', 'name' => 'Doctor of Letters', 'level' => 'doctoral', 'sort_order' => 8],
            ['code' => 'EngD', 'name' => 'Doctor of Engineering', 'level' => 'doctoral', 'sort_order' => 9],
            ['code' => 'PharmD', 'name' => 'Doctor of Pharmacy', 'level' => 'doctoral', 'sort_order' => 10],
            ['code' => 'PsyD', 'name' => 'Doctor of Psychology', 'level' => 'doctoral', 'sort_order' => 11],
            ['code' => 'DMin', 'name' => 'Doctor of Ministry', 'level' => 'doctoral', 'sort_order' => 12],
            ['code' => 'DMus', 'name' => 'Doctor of Music', 'level' => 'doctoral', 'sort_order' => 13],
            ['code' => 'DFA', 'name' => 'Doctor of Fine Arts', 'level' => 'doctoral', 'sort_order' => 14],

            // Post-Doctoral
            ['code' => 'PostDoc', 'name' => 'Post-Doctoral Fellowship', 'level' => 'post-doctoral', 'sort_order' => 1],
            ['code' => 'ResFellow', 'name' => 'Research Fellowship', 'level' => 'post-doctoral', 'sort_order' => 2],
            ['code' => 'ClinFellow', 'name' => 'Clinical Fellowship', 'level' => 'post-doctoral', 'sort_order' => 3],

            // Professional Certification
            ['code' => 'CPA', 'name' => 'Certified Public Accountant', 'level' => 'professional-certification', 'sort_order' => 1],
            ['code' => 'PMP', 'name' => 'Project Management Professional', 'level' => 'professional-certification', 'sort_order' => 2],
            ['code' => 'PE', 'name' => 'Professional Engineer', 'level' => 'professional-certification', 'sort_order' => 3],
            ['code' => 'CFA', 'name' => 'Chartered Financial Analyst', 'level' => 'professional-certification', 'sort_order' => 4],
            ['code' => 'CA', 'name' => 'Chartered Accountant', 'level' => 'professional-certification', 'sort_order' => 5],
            ['code' => 'CISSP', 'name' => 'Certified Information Systems Security Professional', 'level' => 'professional-certification', 'sort_order' => 6],
            ['code' => 'FRM', 'name' => 'Financial Risk Manager', 'level' => 'professional-certification', 'sort_order' => 7],
            ['code' => 'ACCA', 'name' => 'Association of Chartered Certified Accountants', 'level' => 'professional-certification', 'sort_order' => 8],
            ['code' => 'CIMA', 'name' => 'Chartered Institute of Management Accountants', 'level' => 'professional-certification', 'sort_order' => 9],
            ['code' => 'SHRM', 'name' => 'Society for Human Resource Management', 'level' => 'professional-certification', 'sort_order' => 10],
        ];

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($degreeTypes as $typeData) {
            // Get level ID
            $levelSlug = $typeData['level'];
            if (!isset($degreeLevels[$levelSlug])) {
                $this->command->error("Degree level '{$levelSlug}' not found for {$typeData['name']}");
                continue;
            }

            $levelId = $degreeLevels[$levelSlug];

            // Generate slug from name if not provided
            $slug = Str::slug($typeData['name']);

            $data = [
                'degree_level_id' => $levelId,
                'code' => $typeData['code'],
                'name' => $typeData['name'],
                'slug' => $slug,
                'sort_order' => $typeData['sort_order'],
                'is_active' => true,
            ];

            // Check if exists by code or name
            $exists = DegreeType::where('code', $typeData['code'])
                ->orWhere('slug', $slug)
                ->first();

            if ($exists) {
                $exists->update($data);
                $updatedCount++;
            } else {
                DegreeType::create($data);
                $createdCount++;
            }
        }

    }
}
