<?php

namespace App\Http\Resources\V1;

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
            // The URL segment, spelled as the routes expect it. Named separately
            // from short_name so an app builds paths from one field and never
            // has to guess whether the case matters.
            'slug' => $this->short_name,
            'description' => $this->description,
            // Present only when the query asked for them, so a list endpoint
            // does not quietly run a count per row.
            'departments_count' => $this->whenCounted('departments'),
            'teachers_count' => $this->whenCounted('teachers'),
            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
        ];
    }
}
