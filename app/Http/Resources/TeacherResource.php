<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'webpage' => $this->webpage,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'name' => trim($this->first_name . ($this->middle_name ? ' ' . $this->middle_name : '') . ($this->last_name ? ' ' . $this->last_name : '')),
            'phone' => $this->phone,
            'extension_no' => $this->extension_no,
            'personal_phone' => $this->personal_phone,
            'email' => $this->email,
            'secondary_email' => $this->secondary_email,
            'date_of_birth' => $this->date_of_birth ? ($this->date_of_birth instanceof \Carbon\Carbon ? $this->date_of_birth->toDateString() : $this->date_of_birth) : null,
            'gender' => $this->gender ? $this->gender->name : null,
            'blood_group' => $this->bloodGroup ? $this->bloodGroup->name : null,
            'country' => $this->country ? $this->country->name : null,
            'religion' => $this->religion ? $this->religion->name : null,
            'present_address' => $this->present_address,
            'permanent_address' => $this->permanent_address,
            'joining_date' => $this->joining_date ? ($this->joining_date instanceof \Carbon\Carbon ? $this->joining_date->toDateString() : $this->joining_date) : null,
            'work_location' => $this->work_location,
            'office_room' => $this->office_room,
            'photo' => $this->getPhotoUrl(),
            'bio' => $this->bio,
            'research_interest' => $this->research_interest,
            'profile_status' => $this->profile_status,
            'is_public' => (bool) $this->is_public,
            'is_active' => (bool) $this->is_active,
            'login_allowed' => (bool) $this->login_allowed,
            'employment_status' => $this->employmentStatus ? $this->employmentStatus->name : null,
            'job_type' => $this->jobType ? $this->jobType->name : null,
            'is_archived' => (bool) $this->is_archived,
            'sort_order' => $this->sort_order,
            
            'designation' => $this->designation ? $this->designation->name : null,
            'designation_sort_order' => $this->designation ? (int) $this->designation->sort_order : 9999,
            
            'administrative_roles' => ($this->user && $this->user->relationLoaded('administrativeRoles'))
                ? $this->user->administrativeRoles
                    ->filter(function ($role) use ($request) {
                        $isActive = (bool) $role->pivot->is_active;
                        $notEnded = is_null($role->pivot->end_date) || \Carbon\Carbon::parse($role->pivot->end_date)->isFuture();

                        if (!$isActive || !$notEnded) {
                            return false;
                        }

                        $targetDeptId = $this->department_id;
                        $targetFacId = $this->department ? $this->department->faculty_id : null;

                        $deptCode = $request->query('department_code') ?? $request->route('dept');
                        if ($deptCode) {
                            $dept = \App\Models\Department::where('code', $deptCode)
                                ->orWhere('short_name', $deptCode)
                                ->first();
                            if ($dept) {
                                $targetDeptId = $dept->id;
                                $targetFacId = $dept->faculty_id;
                            }
                        }

                        if ($role->pivot->department_id == $targetDeptId) {
                            return true;
                        }
                        if (is_null($role->pivot->department_id) && $targetFacId && $role->pivot->faculty_id == $targetFacId) {
                            return true;
                        }
                        return false;
                    })
                    ->map(fn($r) => [
                        'id' => $r->id,
                        'name' => $r->name,
                        'short_name' => $r->short_name,
                        'sort_order' => (int) $r->sort_order,
                    ])->values()->toArray()
                : [],
            
            'educations' => $this->relationLoaded('educations') ? $this->educations->map(fn($e) => [
                'id' => $e->id,
                'degree_type' => $e->degreeType ? $e->degreeType->name : null,
                'country' => $e->country ? $e->country->name : 'Bangladesh',
                'result_type' => $e->resultType ? $e->resultType->name : null,
                'institution' => ($e->educationalInstitution ? $e->educationalInstitution->name : null) ?? $e->institution,
                'major' => ($e->majorRelation ? $e->majorRelation->name : null) ?? $e->major,
                'passing_year' => $e->passing_year,
                'duration' => $e->duration,
                'cgpa' => $e->cgpa,
                'scale' => $e->scale,
                'marks' => $e->marks,
                'grade' => $e->grade,
                'sort_order' => $e->sort_order,
            ]) : [],
            
            'publications' => $this->relationLoaded('publications') ? $this->publications->map(fn($p) => [
                'id' => $p->id,
                'type' => $p->type ? $p->type->name : null,
                'linkage' => $p->linkage ? $p->linkage->name : null,
                'quartile' => $p->quartile ? $p->quartile->name : null,
                'grant' => $p->grant ? $p->grant->name : null,
                'collaboration' => $p->collaboration ? $p->collaboration->name : null,
                'title' => $p->title,
                'journal_name' => $p->journal_name,
                'journal_link' => $p->journal_link,
                'publication_date' => $p->publication_date ? $p->publication_date->toDateString() : null,
                'publication_year' => $p->publication_year,
                'research_area' => $p->research_area,
                'h_index' => $p->h_index,
                'citescore' => $p->citescore,
                'impact_factor' => $p->impact_factor,
                'student_involvement' => (bool) $p->student_involvement,
                'keywords' => $p->keywords,
                'abstract' => $p->abstract,
                'status' => $p->status,
                'is_featured' => (bool) $p->is_featured,
                'sort_order' => $p->sort_order,
                'author_role' => $p->pivot ? $p->pivot->author_role : null,
                'incentive_amount' => $p->pivot ? $p->pivot->incentive_amount : null,
            ]) : [],
            
            'trainingExperiences' => $this->relationLoaded('trainingExperiences') ? $this->trainingExperiences->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'organization' => ($t->organizationRelation ? $t->organizationRelation->name : null) ?? $t->organization,
                'category' => $t->category,
                'duration_days' => $t->duration_days,
                'completion_date' => $t->completion_date ? $t->completion_date->toDateString() : null,
                'year' => $t->year,
                'country' => $t->countryRelation ? $t->countryRelation->name : 'Bangladesh',
                'certificate_url' => $t->certificate_url,
                'is_online' => (bool) $t->is_online,
                'description' => $t->description,
                'sort_order' => $t->sort_order,
            ]) : [],
            
            'awards' => $this->relationLoaded('awards') ? $this->awards->map(fn($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'awarding_body' => ($a->awardingBodyOrganizationRelation ? $a->awardingBodyOrganizationRelation->name : null) ?? $a->awarding_body,
                'type' => $a->type,
                'date' => $a->date ? $a->date->toDateString() : null,
                'year' => $a->year,
                'remarks' => $a->remarks,
                'attachment' => $a->attachment,
                'sort_order' => $a->sort_order,
            ]) : [],
            
            'memberships' => $this->relationLoaded('memberships') ? $this->memberships->map(fn($m) => [
                'id' => $m->id,
                'organization' => $m->membershipOrganization ? $m->membershipOrganization->name : null,
                'membership_type' => $m->membershipType ? $m->membershipType->name : null,
                'membership_id' => $m->membership_id,
                'record_type' => $m->record_type,
                'position' => $m->position,
                'scope' => $m->scope,
                'url' => $m->url,
                'start_date' => $m->start_date ? $m->start_date->toDateString() : null,
                'end_date' => $m->end_date ? $m->end_date->toDateString() : null,
                'status' => $m->status,
                'description' => $m->description,
                'sort_order' => $m->sort_order,
                'is_active' => (bool) $m->is_active,
            ]) : [],
            
            'social_links' => $this->relationLoaded('socialLinks') ? $this->socialLinks->map(fn($s) => [
                'id' => $s->id,
                'platform' => $s->platform ? $s->platform->name : 'Social',
                'username' => $s->username,
                'url' => $s->url,
                'sort_order' => $s->sort_order,
            ]) : [],

            'jobExperiences' => $this->relationLoaded('jobExperiences') ? $this->jobExperiences->map(fn($j) => [
                'id' => $j->id,
                'position' => ($j->positionRelation ? $j->positionRelation->name : null) ?? $j->position,
                'organization' => ($j->organizationRelation ? $j->organizationRelation->name : null) ?? $j->organization,
                'department' => $j->department,
                'location' => $j->location,
                'country' => ($j->countryRelation ? $j->countryRelation->name : null) ?? $j->country,
                'start_date' => $j->start_date ? $j->start_date->toDateString() : null,
                'end_date' => $j->end_date ? $j->end_date->toDateString() : null,
                'is_current' => (bool) $j->is_current,
                'responsibilities' => $j->responsibilities,
                'source' => $j->source,
                'source_reference_id' => $j->source_reference_id,
                'sort_order' => $j->sort_order,
            ]) : [],

            'certifications' => $this->relationLoaded('certifications') ? $this->certifications->map(fn($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'type' => $c->type,
                'issuing_authority' => ($c->issuingAuthorityOrganizationRelation ? $c->issuingAuthorityOrganizationRelation->name : null) ?? $c->issuing_authority,
                'issue_date' => $c->issue_date ? $c->issue_date->toDateString() : null,
                'expiry_date' => $c->expiry_date ? $c->expiry_date->toDateString() : null,
                'credential_id' => $c->credential_id,
                'credential_url' => $c->credential_url,
                'description' => $c->description,
                'sort_order' => $c->sort_order,
            ]) : [],

            'skills' => $this->relationLoaded('skills') ? $this->skills->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'proficiency' => $s->proficiency,
                'sort_order' => $s->sort_order,
            ]) : [],

            'teachingAreas' => $this->relationLoaded('teachingAreas') ? $this->teachingAreas->map(fn($t) => [
                'id' => $t->id,
                'area' => $t->area,
                'description' => $t->description,
                'sort_order' => $t->sort_order,
            ]) : [],

            'researchProjects' => $this->relationLoaded('researchProjects') ? $this->researchProjects->map(fn($r) => [
                'id' => $r->id,
                'title' => $r->title,
                'description' => $r->description,
                'project_leader' => $r->project_leader,
                'funding_agency' => ($r->fundingAgencyOrganizationRelation ? $r->fundingAgencyOrganizationRelation->name : null) ?? $r->funding_agency,
                'budget' => $r->budget,
                'currency' => $r->currency,
                'role' => $r->role,
                'start_date' => $r->start_date ? $r->start_date->toDateString() : null,
                'end_date' => $r->end_date ? $r->end_date->toDateString() : null,
                'status' => $r->status,
                'outcome' => $r->outcome,
                'sort_order' => $r->sort_order,
            ]) : [],
            
            'department' => ($this->relationLoaded('department') && $this->department) ? [
                'id' => $this->department->code,
                'name' => $this->department->name,
            ] : null,
        ];
    }

    private function getPhotoUrl(): ?string
    {
        $photo = $this->photo;
        if (empty($photo) && $this->employee_id) {
            $picture = \Illuminate\Support\Facades\Cache::remember('teacher_photo_' . $this->employee_id, 3600, function () {
                try {
                    $oldTeacher = \Illuminate\Support\Facades\DB::connection('old_db')
                        ->table('teacher')
                        ->where('employeeID', $this->employee_id)
                        ->first(['picture']);
                    return $oldTeacher ? $oldTeacher->picture : null;
                } catch (\Exception $e) {
                    return null;
                }
            });
            if ($picture) {
                $photo = $picture;
            }
        }

        if (empty($photo)) {
            return null;
        }

        return (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://'))
            ? $photo
            : 'https://faculty.daffodilvarsity.edu.bd/images/teacher/' . basename($photo);
    }
}
