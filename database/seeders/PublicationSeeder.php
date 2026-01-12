<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Publication;
use App\Models\Teacher;
use App\Models\PublicationType;
use App\Models\PublicationLinkage;
use App\Models\PublicationQuartile;
use App\Models\GrantType;
use App\Models\ResearchCollaboration;
use App\Models\Department;
use App\Models\PublicationIncentive;
use App\Models\User;

class PublicationSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure we have required data
        if (Teacher::count() == 0) return;
        
        $types = PublicationType::where('is_active', true)->pluck('id');
        $linkages = PublicationLinkage::where('is_active', true)->pluck('id');
        $quartiles = PublicationQuartile::where('is_active', true)->pluck('id');
        $grants = GrantType::where('is_active', true)->pluck('id');
        $collabs = ResearchCollaboration::where('is_active', true)->pluck('id');
        $departments = Department::where('is_active', true)->get();

        if ($departments->isEmpty()) return;

        // Get an admin user for approvals
        $adminUser = User::where('email', 'like', '%admin%')->first() ?? User::first();

        // Research areas for realistic data
        $researchAreas = [
            'Artificial Intelligence', 'Machine Learning', 'Deep Learning',
            'Data Science', 'Big Data Analytics', 'IoT', 'Cyber Security',
            'Cloud Computing', 'Blockchain', 'Natural Language Processing',
            'Computer Vision', 'Robotics', 'Quantum Computing',
            'Software Engineering', 'Human-Computer Interaction',
        ];

        // Create 50 sample publications
        for ($i = 0; $i < 50; $i++) {
            $currDept = $departments->random();
            $author = $currDept->teachers()->inRandomOrder()->first() ?? Teacher::inRandomOrder()->first();
            
            if (!$author) continue;

            $year = rand(2020, 2024);
            
            // Generate realistic abstract
            $abstractTopics = ['This study investigates', 'This paper presents', 'We propose', 'This research explores', 'In this work, we analyze'];
            $abstract = fake()->randomElement($abstractTopics) . ' ' . fake()->paragraph(4) . ' ' . fake()->paragraph(3);
            
            // Generate keywords
            $keywordPool = ['machine learning', 'deep learning', 'neural networks', 'data mining', 'classification', 'prediction', 'optimization', 'algorithm', 'model', 'framework', 'system', 'analysis', 'evaluation', 'performance', 'accuracy'];
            $keywords = implode(', ', fake()->randomElements($keywordPool, rand(4, 7)));
            
            $pub = Publication::create([
                'faculty_id' => $currDept->faculty_id,
                'department_id' => $currDept->id,
                'publication_type_id' => $types->random(),
                'publication_linkage_id' => $linkages->isNotEmpty() ? $linkages->random() : null,
                'publication_quartile_id' => $quartiles->isNotEmpty() ? $quartiles->random() : null,
                'grant_type_id' => $grants->isNotEmpty() ? $grants->random() : null,
                'research_collaboration_id' => $collabs->isNotEmpty() ? $collabs->random() : null,
                
                'title' => fake()->sentence(rand(8, 15)),
                'journal_name' => fake()->randomElement([
                    'IEEE Transactions on Neural Networks',
                    'Nature Scientific Reports',
                    'Springer Journal of Computer Science',
                    'Elsevier Data Science Journal',
                    'ACM Computing Surveys',
                    'Journal of Artificial Intelligence Research',
                    'International Journal of Machine Learning',
                ]),
                'journal_link' => fake()->url(),
                'publication_date' => fake()->dateTimeBetween("{$year}-01-01", "{$year}-12-31"),
                'publication_year' => $year,
                
                // Filled fields
                'research_area' => fake()->randomElement($researchAreas),
                'abstract' => $abstract,
                'keywords' => $keywords,
                'h_index' => rand(5, 50),
                'citescore' => rand(10, 100) / 10, // 1.0 to 10.0
                'impact_factor' => rand(5, 80) / 10, // 0.5 to 8.0
                'student_involvement' => fake()->boolean(40),
                'is_featured' => fake()->boolean(20),
                'status' => 'approved',
                'sort_order' => 0,
            ]);

            // Attach Authors with role distribution
            $authors = collect();
            
            // First Author (gets higher share)
            $authors->push([
                'teacher' => $author,
                'role' => 'first',
                'sort_order' => 0,
                'share_weight' => 40, // 40% share
            ]);
            
            // Maybe add corresponding author
            if (fake()->boolean(50)) {
                $corresponding = Teacher::where('id', '!=', $author->id)->inRandomOrder()->first();
                if ($corresponding) {
                    $authors->push([
                        'teacher' => $corresponding,
                        'role' => 'corresponding',
                        'sort_order' => 1,
                        'share_weight' => 30, // 30% share
                    ]);
                }
            }
            
            // Maybe add co-authors
            if (fake()->boolean(60)) {
                $existingIds = $authors->pluck('teacher.id')->toArray();
                $coAuthors = Teacher::whereNotIn('id', $existingIds)->inRandomOrder()->take(rand(1, 3))->get();
                $coAuthorWeight = 30 / max($coAuthors->count(), 1); // Split remaining 30%
                
                foreach ($coAuthors as $index => $coAuthor) {
                    $authors->push([
                        'teacher' => $coAuthor,
                        'role' => 'co_author',
                        'sort_order' => $authors->count(),
                        'share_weight' => $coAuthorWeight,
                    ]);
                }
            }

            // Normalize weights to exactly 100%
            $totalWeight = $authors->sum('share_weight');
            $authors = $authors->map(function ($a) use ($totalWeight) {
                $a['share_percent'] = $a['share_weight'] / $totalWeight * 100;
                return $a;
            });

            // Create incentive for some publications (70% chance)
            $hasIncentive = fake()->boolean(70);
            $totalIncentive = $hasIncentive ? rand(10, 100) * 1000 : 0; // 10,000 to 100,000

            // Attach authors with calculated incentive amounts
            foreach ($authors as $authorData) {
                $incentiveAmount = $hasIncentive 
                    ? round($totalIncentive * $authorData['share_percent'] / 100, 2)
                    : 0;
                
                $pub->teachers()->attach($authorData['teacher']->id, [
                    'author_role' => $authorData['role'],
                    'sort_order' => $authorData['sort_order'],
                    'incentive_amount' => $incentiveAmount,
                ]);
            }

            // Create PublicationIncentive record
            if ($hasIncentive) {
                $status = fake()->randomElement(['pending', 'approved', 'paid']);
                
                PublicationIncentive::withoutEvents(function () use ($pub, $totalIncentive, $status, $adminUser) {
                    PublicationIncentive::create([
                        'publication_id' => $pub->id,
                        'total_amount' => $totalIncentive,
                        'status' => $status,
                        'approved_by' => $status !== 'pending' ? $adminUser?->id : null,
                        'approved_at' => $status !== 'pending' ? now()->subDays(rand(1, 30)) : null,
                        'paid_by' => $status === 'paid' ? $adminUser?->id : null,
                        'paid_at' => $status === 'paid' ? now()->subDays(rand(1, 15)) : null,
                        'remarks' => $status === 'paid' ? 'Payment processed via bank transfer.' : null,
                    ]);
                });
            }
        }
    }
}

