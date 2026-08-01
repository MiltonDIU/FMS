<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Teacher;

/**
 * Turning /{faculty}/{department}/{teacher} into models.
 *
 * The API uses the same address the website does, so an app can hand a link it
 * was given straight to either. Resolving each segment against its parent —
 * rather than looking the teacher up by slug alone — means a teacher cannot be
 * reached under a department they do not belong to.
 */
trait ResolvesDirectoryPath
{
    /** Accepts the short name or the numeric id. */
    protected function resolveFaculty(string $faculty): Faculty
    {
        return Faculty::where('is_active', true)
            ->where(fn ($q) => $q->where('short_name', $faculty)->orWhere('id', $faculty))
            ->firstOrFail();
    }

    protected function resolveDepartment(Faculty $faculty, string $department): Department
    {
        return $faculty->departments()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('code', $department)->orWhere('id', $department))
            ->firstOrFail();
    }

    /**
     * The teacher at this address, or a 404.
     *
     * published() is what keeps the 872 archived and inactive records out of
     * every API answer. It is applied here rather than left to each caller, so
     * a new endpoint cannot forget it.
     */
    protected function resolveTeacher(string $faculty, string $department, string $webpage): Teacher
    {
        $departmentModel = $this->resolveDepartment($this->resolveFaculty($faculty), $department);

        return Teacher::published()
            ->where(fn ($q) => $q
                ->where('teachers.department_id', $departmentModel->id)
                // A teacher can also be attached through the pivot; the website
                // counts them as a member of that department and so does this.
                ->orWhereIn('teachers.id', fn ($sub) => $sub
                    ->select('teacher_id')
                    ->from('department_teacher')
                    ->whereNull('deleted_at')
                    ->where('department_id', $departmentModel->id)))
            ->where('teachers.webpage', $webpage)
            ->firstOrFail();
    }
}
