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
                ['name' => 'Department of Business Administration', 'short_name' => 'BBA', 'code' => 'BBA', 'description' => 'Offers comprehensive business education covering management principles, strategic planning, organizational behavior, and leadership skills for future business professionals.'],
                ['name' => 'Department of Management', 'short_name' => 'MGT', 'code' => 'MGT', 'description' => 'Focuses on management theories, human resource management, operations management, and organizational development for effective business leadership.'],
                ['name' => 'Department of Real Estate', 'short_name' => 'RE', 'code' => 'RE', 'description' => 'Provides specialized education in real estate development, property management, valuation, and urban planning for the growing real estate industry.'],
                ['name' => 'Department of Tourism & Hospitality Management', 'short_name' => 'THM', 'code' => 'THM', 'description' => 'Prepares students for careers in tourism, hotel management, event planning, and hospitality services with practical industry exposure.'],
                ['name' => 'Department of Innovation & Entrepreneurship', 'short_name' => 'IE', 'code' => 'IE', 'description' => 'Fosters entrepreneurial mindset, startup development, innovation management, and venture creation skills for aspiring entrepreneurs.'],
                ['name' => 'Department of Accounting', 'short_name' => 'ACC', 'code' => 'ACC', 'description' => 'Provides expertise in financial accounting, auditing, taxation, cost accounting, and financial reporting standards for accounting professionals.'],
                ['name' => 'Department of Finance & Banking', 'short_name' => 'FNB', 'code' => 'FNB', 'description' => 'Covers financial management, investment analysis, banking operations, risk management, and capital markets for finance professionals.'],
                ['name' => 'Department of Marketing', 'short_name' => 'MKT', 'code' => 'MKT', 'description' => 'Focuses on marketing strategies, consumer behavior, digital marketing, brand management, and market research for marketing professionals.'],
            ],

            // Faculty of Science and Information Technology
            'FSIT' => [
                ['name' => 'Department of Computer Science and Engineering', 'short_name' => 'CSE', 'code' => 'CSE', 'description' => 'Offers comprehensive computer science education including programming, algorithms, data structures, AI, machine learning, and software development.'],
                ['name' => 'Department of Software Engineering', 'short_name' => 'SWE', 'code' => 'SWE', 'description' => 'Focuses on software development lifecycle, agile methodologies, quality assurance, DevOps, and modern software architecture practices.'],
                ['name' => 'Department of Multimedia & Creative Technology', 'short_name' => 'MCT', 'code' => 'MCT', 'description' => 'Combines creativity with technology covering animation, game development, visual effects, UI/UX design, and digital content creation.'],
                ['name' => 'Department of Computing and Information System', 'short_name' => 'CIS', 'code' => 'CIS', 'description' => 'Focuses on information systems design, database management, business analytics, and enterprise solutions for IT-business integration.'],
                ['name' => 'Department of Information Technology & Management', 'short_name' => 'ITM', 'code' => 'ITM', 'description' => 'Combines IT skills with management knowledge covering IT project management, systems analysis, and technology leadership.'],
            ],

            // Faculty of Engineering
            'FE' => [
                ['name' => 'Department of Information and Communication Engineering', 'short_name' => 'ICE', 'code' => 'ICE', 'description' => 'Covers telecommunication systems, network engineering, signal processing, and wireless communication technologies.'],
                ['name' => 'Department of Textile Engineering', 'short_name' => 'TE', 'code' => 'TE', 'description' => 'Provides education in textile manufacturing, fabric technology, garment production, and apparel merchandising for Bangladesh\'s key industry.'],
                ['name' => 'Department of Electrical and Electronic Engineering', 'short_name' => 'EEE', 'code' => 'EEE', 'description' => 'Covers electrical systems, electronics, power generation, control systems, and embedded systems for electrical engineering professionals.'],
                ['name' => 'Department of Architecture', 'short_name' => 'ARCH', 'code' => 'ARCH', 'description' => 'Focuses on architectural design, urban planning, sustainable building practices, and interior design for future architects.'],
                ['name' => 'Department of Civil Engineering', 'short_name' => 'CE', 'code' => 'CE', 'description' => 'Covers structural engineering, construction management, transportation systems, and environmental engineering for infrastructure development.'],
            ],

            // Faculty of Health and Life Sciences
            'FHLS' => [
                ['name' => 'Department of Environmental Science and Disaster Management', 'short_name' => 'ESDM', 'code' => 'ESDM', 'description' => 'Focuses on environmental protection, climate change, disaster preparedness, and sustainable development practices.'],
                ['name' => 'Department of Pharmacy', 'short_name' => 'PHR', 'code' => 'PHR', 'description' => 'Provides pharmaceutical education covering drug development, pharmacology, clinical pharmacy, and pharmaceutical management.'],
                ['name' => 'Department of Nutrition and Food Engineering', 'short_name' => 'NFE', 'code' => 'NFE', 'description' => 'Covers food science, nutrition, food processing technology, and food safety for the food and nutrition industry.'],
                ['name' => 'Department of Public Health', 'short_name' => 'PH', 'code' => 'PH', 'description' => 'Focuses on community health, epidemiology, health policy, and disease prevention for public health professionals.'],
                ['name' => 'Department of Physical Education & Sports Science', 'short_name' => 'PESS', 'code' => 'PESS', 'description' => 'Covers sports science, fitness training, sports management, and physical education for sports professionals.'],
                ['name' => 'Department of Agricultural Science', 'short_name' => 'AGS', 'code' => 'AGS', 'description' => 'Provides education in agricultural practices, crop science, agribusiness, and sustainable farming for agricultural development.'],
                ['name' => 'Department of Genetic Engineering and Biotechnology', 'short_name' => 'GEB', 'code' => 'GEB', 'description' => 'Covers molecular biology, genetic engineering, bioinformatics, and biotechnology applications for life science research.'],
            ],

            // Faculty of Humanities & Social Sciences
            'FHSS' => [
                ['name' => 'Department of English', 'short_name' => 'ENG', 'code' => 'ENG', 'description' => 'Focuses on English language, literature, linguistics, and communication skills for teaching and professional communication careers.'],
                ['name' => 'Department of Law', 'short_name' => 'LAW', 'code' => 'LAW', 'description' => 'Provides legal education covering constitutional law, criminal law, corporate law, and legal practice for future lawyers and legal professionals.'],
                ['name' => 'Department of Journalism, Media and Communication', 'short_name' => 'JMC', 'code' => 'JMC', 'description' => 'Covers journalism, mass communication, broadcast media, and digital media for media professionals and communicators.'],
                ['name' => 'Department of Development Studies', 'short_name' => 'DS', 'code' => 'DS', 'description' => 'Focuses on development theories, social policy, poverty alleviation, and sustainable development for development professionals.'],
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
