<?php

namespace Tests\Feature;

use App\Models\AdministrativeRole;
use App\Models\Department;
use App\Models\DepartmentTeacher;
use App\Models\Teacher;
use App\Models\UserAdministrativeRole;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Appointing a Dean, Associate Dean, Head or Associate Head puts them at
 * serial 1, 2, 3 and 4 of the department listing — the Dean and Associate
 * Dean in every department of the faculty — adding them to a department only
 * when they are not in it already.
 */
class DepartmentAdminPlacementTest extends TestCase
{
    protected Department $department;

    /** @var array<int, Teacher> */
    protected array $teachers;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Dean', 'Associate Dean', 'Head of Department', 'Associate Head'] as $name) {
            if (! AdministrativeRole::where('name', $name)->exists()) {
                $this->markTestSkipped("the \"{$name}\" administrative role is not seeded");
            }
        }

        $this->department = Department::where('is_active', true)
            ->whereIn('faculty_id', Department::where('is_active', true)
                ->groupBy('faculty_id')->havingRaw('COUNT(*) > 1')->pluck('faculty_id'))
            ->has('teachers', '>=', 2)
            ->first()
            ?? $this->markTestSkipped('no faculty with two active departments');

        $this->teachers = Teacher::whereNotNull('user_id')->limit(4)->get()->all();

        if (count($this->teachers) < 4) {
            $this->markTestSkipped('fewer than four teachers with accounts');
        }

        // Start from a faculty with nobody in office, so the real holders in
        // the copied data do not take the places being tested.
        $facultyDepartments = Department::where('faculty_id', $this->department->faculty_id)->pluck('id');
        UserAdministrativeRole::query()
            ->where(fn ($q) => $q->where('faculty_id', $this->department->faculty_id)
                ->orWhereIn('department_id', $facultyDepartments))
            ->update(['end_date' => now()->subDay()->toDateString()]);
    }

    public function test_a_dean_leads_every_department_of_the_faculty(): void
    {
        $dean = $this->teachers[0];

        $this->appoint($dean, 'Dean', facultyWide: true);

        $departments = Department::where('faculty_id', $this->department->faculty_id)->where('is_active', true)->pluck('id');

        foreach ($departments as $departmentId) {
            $this->assertSame(1, $this->serialOf($dean, $departmentId), "department {$departmentId}");
            $this->assertSerialsAreUnique($departmentId);
        }
    }

    public function test_the_four_offices_take_serials_one_to_four(): void
    {
        [$dean, $associateDean, $head, $associateHead] = $this->teachers;

        // Appointed out of order on purpose: the places do not depend on it.
        $this->appoint($associateHead, 'Associate Head');
        $this->appoint($head, 'Head of Department');
        $this->appoint($associateDean, 'Associate Dean', facultyWide: true);
        $this->appoint($dean, 'Dean', facultyWide: true);

        $this->assertSame(1, $this->serialOf($dean));
        $this->assertSame(2, $this->serialOf($associateDean));
        $this->assertSame(3, $this->serialOf($head));
        $this->assertSame(4, $this->serialOf($associateHead));
        $this->assertSerialsAreUnique($this->department->id);
    }

    public function test_a_teacher_already_in_the_department_only_moves(): void
    {
        $member = DepartmentTeacher::where('department_id', $this->department->id)
            ->orderByDesc('sort_order')
            ->with('teacher')
            ->get()
            ->first(fn ($row) => $row->teacher?->user_id);

        if (! $member) {
            $this->markTestSkipped('no department member with an account');
        }

        $before = DepartmentTeacher::where('department_id', $this->department->id)->count();

        $this->appoint($member->teacher, 'Head of Department');

        $this->assertSame($before, DepartmentTeacher::where('department_id', $this->department->id)->count());
        $this->assertSame(1, DepartmentTeacher::where('department_id', $this->department->id)
            ->where('teacher_id', $member->teacher_id)->count());
        $this->assertSame(1, $this->serialOf($member->teacher)); // no Dean in office, so the Head is first
        $this->assertSerialsAreUnique($this->department->id);
    }

    public function test_editing_the_remarks_leaves_a_manual_order_alone(): void
    {
        $head = $this->teachers[0];
        $assignment = $this->appoint($head, 'Head of Department');

        $row = DepartmentTeacher::where('department_id', $this->department->id)->where('teacher_id', $head->id)->first();
        $row->update(['sort_order' => 99]);

        $assignment->update(['remarks' => 'moved by hand']);

        $this->assertSame(99, $row->fresh()->sort_order);
    }

    public function test_ending_a_faculty_wide_appointment_takes_them_out_of_every_department_but_home(): void
    {
        $associateDean = $this->teachers[0];
        $assignment = $this->appoint($associateDean, 'Associate Dean', facultyWide: true);

        $faculty = $this->facultyDepartmentIds();
        $this->assertNotEmpty($this->rowsOf($associateDean, $faculty));

        $assignment->update(['is_active' => false]);

        $left = $this->rowsOf($associateDean, $faculty);
        $this->assertSame(
            in_array((int) $associateDean->department_id, $faculty, true) ? [(int) $associateDean->department_id] : [],
            $left,
            'only the home department may keep them'
        );

        foreach ($faculty as $departmentId) {
            $this->assertSerialsAreUnique($departmentId);
        }
    }

    public function test_deleting_the_appointment_does_the_same(): void
    {
        $dean = $this->teachers[0];
        $assignment = $this->appoint($dean, 'Dean', facultyWide: true);

        $assignment->delete();

        $left = $this->rowsOf($dean, $this->facultyDepartmentIds());
        $this->assertSame(array_values(array_intersect($left, [(int) $dean->department_id])), $left);
    }

    public function test_an_end_date_counts_as_ended(): void
    {
        $head = $this->teachers[0];
        $assignment = $this->appoint($head, 'Head of Department');

        $assignment->update(['end_date' => now()->toDateString()]);

        $expected = (int) $head->department_id === $this->department->id ? 1 : 0;
        $this->assertSame($expected, DepartmentTeacher::where('department_id', $this->department->id)
            ->where('teacher_id', $head->id)->count());
    }

    public function test_a_department_another_appointment_still_covers_is_kept(): void
    {
        $teacher = $this->teachers[0];
        $this->appoint($teacher, 'Head of Department');
        $associateDean = $this->appoint($teacher, 'Associate Dean', facultyWide: true);

        $associateDean->update(['is_active' => false]);

        $this->assertNotNull($this->serialOf($teacher), 'still Head of this department');
        $this->assertSame(1, $this->serialOf($teacher));
    }

    public function test_the_next_holder_moves_up_when_one_leaves(): void
    {
        [$dean, $associateDean] = $this->teachers;
        $deanAssignment = $this->appoint($dean, 'Dean', facultyWide: true);
        $this->appoint($associateDean, 'Associate Dean', facultyWide: true);

        $deanAssignment->update(['is_active' => false]);

        $this->assertSame(1, $this->serialOf($associateDean));
    }

    public function test_the_department_page_follows_the_admin_serial(): void
    {
        // Reverse whatever order the department has, so the rank-based order
        // the site used before cannot pass this by coincidence.
        $rows = DepartmentTeacher::where('department_id', $this->department->id)->orderBy('sort_order')->orderBy('id')->get();
        $count = $rows->count();
        foreach ($rows->values() as $i => $row) {
            DepartmentTeacher::whereKey($row->id)->update(['sort_order' => $count - $i]);
        }

        $page = new \App\Livewire\Frontend\DepartmentSearch;
        $page->mount($this->department->id);

        $serials = DepartmentTeacher::where('department_id', $this->department->id)->pluck('sort_order', 'teacher_id');

        foreach ([$page->getAdminTeachersProperty(), collect($page->getTeachersProperty()->items())] as $listed) {
            $shown = $listed->pluck('id')->filter(fn ($id) => $serials->has($id))->map(fn ($id) => $serials[$id])->values()->all();
            $sorted = $shown;
            sort($sorted);

            $this->assertSame($sorted, $shown);
        }
    }

    public function test_teachers_sharing_a_serial_appear_in_the_admin_lists_order(): void
    {
        // Everyone on one serial: only the tie-break decides, and it must be
        // the admin list's — the row id — not the rank order.
        DepartmentTeacher::where('department_id', $this->department->id)->update(['sort_order' => 1]);

        $adminOrder = DepartmentTeacher::where('department_id', $this->department->id)
            ->orderBy('sort_order')->orderBy('id')->pluck('teacher_id')->unique()->values();

        $page = new \App\Livewire\Frontend\DepartmentSearch;
        $page->mount($this->department->id);

        foreach ([$page->getAdminTeachersProperty()->pluck('id'), collect($page->getTeachersProperty()->items())->pluck('id')] as $listed) {
            $this->assertSame(
                $adminOrder->intersect($listed)->values()->all(),
                $listed->filter(fn ($id) => $adminOrder->contains($id))->values()->all()
            );
        }
    }

    public function test_the_faculty_list_keeps_each_departments_serial(): void
    {
        // Reverse the department's order so rank order cannot pass by chance.
        $rows = DepartmentTeacher::where('department_id', $this->department->id)->orderBy('sort_order')->orderBy('id')->get();
        foreach ($rows->values() as $i => $row) {
            DepartmentTeacher::whereKey($row->id)->update(['sort_order' => $rows->count() - $i]);
        }

        $search = new \App\Livewire\Frontend\TeacherSearch;
        $search->mount((string) $this->department->faculty_id);

        $listed = collect();
        for ($page = 1; ; $page++) {
            $search->setPage($page);
            $paginator = $search->getTeachersProperty();
            $listed = $listed->concat($paginator->items());
            if ($page >= $paginator->lastPage()) {
                break;
            }
        }

        // Each department appears as one run, not scattered through the list.
        $runs = $listed->pluck('department_id')->chunkWhile(fn ($v, $k, $c) => $v === $c->last())->map->first();
        $this->assertSame($runs->unique()->values()->all(), $runs->values()->all());

        // And inside its run, in the department's own serial.
        foreach ($listed->groupBy('department_id') as $departmentId => $teachers) {
            $serial = DepartmentTeacher::where('department_id', $departmentId)
                ->orderBy('sort_order')->orderBy('id')->pluck('teacher_id')->unique()->values();
            $ids = $teachers->pluck('id');

            $this->assertSame(
                $serial->intersect($ids)->values()->all(),
                $ids->filter(fn ($id) => $serial->contains($id))->values()->all(),
                "department {$departmentId}"
            );
        }
    }

    /** @return array<int, int> */
    protected function facultyDepartmentIds(): array
    {
        return Department::where('faculty_id', $this->department->faculty_id)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  array<int, int>  $departmentIds
     * @return array<int, int>
     */
    protected function rowsOf(Teacher $teacher, array $departmentIds): array
    {
        return DepartmentTeacher::where('teacher_id', $teacher->id)
            ->whereIn('department_id', $departmentIds)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected function appoint(Teacher $teacher, string $role, bool $facultyWide = false): UserAdministrativeRole
    {
        return UserAdministrativeRole::create([
            'user_id' => $teacher->user_id,
            'administrative_role_id' => AdministrativeRole::where('name', $role)->value('id'),
            'faculty_id' => $facultyWide ? $this->department->faculty_id : null,
            'department_id' => $facultyWide ? null : $this->department->id,
            'start_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    protected function serialOf(Teacher $teacher, ?int $departmentId = null): ?int
    {
        return DepartmentTeacher::where('department_id', $departmentId ?? $this->department->id)
            ->where('teacher_id', $teacher->id)
            ->value('sort_order');
    }

    protected function assertSerialsAreUnique(int $departmentId): void
    {
        $serials = DepartmentTeacher::where('department_id', $departmentId)->pluck('sort_order');

        $this->assertSame($serials->count(), $serials->unique()->count(), "duplicate serials in department {$departmentId}");
    }
}
