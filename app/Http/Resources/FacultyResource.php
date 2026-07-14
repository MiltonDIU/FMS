<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacultyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'code' => $this->code,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'description' => $this->description ?? $this->getDefaultDescription($this->code),
            'image' => $this->getDefaultImage($this->code),
            'departments_count' => $this->departments()->where('is_active', true)->count(),
            'teachers_count' => $this->teachers()->where('teachers.is_active', true)->where('teachers.is_archived', false)->count(),
            'dean' => $this->getDean(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function getDean(): ?array
    {
        $dean = \App\Models\Teacher::whereHas('user.administrativeRoles', function ($q) {
            $q->where('administrative_role_user.faculty_id', $this->id)
              ->where('administrative_role_user.is_active', true)
              ->where(function ($sub) {
                  $sub->whereNull('administrative_role_user.end_date')
                     ->orWhere('administrative_role_user.end_date', '>=', now()->toDateString());
              })
              ->where('administrative_roles.name', 'like', '%Dean%');
        })->first();

        if (!$dean) {
            return null;
        }

        $photo = $dean->photo;
        if (empty($photo) && $dean->employee_id) {
            $picture = \Illuminate\Support\Facades\Cache::remember('teacher_photo_' . $dean->employee_id, 3600, function () use ($dean) {
                try {
                    $oldTeacher = \Illuminate\Support\Facades\DB::connection('old_db')
                        ->table('teacher')
                        ->where('employeeID', $dean->employee_id)
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

        $photoUrl = null;
        if ($photo) {
            $photoUrl = (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://'))
                ? $photo
                : 'https://faculty.daffodilvarsity.edu.bd/images/teacher/' . basename($photo);
        }

        return [
            'id' => $dean->id,
            'name' => trim($dean->first_name . ($dean->middle_name ? ' ' . $dean->middle_name : '') . ($dean->last_name ? ' ' . $dean->last_name : '')),
            'email' => $dean->email,
            'avatar' => $photoUrl,
        ];
    }

    private function getDefaultImage(string $code): string
    {
        $images = [
            'fbe' => "https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&q=80&w=400",
            'fsit' => "https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&q=80&w=400",
            'fe' => "https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&q=80&w=400",
            'fhls' => "https://images.unsplash.com/photo-1506126613408-eca07ce68773?auto=format&fit=crop&q=80&w=400",
            'fhss' => "https://images.unsplash.com/photo-1513258496099-48168024aec0?auto=format&fit=crop&q=80&w=400",
        ];

        return $images[strtolower($code)] ?? "https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&q=80&w=400";
    }

    private function getDefaultDescription(string $code): string
    {
        $descriptions = [
            'fbe' => "Developing industry leaders and next-generation innovators with a strong foundation in business administration and strategic entrepreneurship.",
            'fsit' => "Leading faculty at DIU driving cutting-edge computer science, software engineering, and mathematical computing programs in Bangladesh.",
            'fe' => "Nurturing future engineers in telecommunication, textile science, civil, and electrical engineering with robust laboratory-focused education.",
            'fhls' => "Fostering excellence in health education, pharmaceutical research, public health, and nutrition sciences to meet global healthcare challenges.",
            'fhss' => "Cultivating critical thinking, professional journalism, and strong English communication skills to prepare graduates for a globalized world.",
        ];

        return $descriptions[strtolower($code)] ?? "Fostering excellence and academic achievement across a range of multidisciplinary programs.";
    }
}
