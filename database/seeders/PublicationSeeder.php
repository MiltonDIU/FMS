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

class PublicationSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = Teacher::first();
        if (!$teacher) return;

        $type = PublicationType::where('slug', 'journal-article')->first();
        $linkage = PublicationLinkage::where('slug', 'scopus')->first();
        $quartile = PublicationQuartile::where('slug', 'q1')->first();
        $grant = GrantType::where('slug', 'self-funded')->first();
        $collab = ResearchCollaboration::where('slug', 'diu-researcher')->first();

        $pub = Publication::create([
            'publication_type_id' => $type?->id,
            'publication_linkage_id' => $linkage?->id,
            'publication_quartile_id' => $quartile?->id,
            'grant_type_id' => $grant?->id,
            'research_collaboration_id' => $collab?->id,
            'title' => 'Sample Research Paper on advanced AI Agents',
            'journal_name' => 'Journal of AI Research',
            'publication_date' => now()->subMonth(),
            'publication_year' => 2024,
            'status' => 'approved',
            'is_featured' => true,
        ]);

        // Attach Authors
        // First Author: The teacher
        $pub->teachers()->attach($teacher->id, ['author_role' => 'first', 'sort_order' => 0]);
        
        // Find another teacher for co-author if exists
        $otherTeacher = Teacher::where('id', '!=', $teacher->id)->first();
        if ($otherTeacher) {
             $pub->teachers()->attach($otherTeacher->id, ['author_role' => 'co_author', 'sort_order' => 1]);
        }
    }
}
