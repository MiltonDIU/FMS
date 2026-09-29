<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NotificationRouting;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Who a "department head" routing reaches.
 *
 * Holding approve:own-department-teacher is not enough: every head holds it,
 * so on the permission alone the head of one department was sent — and could
 * approve — changes to another department's teachers. The head must also
 * hold a current administrative role over the teacher's own department, and
 * nobody approves a change to their own profile.
 */
class DepartmentHeadRoutingTest extends TestCase
{
    protected const PERMISSION = 'approve:own-department-teacher';

    protected Teacher $teacher;

    protected int $roleId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::table('permissions')->where('name', self::PERMISSION)->exists()) {
            $this->markTestSkipped('the approval permission is not seeded');
        }

        $this->teacher = Teacher::whereNotNull('department_id')->firstOrFail();
        $this->roleId = DB::table('administrative_roles')->insertGetId([
            'name' => 'Test Head', 'scope' => 'department', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        NotificationRouting::create([
            'trigger_type' => 'teacher_profile_update',
            'trigger_sections' => null,
            'recipient_type' => 'department_head',
            'recipient_identifiers' => [],
            'is_active' => true,
        ]);
    }

    public function test_the_head_of_the_teachers_own_department_is_reached(): void
    {
        $head = $this->aHead($this->teacher->department_id);

        $this->assertTrue($this->recipients()->contains('id', $head->id));
    }

    public function test_the_head_of_another_department_is_not(): void
    {
        $other = Department::whereKeyNot($this->teacher->department_id)->firstOrFail();
        $head = $this->aHead($other->id);

        $this->assertFalse($this->recipients()->contains('id', $head->id));
    }

    public function test_a_head_whose_appointment_has_ended_is_not(): void
    {
        $head = $this->aHead($this->teacher->department_id, ['end_date' => now()->subDay()->toDateString()]);
        $inactive = $this->aHead($this->teacher->department_id, ['is_active' => false]);

        $recipients = $this->recipients();

        $this->assertFalse($recipients->contains('id', $head->id));
        $this->assertFalse($recipients->contains('id', $inactive->id));
    }

    public function test_the_role_without_the_permission_is_not_enough(): void
    {
        $head = $this->aHead($this->teacher->department_id, [], withPermission: false);

        $this->assertFalse($this->recipients()->contains('id', $head->id));
    }

    public function test_a_head_is_not_sent_their_own_profile(): void
    {
        $head = $this->aHead($this->teacher->department_id);
        $this->teacher->forceFill(['user_id' => $head->id])->saveQuietly();

        $this->assertFalse($this->recipients()->contains('id', $head->id));
    }

    protected function recipients()
    {
        return NotificationRouting::getRecipientsFor('teacher_profile_update', 'basic_info', $this->teacher->fresh());
    }

    protected function aHead(int $departmentId, array $appointment = [], bool $withPermission = true): User
    {
        $user = User::create([
            'name' => 'Routing Test ' . uniqid(),
            'email' => 'routing-test-' . uniqid() . '@fms.test',
            'password' => bcrypt('secret-password'),
        ]);

        if ($withPermission) {
            $user->givePermissionTo(self::PERMISSION);
        }

        DB::table('administrative_role_user')->insert(array_merge([
            'user_id' => $user->id,
            'administrative_role_id' => $this->roleId,
            'department_id' => $departmentId,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $appointment));

        return $user;
    }
}
