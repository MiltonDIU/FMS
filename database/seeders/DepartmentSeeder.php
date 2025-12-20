<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            // Faculty of Business & Entrepreneurship
            'FBE' => [
                ['name' => 'Department of Business Administration', 'short_name' => 'BBA', 'code' => 'BBA'],
                ['name' => 'Department of Management', 'short_name' => 'MGT', 'code' => 'MGT'],
                ['name' => 'Department of Real Estate', 'short_name' => 'RE', 'code' => 'RE'],
                ['name' => 'Department of Tourism & Hospitality Management', 'short_name' => 'THM', 'code' => 'THM'],
                ['name' => 'Department of Innovation & Entrepreneurship', 'short_name' => 'IE', 'code' => 'IE'],
                ['name' => 'Department of Accounting', 'short_name' => 'ACC', 'code' => 'ACC'],
                ['name' => 'Department of Finance & Banking', 'short_name' => 'FNB', 'code' => 'FNB'],
                ['name' => 'Department of Marketing', 'short_name' => 'MKT', 'code' => 'MKT'],
            ],

            // Faculty of Science and Information Technology
            'FSIT' => [
                ['name' => 'Department of Computer Science and Engineering', 'short_name' => 'CSE', 'code' => 'CSE'],
                ['name' => 'Department of Software Engineering', 'short_name' => 'SWE', 'code' => 'SWE'],
                ['name' => 'Department of Multimedia & Creative Technology', 'short_name' => 'MCT', 'code' => 'MCT'],
                ['name' => 'Department of Computing and Information System', 'short_name' => 'CIS', 'code' => 'CIS'],
                ['name' => 'Department of Information Technology & Management', 'short_name' => 'ITM', 'code' => 'ITM'],
            ],

            // Faculty of Engineering
            'FE' => [
                ['name' => 'Department of Information and Communication Engineering', 'short_name' => 'ICE', 'code' => 'ICE'],
                ['name' => 'Department of Textile Engineering', 'short_name' => 'TE', 'code' => 'TE'],
                ['name' => 'Department of Electrical and Electronic Engineering', 'short_name' => 'EEE', 'code' => 'EEE'],
                ['name' => 'Department of Architecture', 'short_name' => 'ARCH', 'code' => 'ARCH'],
                ['name' => 'Department of Civil Engineering', 'short_name' => 'CE', 'code' => 'CE'],
            ],

            // Faculty of Health and Life Sciences
            'FHLS' => [
                ['name' => 'Department of Environmental Science and Disaster Management', 'short_name' => 'ESDM', 'code' => 'ESDM'],
                ['name' => 'Department of Pharmacy', 'short_name' => 'PHR', 'code' => 'PHR'],
                ['name' => 'Department of Nutrition and Food Engineering', 'short_name' => 'NFE', 'code' => 'NFE'],
                ['name' => 'Department of Public Health', 'short_name' => 'PH', 'code' => 'PH'],
                ['name' => 'Department of Physical Education & Sports Science', 'short_name' => 'PESS', 'code' => 'PESS'],
                ['name' => 'Department of Agricultural Science', 'short_name' => 'AGS', 'code' => 'AGS'],
                ['name' => 'Department of Genetic Engineering and Biotechnology', 'short_name' => 'GEB', 'code' => 'GEB'],
            ],

            // Faculty of Humanities & Social Sciences
            'FHSS' => [
                ['name' => 'Department of English', 'short_name' => 'ENG', 'code' => 'ENG'],
                ['name' => 'Department of Law', 'short_name' => 'LAW', 'code' => 'LAW'],
                ['name' => 'Department of Journalism, Media and Communication', 'short_name' => 'JMC', 'code' => 'JMC'],
                ['name' => 'Department of Development Studies', 'short_name' => 'DS', 'code' => 'DS'],
            ],
        ];

        $sortOrder = 1;

        foreach ($departments as $facultyCode => $deptList) {
            $faculty = Faculty::where('code', $facultyCode)->first();

            if (!$faculty) {
                continue;
            }

            foreach ($deptList as $dept) {
                Department::updateOrCreate(
                    ['code' => $dept['code']],
                    [
                        'faculty_id' => $faculty->id,
                        'name' => $dept['name'],
                        'short_name' => $dept['short_name'],
                        'code' => $dept['code'],
                        'is_active' => true,
                        'sort_order' => $sortOrder++,
                    ]
                );
            }
        }
    }
}
