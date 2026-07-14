<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => trim($this->first_name . ($this->middle_name ? ' ' . $this->middle_name : '') . ($this->last_name ? ' ' . $this->last_name : '')),
            'webpage' => $this->webpage,
            'photo' => $this->getPhotoUrl(),
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

                        // Exact department match
                        if ($role->pivot->department_id == $targetDeptId) {
                            return true;
                        }
                        // Faculty-wide role (department_id IS NULL means assigned to entire faculty)
                        if (is_null($role->pivot->department_id) && $targetFacId && $role->pivot->faculty_id == $targetFacId) {
                            return true;
                        }
                        return false;
                    })
                    ->map(function ($r) {
                        $pivotDeptId = $r->pivot->department_id ?? null;
                        $deptShortName = $pivotDeptId
                            ? optional(\App\Models\Department::find($pivotDeptId))->short_name
                            : null;
                        return [
                            'id'              => $r->id,
                            'name'            => $r->name,
                            'short_name'      => $r->short_name,
                            'sort_order'      => (int) $r->sort_order,
                            'department_id'   => $pivotDeptId,
                            'department_short_name' => $deptShortName,
                        ];
                    })->values()->toArray()
                : [],
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
