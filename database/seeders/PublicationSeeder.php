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

        // Create 50 sample publications
        for ($i = 0; $i < 50; $i++) {
            $currDept = $departments->random();
            // Pick a teacher from this department preferably, or random if none
            $author = $currDept->teachers()->inRandomOrder()->first() ?? Teacher::inRandomOrder()->first();
            
            if (!$author) continue;

            $year = rand(2020, 2024);
            
            $pub = Publication::create([
                'faculty_id' => $currDept->faculty_id,
                'department_id' => $currDept->id,
                'publication_type_id' => $types->random(),
                'publication_linkage_id' => $linkages->isNotEmpty() ? $linkages->random() : null,
                'publication_quartile_id' => $quartiles->isNotEmpty() ? $quartiles->random() : null,
                'grant_type_id' => $grants->isNotEmpty() ? $grants->random() : null,
                'research_collaboration_id' => $collabs->isNotEmpty() ? $collabs->random() : null,
                
                'title' => fake()->sentence(rand(6, 12)),
                'journal_name' => fake()->randomElement(['Journal of AI Research', 'IEEE Transactions', 'Nature Scientific Reports', 'Springer CS', 'Elsevier Data Science']),
                'journal_link' => fake()->url(),
                'publication_date' => fake()->dateTimeBetween("-{$year} years", 'now'),
                'publication_year' => $year,
                
                'research_area' => fake()->randomElement(['Artificial Intelligence', 'Machine Learning', 'Data Science', 'IoT', 'Cyber Security']),
                'abstract' => fake()->paragraph(),
                'keywords' => implode(', ', fake()->words(5)),
                
                'h_index' => rand(0, 20),
                'citescore' => rand(0, 100) / 10, // 0.0 to 10.0
                'impact_factor' => rand(0, 500) / 100, // 0.00 to 5.00
                'student_involvement' => fake()->boolean(30),
                'is_featured' => fake()->boolean(20),
                'status' => 'approved',
                'sort_order' => 0,
            ]);

            // Attach Authors
            // First Author
            $pub->teachers()->attach($author->id, ['author_role' => 'first', 'sort_order' => 0]);
            
            // Maybe add co-authors
            if (fake()->boolean(60)) {
                $coAuthors = Teacher::where('id', '!=', $author->id)->inRandomOrder()->take(rand(1, 3))->get();
                foreach ($coAuthors as $index => $coAuthor) {
                    $pub->teachers()->attach($coAuthor->id, ['author_role' => 'co_author', 'sort_order' => $index + 1]);
                }
            }
        }
    }
}
