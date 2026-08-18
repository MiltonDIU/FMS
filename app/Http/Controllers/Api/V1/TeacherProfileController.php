<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesDirectoryPath;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PublicationResource;
use App\Http\Resources\V1\TeacherResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * One teacher's profile, and the publications on it.
 *
 * Publications are a separate, paged endpoint rather than part of the profile.
 * They are the only part of a profile with no natural ceiling, so folding them
 * in would make the size of a profile depend on how prolific the person is.
 */
class TeacherProfileController extends Controller
{
    use ResolvesDirectoryPath;

    public const PUBLICATIONS_PER_PAGE = 20;

    /**
     * Everything the profile screen shows, in one request.
     *
     * ?include= trims it: pass a comma-separated list of sections and only
     * those are loaded. An app showing a summary card does not need every
     * relation, and on a phone connection that is a difference worth having.
     */
    public function show(Request $request, string $faculty, string $department, string $webpage): TeacherResource
    {
        $teacher = $this->resolveTeacher($faculty, $department, $webpage);

        $teacher->load($this->relations($request));
        $teacher->loadCount('publications');

        return new TeacherResource($teacher);
    }

    public function publications(Request $request, string $faculty, string $department, string $webpage): AnonymousResourceCollection
    {
        $teacher = $this->resolveTeacher($faculty, $department, $webpage);

        $publications = $teacher->publications()
            ->with(['type', 'quartile'])
            // Newest first, by year: an exact date exists for about one record
            // in twelve, so ordering by it would scatter the rest arbitrarily.
            ->orderByDesc('publication_year')
            ->orderByDesc('publications.id')
            ->paginate(min(max((int) $request->query('per_page', self::PUBLICATIONS_PER_PAGE), 1), 100))
            ->withQueryString();

        return PublicationResource::collection($publications);
    }

    /**
     * Which relations to eager-load, from ?include=.
     *
     * @return array<int, string>
     */
    protected function relations(Request $request): array
    {
        $optional = [
            'education' => ['educations.degreeType', 'educations.degreeLevel', 'educations.resultType', 'educations.educationalInstitution'],
            'experience' => ['jobExperiences.positionRelation', 'jobExperiences.organizationRelation'],
            'training' => ['trainingExperiences.organizationRelation'],
            'awards' => ['awards.awardingBodyOrganizationRelation'],
            'memberships' => ['memberships.membershipType', 'memberships.membershipOrganization'],
            'teaching_areas' => ['teachingAreas'],
            'research_interests' => ['researchInterests'],
            'skills' => ['skills'],
            'research' => ['researchProjects'],
            'social_links' => ['socialLinks.platform'],
        ];

        // Always loaded: without these the record does not identify anybody.
        $always = ['designation', 'department.faculty', 'employmentStatus', 'user', 'administrativeRoles.administrativeRole', 'media'];

        if (blank($asked = $request->query('include'))) {
            return array_merge($always, ...array_values($optional));
        }

        $sections = array_filter(array_map('trim', explode(',', (string) $asked)));
        $selected = array_intersect_key($optional, array_flip($sections));

        return array_merge($always, ...array_values($selected));
    }
}
