<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesDirectoryPath;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DepartmentResource;
use App\Http\Resources\V1\FacultyResource;
use App\Http\Resources\V1\TeacherResource;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Browsing the directory: faculties, departments, and the people in them.
 *
 * Open without a token — this is the same information the public website
 * serves, and an app that has to sign in before it can show a directory is a
 * worse app. Every teacher query goes through Teacher::published() so the 872
 * archived or inactive records cannot reach it.
 */
class DirectoryController extends Controller
{
    use ResolvesDirectoryPath;

    /** Enough for a phone screen, capped so a caller cannot ask for everything. */
    public const PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    public function faculties(): AnonymousResourceCollection
    {
        $faculties = Faculty::where('is_active', true)
            ->withCount([
                'departments' => fn ($q) => $q->where('is_active', true),
                'teachers' => fn ($q) => $q->published(),
            ])
            ->orderBy('sort_order')
            ->get();

        return FacultyResource::collection($faculties);
    }

    public function faculty(string $faculty): FacultyResource
    {
        return new FacultyResource($this->resolveFaculty($faculty)->loadCount([
            'departments' => fn ($q) => $q->where('is_active', true),
            'teachers' => fn ($q) => $q->published(),
        ]));
    }

    public function departments(string $faculty): AnonymousResourceCollection
    {
        $departments = $this->resolveFaculty($faculty)
            ->departments()
            ->where('is_active', true)
            ->withCount(['teachers' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get();

        return DepartmentResource::collection($departments);
    }

    /**
     * Every department in the university, flat.
     *
     * The website has no such page — you reach a department through its faculty.
     * An app needs the flat list anyway, to fill a filter without first making
     * one request per faculty.
     */
    public function allDepartments(): AnonymousResourceCollection
    {
        $departments = Department::where('is_active', true)
            ->whereHas('faculty', fn ($q) => $q->where('is_active', true))
            ->with('faculty')
            ->withCount(['teachers' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get();

        return DepartmentResource::collection($departments);
    }

    public function department(string $faculty, string $department): DepartmentResource
    {
        $facultyModel = $this->resolveFaculty($faculty);

        return new DepartmentResource(
            $this->resolveDepartment($facultyModel, $department)
                ->loadCount(['teachers' => fn ($q) => $q->published()])
                ->load('faculty')
        );
    }

    /**
     * The people in one department.
     *
     * Mirrors the website's list: administrative office holders are not sorted
     * to the top here, because an app can group them itself and a stable order
     * is easier to page through.
     */
    public function departmentTeachers(Request $request, string $faculty, string $department): AnonymousResourceCollection
    {
        $facultyModel = $this->resolveFaculty($faculty);
        $departmentModel = $this->resolveDepartment($facultyModel, $department);

        $teachers = $this->teacherQuery($request)
            ->where(fn ($q) => $q
                ->where('teachers.department_id', $departmentModel->id)
                // A teacher can be attached to a second department through the
                // pivot; the website counts them as members and so does this.
                ->orWhereIn('teachers.id', fn ($sub) => $sub
                    ->select('teacher_id')
                    ->from('department_teacher')
                    ->whereNull('deleted_at')
                    ->where('department_id', $departmentModel->id)))
            ->paginate($this->perPage($request))
            ->withQueryString();

        return TeacherResource::collection($teachers);
    }

    /** Search across every faculty — the website only searches within one. */
    public function teachers(Request $request): AnonymousResourceCollection
    {
        $teachers = $this->teacherQuery($request)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return TeacherResource::collection($teachers);
    }

    /**
     * The list query, with the filters both listing endpoints accept.
     *
     * ?q= name, employee id or email · ?designation= · ?department= · ?faculty=
     */
    protected function teacherQuery(Request $request)
    {
        $query = Teacher::published()
            ->with(['designation', 'department.faculty', 'employmentStatus', 'user', 'media'])
            ->withCount('publications');

        if (filled($search = $request->query('q'))) {
            $like = '%' . $search . '%';

            // Not full_name: it is a column and an accessor of the same name,
            // and the column is empty on all 2,000 rows — the accessor builds
            // the name from the parts on read. Matching against it compiles and
            // runs and finds nothing.
            $query->where(fn ($q) => $q
                ->where('teachers.first_name', 'like', $like)
                ->orWhere('teachers.middle_name', 'like', $like)
                ->orWhere('teachers.last_name', 'like', $like)
                ->orWhere('teachers.employee_id', 'like', $like)
                ->orWhere('teachers.secondary_email', 'like', $like));
        }

        if (filled($designation = $request->query('designation'))) {
            $query->where('teachers.designation_id', $designation);
        }

        if (filled($faculty = $request->query('faculty'))) {
            $query->whereHas('department.faculty', fn ($q) => $q
                ->where('faculties.id', $faculty)
                ->orWhere('faculties.short_name', $faculty));
        }

        if (filled($department = $request->query('department'))) {
            $query->whereHas('department', fn ($q) => $q
                ->where('departments.id', $department)
                ->orWhere('departments.code', $department));
        }

        return $query
            ->orderBy('teachers.sort_order')
            ->orderBy('teachers.first_name');
    }

    protected function perPage(Request $request): int
    {
        return min(
            max((int) $request->query('per_page', self::PER_PAGE), 1),
            self::MAX_PER_PAGE,
        );
    }
}
