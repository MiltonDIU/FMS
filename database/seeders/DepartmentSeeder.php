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
                ['name' => 'Business Administration', 'short_name' => 'BBA', 'code' => 'BBA', 'description' => 'Offers comprehensive business education covering management principles, strategic planning, organizational behavior, and leadership skills for future business professionals.'],
                ['name' => 'Management', 'short_name' => 'MGT', 'code' => 'MGT', 'description' => 'Focuses on management theories, human resource management, operations management, and organizational development for effective business leadership.'],
                ['name' => 'Real Estate', 'short_name' => 'RE', 'code' => 'RE', 'description' => 'Provides specialized education in real estate development, property management, valuation, and urban planning for the growing real estate industry.'],
                ['name' => 'Tourism & Hospitality Management', 'short_name' => 'THM', 'code' => 'THM', 'description' => 'Prepares students for careers in tourism, hotel management, event planning, and hospitality services with practical industry exposure.'],
                ['name' => 'Innovation & Entrepreneurship', 'short_name' => 'IE', 'code' => 'IE', 'description' => 'Fosters entrepreneurial mindset, startup development, innovation management, and venture creation skills for aspiring entrepreneurs.'],
                ['name' => 'Accounting', 'short_name' => 'ACC', 'code' => 'ACC', 'description' => 'Provides expertise in financial accounting, auditing, taxation, cost accounting, and financial reporting standards for accounting professionals.'],
                ['name' => 'Finance & Banking', 'short_name' => 'FNB', 'code' => 'FNB', 'description' => 'Covers financial management, investment analysis, banking operations, risk management, and capital markets for finance professionals.'],
                ['name' => 'Marketing', 'short_name' => 'MKT', 'code' => 'MKT', 'description' => 'Focuses on marketing strategies, consumer behavior, digital marketing, brand management, and market research for marketing professionals.'],
            ],
            // Faculty of Engineering
            'FE' => [
                ['name' => 'Information and Communication Engineering', 'short_name' => 'ICE', 'code' => 'ICE', 'description' => 'Covers telecommunication systems, network engineering, signal processing, and wireless communication technologies.'],
                ['name' => 'Textile Engineering', 'short_name' => 'TE', 'code' => 'TE', 'description' => 'Provides education in textile manufacturing, fabric technology, garment production, and apparel merchandising for Bangladesh\'s key industry.'],
                ['name' => 'Electrical and Electronic Engineering', 'short_name' => 'EEE', 'code' => 'EEE', 'description' => 'Covers electrical systems, electronics, power generation, control systems, and embedded systems for electrical engineering professionals.'],
                ['name' => 'Architecture', 'short_name' => 'ARCH', 'code' => 'ARCH', 'description' => 'Focuses on architectural design, urban planning, sustainable building practices, and interior design for future architects.'],
                ['name' => 'Civil Engineering', 'short_name' => 'CE', 'code' => 'CE', 'description' => 'Covers structural engineering, construction management, transportation systems, and environmental engineering for infrastructure development.'],
            ],
            // Faculty of Health and Life Sciences
            'FHLS' => [
                ['name' => 'Environmental Science and Disaster Management', 'short_name' => 'ESDM', 'code' => 'ESDM', 'description' => 'Focuses on environmental protection, climate change, disaster preparedness, and sustainable development practices.'],
                ['name' => 'Pharmacy', 'short_name' => 'PHR', 'code' => 'PHR', 'description' => 'Provides pharmaceutical education covering drug development, pharmacology, clinical pharmacy, and pharmaceutical management.'],
                ['name' => 'Nutrition and Food Engineering', 'short_name' => 'NFE', 'code' => 'NFE', 'description' => 'Covers food science, nutrition, food processing technology, and food safety for the food and nutrition industry.'],
                ['name' => 'Public Health', 'short_name' => 'PH', 'code' => 'PH', 'description' => 'Focuses on community health, epidemiology, health policy, and disease prevention for public health professionals.'],
                ['name' => 'Physical Education & Sports Science', 'short_name' => 'PESS', 'code' => 'PESS', 'description' => 'Covers sports science, fitness training, sports management, and physical education for sports professionals.'],
                ['name' => 'Genetic Engineering and Biotechnology', 'short_name' => 'GEB', 'code' => 'GEB', 'description' => 'Covers molecular biology, genetic engineering, bioinformatics, and biotechnology applications for life science research.'],
            ],
            /*
             * Faculty of Agriculture Sciences.
             *
             * Both of these were under Health and Life Sciences until the
             * faculty they actually belong to was added to FacultySeeder —
             * Agricultural Science because there was nowhere better to put it,
             * and Fisheries because it did not exist here at all and its four
             * teachers had nowhere to land.
             *
             * updateOrCreate keys on the code, so a re-seed moves the existing
             * rows across rather than making a second copy. Agricultural
             * Science's public address changes with it, from /fhls/ags to
             * /fas/ags.
             */
            'FAS' => [
                ['name' => 'Agricultural Science', 'short_name' => 'AGS', 'code' => 'AGS', 'description' => 'Provides education in agricultural practices, crop science, agribusiness, and sustainable farming for agricultural development.'],
                ['name' => 'Fisheries', 'short_name' => 'FISH', 'code' => 'FISHERIES', 'description' => 'Covers fisheries science, aquaculture, aquatic resource management, and fish processing for the fisheries and aquaculture sector.'],
            ],
            // Faculty of Humanities & Social Sciences
            'FHSS' => [
                ['name' => 'English', 'short_name' => 'ENG', 'code' => 'ENG', 'description' => 'Focuses on English language, literature, linguistics, and communication skills for teaching and professional communication careers.'],
                ['name' => 'Law', 'short_name' => 'LAW', 'code' => 'LAW', 'description' => 'Provides legal education covering constitutional law, criminal law, corporate law, and legal practice for future lawyers and legal professionals.'],
                ['name' => 'Journalism, Media and Communication', 'short_name' => 'JMC', 'code' => 'JMC', 'description' => 'Covers journalism, mass communication, broadcast media, and digital media for media professionals and communicators.'],
                ['name' => 'Development Studies', 'short_name' => 'DS', 'code' => 'DS', 'description' => 'Focuses on development theories, social policy, poverty alleviation, and sustainable development for development professionals.'],
            ],
            // Faculty of Science and Information Technology
            'FSIT' => [
                ['name' => 'Computer Science and Engineering', 'short_name' => 'CSE', 'code' => 'CSE', 'description' => 'Offers comprehensive computer science education including programming, algorithms, data structures, AI, machine learning, and software development.'],
                ['name' => 'Software Engineering', 'short_name' => 'SWE', 'code' => 'SWE', 'description' => 'Focuses on software development lifecycle, agile methodologies, quality assurance, DevOps, and modern software architecture practices.'],
                ['name' => 'Multimedia & Creative Technology', 'short_name' => 'MCT', 'code' => 'MCT', 'description' => 'Combines creativity with technology covering animation, game development, visual effects, UI/UX design, and digital content creation.'],
                ['name' => 'Computing and Information System', 'short_name' => 'CIS', 'code' => 'CIS', 'description' => 'Focuses on information systems design, database management, business analytics, and enterprise solutions for IT-business integration.'],
                ['name' => 'Information Technology & Management', 'short_name' => 'ITM', 'code' => 'ITM', 'description' => 'Combines IT skills with management knowledge covering IT project management, systems analysis, and technology leadership.'],
                /*
                 * Listed here rather than under Engineering, which is where this
                 * file had it. The old database declares it under Science and
                 * Information Technology and every one of its six teacher rows
                 * agrees, so this follows the old structure. Its public address
                 * moves with it, from /fe/rme to /fsit/rme.
                 */
                ['name' => 'Robotics and Mechatronics Engineering', 'short_name' => 'rme', 'code' => 'rme', 'description' => 'The Department of Robotics and Mechatronics Engineering integrates mechanical, electronic, and computer systems to develop intelligent robots, automation technologies, and smart industrial solutions.'],
            ],

            'SUF' => [
                ['name' => 'System - Unassigned Department', 'short_name' => 'SUD', 'code' => 'SUD', 'description' => 'System - Unassigned Department'],
                /*
                 * Also from the old site, and parked here rather than guessed at.
                 *
                 * Its old row carries faculty_id = 0 — no faculty was ever
                 * recorded for it — so any faculty chosen here would be an
                 * invention. Nobody is attached to it either, so nothing is
                 * waiting on the answer; move this line into whichever faculty
                 * owns it and the next seed puts it there.
                 */
               // ['name' => 'General Educational Development', 'short_name' => 'GED', 'code' => 'GED', 'description' => 'Delivers the general education and foundation courses taken across the university.'],
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
                        'description' => $dept['description'],
                        'is_active' => true,
                        'sort_order' => $sortOrder++,
                    ]
                );
            }
        }
    }
}
