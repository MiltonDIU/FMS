<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in account, as the app needs to know it.
 *
 * Deliberately thin. It answers "who am I" and "which profile is mine", which
 * is all the app does with it — the profile itself comes from the teacher
 * endpoints, and anything else here would be a second place to keep in step.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Not whenLoaded(): that returns a MissingValue when the relation was
        // never loaded, and a MissingValue is truthy — the branch below would
        // then read properties off it. The relation is either there or it is not.
        $teacher = $this->resource->relationLoaded('teacher') ? $this->resource->teacher : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'teacher' => $teacher
                ? [
                    'id' => $teacher->id,
                    'full_name' => $teacher->full_name,
                    'designation' => optional($teacher->designation)->name,
                    'department' => optional($teacher->department)->name,
                    // Where the app should send them for their own public page.
                    'profile_path' => $this->profilePath($teacher),
                ]
                : null,
        ];
    }

    protected function profilePath($teacher): ?string
    {
        $faculty = optional(optional($teacher->department)->faculty)->short_name;
        $department = optional($teacher->department)->code;

        if (! $faculty || ! $department || ! $teacher->webpage) {
            return null;
        }

        // The segments exactly as the routes and the website spell them — not
        // lowercased. MySQL would match either way, so a mismatch here would
        // only show up on a database that collates case-sensitively.
        return $faculty . '/' . $department . '/' . $teacher->webpage;
    }
}
