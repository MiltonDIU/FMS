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
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'name' => trim($this->first_name . ($this->middle_name ? ' ' . $this->middle_name : '') . ($this->last_name ? ' ' . $this->last_name : '')),
            'webpage' => $this->webpage,
            'photo' => $this->photo,
            'designation' => $this->designation ? $this->designation->name : null,
            'designation_sort_order' => $this->designation ? (int) $this->designation->sort_order : 9999,
            'department_id' => $this->department_id,
            'email' => $this->email,
            'personal_phone' => $this->personal_phone,
            'phone' => $this->phone,
            'secondary_email' => $this->secondary_email,
            'bio' => $this->bio,
            'research_interest' => $this->research_interest,
            'is_active' => (bool) $this->is_active,
            'is_archived' => (bool) $this->is_archived,
            'sort_order' => $this->sort_order,
            
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

                        if ($request->has('department_code')) {
                            $dept = \App\Models\Department::where('code', $request->query('department_code'))->first();
                            if ($dept) {
                                $targetDeptId = $dept->id;
                                $targetFacId = $dept->faculty_id;
                            }
                        }

                        if ($role->pivot->department_id == $targetDeptId) {
                            return true;
                        }
                        if ($targetFacId && $role->pivot->faculty_id == $targetFacId) {
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
                'degree' => $e->degree_name,
                'institution' => $e->institution_name,
                'year' => $e->passing_year,
                'result' => $e->result,
            ]) : [],
            
            'publications' => $this->relationLoaded('publications') ? $this->publications->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'journal' => $p->journal_name,
                'year' => $p->publication_year,
                'link' => $p->paper_link,
                'impact_factor' => $p->impact_factor,
                'citescore' => $p->citescore,
                'h_index' => $p->h_index,
                'abstract' => $p->abstract,
                'keywords' => $p->keywords,
            ]) : [],
            
            'trainingExperiences' => $this->relationLoaded('trainingExperiences') ? $this->trainingExperiences->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'institution' => $t->institution_name,
                'duration' => $t->duration,
            ]) : [],
            
            'awards' => $this->relationLoaded('awards') ? $this->awards->map(fn($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->awarding_body,
                'year' => $a->year,
            ]) : [],
            
            'memberships' => $this->relationLoaded('memberships') ? $this->memberships->map(fn($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'role' => $m->membership_role,
            ]) : [],
            
            'social_links' => $this->relationLoaded('socialLinks') ? $this->socialLinks->map(fn($s) => [
                'platform' => $s->platform ? $s->platform->name : 'Social',
                'url' => $s->url,
            ]) : [],
            
            'department' => ($this->relationLoaded('department') && $this->department) ? [
                'id' => $this->department->code,
                'name' => $this->department->name,
            ] : null,
        ];
    }
}
