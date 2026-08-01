<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            // The URL segment, spelled as the routes expect it.
            'slug' => $this->code,
            'description' => $this->description,
            'teachers_count' => $this->whenCounted('teachers'),
            'faculty' => new FacultyResource($this->whenLoaded('faculty')),
        ];
    }
}
