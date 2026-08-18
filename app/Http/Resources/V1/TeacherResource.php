<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A teacher, at whatever depth the caller asked for.
 *
 * One class serves both the directory list and the full profile, because two
 * would drift: a field renamed in one and not the other is the sort of thing
 * nobody notices until an app shows a blank. The depth comes from what the
 * query eager-loaded, so a list stays a list — whenLoaded() emits nothing for
 * relations the caller never asked for, and a hundred-row page does not turn
 * into a hundred profile fetches.
 *
 * Personal contact details are here because the public profile page already
 * shows them to anyone who visits. Home address, date of birth and the
 * verification token are not, and never appear at any depth.
 */
class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->webpage,
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'initials' => $this->initials,

            'designation' => optional($this->whenLoaded('designation'))->name,
            'department' => new DepartmentResource($this->whenLoaded('department')),

            'photo_url' => $this->photo_url,
            'office_room' => $this->office_room,
            'phone' => $this->phone ?: $this->personal_phone,
            'email' => $this->whenLoaded('user', fn () => $this->user?->email) ?: $this->secondary_email,

            /*
             * Only stated when it is not the ordinary working status — the same
             * rule the website follows. 250 of the visible teachers are on study
             * or ordinary leave, and showing them as though they were at their
             * desk is the wrong answer.
             */
            'employment_status' => $this->public_status,

            'profile_score' => $this->profile_score,
            'views_count' => $this->views_count,

            // ── Present only when the caller asked for the full profile ──────
            'bio' => $this->when(filled($this->bio), $this->bio),

            'educations' => $this->whenLoaded('educations', fn () => $this->educations->map(fn ($e) => [
                'degree' => optional($e->degreeType)->name ?: optional($e->degreeLevel)->name,
                'level' => optional($e->degreeLevel)->name,
                'major' => $e->major ?: optional($e->majorRelation)->name,
                'institution' => $e->institution ?: optional($e->educationalInstitution)->name,
                'passing_year' => $e->passing_year,
                'result' => $this->educationResult($e),
            ])),

            'experiences' => $this->whenLoaded('jobExperiences', fn () => $this->jobExperiences->map(fn ($j) => [
                'position' => $j->position ?: optional($j->positionRelation)->name,
                'organization' => $j->organization ?: optional($j->organizationRelation)->name,
                'department' => $j->department,
                'location' => $j->location,
                'start_date' => optional($j->start_date)->toDateString(),
                'end_date' => optional($j->end_date)->toDateString(),
                'is_current' => (bool) $j->is_current,
                'responsibilities' => $j->responsibilities,
            ])),

            'trainings' => $this->whenLoaded('trainingExperiences', fn () => $this->trainingExperiences->map(fn ($t) => [
                'title' => $t->title,
                'organization' => $t->organization ?: optional($t->organizationRelation)->name,
                'category' => $t->category,
                'year' => $t->year,
                'duration_days' => $t->duration_days,
                'is_online' => (bool) $t->is_online,
                'country' => $t->country,
                'description' => $t->description,
            ])),

            'awards' => $this->whenLoaded('awards', fn () => $this->awards->map(fn ($a) => [
                'title' => $a->title,
                'awarding_body' => $a->awarding_body ?: optional($a->awardingBodyOrganizationRelation)->name,
                'type' => $a->type,
                'year' => $a->year,
                'remarks' => $a->remarks,
            ])),

            'memberships' => $this->whenLoaded('memberships', fn () => $this->memberships->map(fn ($m) => [
                'organization' => optional($m->membershipOrganization)->name,
                'type' => optional($m->membershipType)->name,
                'position' => $m->position,
                'scope' => $m->scope,
                'start_date' => optional($m->start_date)->toDateString(),
                'end_date' => optional($m->end_date)->toDateString(),
                'is_active' => (bool) $m->is_active,
                'url' => $m->url,
            ])),

            'teaching_areas' => $this->whenLoaded('teachingAreas', fn () => $this->teachingAreas->pluck('area')),
            // A list of names, like teaching_areas beside it. This replaced
            // `research_interest`, which was one comma-separated string that
            // every caller then split apart for itself.
            'research_interests' => $this->whenLoaded('researchInterests', fn () => $this->researchInterests->pluck('interest')),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->pluck('name')),

            'research_projects' => $this->whenLoaded('researchProjects', fn () => $this->researchProjects->map(fn ($p) => [
                'title' => $p->title,
                'role' => $p->role,
                'funding_agency' => $p->funding_agency,
                'status' => $p->status,
                'start_date' => optional($p->start_date)->toDateString(),
                'end_date' => optional($p->end_date)->toDateString(),
                'description' => $p->description,
            ])),

            'social_links' => $this->whenLoaded('socialLinks', fn () => $this->socialLinks->map(fn ($l) => [
                'platform' => optional($l->platform)->name,
                'url' => $l->url,
            ])),

            'administrative_roles' => $this->whenLoaded('administrativeRoles', fn () => $this->administrativeRoles
                ->map(fn ($r) => optional($r->administrativeRole)->name)
                ->filter()
                ->values()),

            'publications_count' => $this->whenCounted('publications'),
        ];
    }

    /**
     * The grade, from whichever column the record happens to carry it in.
     *
     * There is no single `result` column — it is cgpa with a scale, or a letter
     * grade, or a mark. The website had this wrong for a long time and showed
     * nothing at all.
     */
    protected function educationResult($education): ?string
    {
        return match (true) {
            filled($education->cgpa) && filled($education->scale) => $education->cgpa . ' / ' . $education->scale,
            filled($education->cgpa) => (string) $education->cgpa,
            filled($education->grade) => (string) $education->grade,
            filled($education->marks) => (string) $education->marks,
            default => optional($education->resultType)->name,
        };
    }
}
