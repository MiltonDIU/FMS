<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\User;
use App\Support\AccessAudit;
use App\Support\TeacherRelationWriter;
use Tests\TestCase;

/**
 * The records people edit — departments, teachers and their sections,
 * publications, settings, roles — leave an entry in the activity log.
 *
 * The suite runs in the console, where model logging is off by default (the
 * imports there would bury everything else), so most tests switch it on.
 */
class ModelActivityLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['activitylog.log_console_model_events' => true]);
    }

    protected function admin(): User
    {
        $user = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        if (! $user) {
            $this->markTestSkipped('no super_admin');
        }

        return $user;
    }

    protected function newEntries(int $after)
    {
        return Activity::where('id', '>', $after)->get();
    }

    public function test_an_edit_records_who_changed_what(): void
    {
        $department = Department::query()->first() ?? $this->markTestSkipped('no department');
        $admin = $this->admin();
        $this->actingAs($admin);
        $last = (int) Activity::max('id');

        $department->update(['name' => $department->name . ' (probe)']);

        $entry = $this->newEntries($last)->firstWhere('log_name', 'Department');

        $this->assertNotNull($entry);
        $this->assertSame('updated', $entry->event);
        $this->assertSame($admin->id, $entry->causer_id);
        $this->assertSame($department->name, $entry->attribute_changes['attributes']['name']);
        $this->assertArrayNotHasKey('updated_at', $entry->attribute_changes['attributes']);
        $this->assertStringContainsString('(probe)', $entry->description);
    }

    public function test_nothing_is_recorded_from_the_console_by_default(): void
    {
        config(['activitylog.log_console_model_events' => false]);

        $department = Department::query()->first() ?? $this->markTestSkipped('no department');
        $last = (int) Activity::max('id');

        $department->update(['name' => $department->name . ' (probe)']);

        $this->assertCount(0, $this->newEntries($last));
    }

    public function test_a_view_counter_is_not_an_edit(): void
    {
        $teacher = Teacher::query()->first() ?? $this->markTestSkipped('no teacher');
        $last = (int) Activity::max('id');

        $teacher->increment('views_count');

        $this->assertCount(0, $this->newEntries($last));
    }

    public function test_a_credential_setting_is_recorded_without_its_value(): void
    {
        $this->actingAs($this->admin());
        $last = (int) Activity::max('id');

        Setting::set('hr_api_password', 'a-password-that-must-not-appear-' . uniqid());

        $entries = $this->newEntries($last);

        $this->assertCount(1, $entries);
        $this->assertStringContainsString('hr_api_password', $entries->first()->description);
        $this->assertStringNotContainsString('a-password-that-must-not-appear', json_encode($entries->first()->attribute_changes));
    }

    public function test_a_progress_counter_setting_is_not_recorded(): void
    {
        $last = (int) Activity::max('id');

        Setting::set('export_progress', 'probe ' . uniqid());

        $this->assertCount(0, $this->newEntries($last));
    }

    /** syncPermissions() revokes all and grants back; only the difference is recorded. */
    public function test_a_role_records_only_the_permissions_that_moved(): void
    {
        $role = Role::create(['name' => 'probe_role_' . uniqid(), 'guard_name' => 'web']);
        $permissions = Permission::query()->where('guard_name', 'web')->limit(3)->get();

        if ($permissions->count() < 3) {
            $this->markTestSkipped('not enough permissions');
        }

        $role->syncPermissions($permissions->take(2));
        $last = (int) Activity::max('id');

        $role->syncPermissions([$permissions[0], $permissions[2]]);

        $entries = $this->newEntries($last)->where('log_name', AccessAudit::LOG_NAME);

        $this->assertCount(1, $entries);
        $this->assertSame([$permissions[2]->name], $entries->first()->properties['added']);
        $this->assertSame([$permissions[1]->name], $entries->first()->properties['removed']);
    }

    public function test_giving_a_user_a_role_is_recorded(): void
    {
        $user = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'))->first()
            ?? $this->markTestSkipped('no user');
        $role = Role::create(['name' => 'probe_role_' . uniqid(), 'guard_name' => 'web']);
        $last = (int) Activity::max('id');

        $user->assignRole($role);

        $entry = $this->newEntries($last)->firstWhere('event', 'roles_changed');

        $this->assertNotNull($entry);
        $this->assertSame([$role->name], $entry->properties['added']);
    }

    /** Removed one by one, so a deleted section row is recorded like an edit. */
    public function test_removing_a_profile_row_is_recorded(): void
    {
        $teacher = Teacher::query()->whereHas('skills', null, '>=', 1)->first()
            ?? $this->markTestSkipped('no teacher with skills');
        $this->actingAs($this->admin());
        $removed = $teacher->skills()->first();
        $last = (int) Activity::max('id');

        TeacherRelationWriter::save($teacher, 'skills', [], [$removed->id]);

        $entry = $this->newEntries($last)->first(fn ($a) => $a->event === 'deleted' && $a->subject_id === $removed->id);

        $this->assertNotNull($entry, 'the removed skill was recorded');
        $this->assertSame('Skill', $entry->log_name);
    }

    /** config/permission.php now names App\Models\Role; Shield's screens must still open. */
    public function test_the_role_screens_open_with_the_recorded_role_model(): void
    {
        $role = Role::query()->first() ?? $this->markTestSkipped('no role');
        $this->actingAs($this->admin());

        $this->assertInstanceOf(Role::class, $this->admin()->roles()->first());

        $this->get(\BezhanSalleh\FilamentShield\Resources\Roles\RoleResource::getUrl('index'))->assertOk();
        $this->get(\BezhanSalleh\FilamentShield\Resources\Roles\RoleResource::getUrl('edit', ['record' => $role]))->assertOk();
    }

    public function test_the_log_page_shows_a_model_change(): void
    {
        $department = Department::query()->first() ?? $this->markTestSkipped('no department');
        $admin = $this->admin();
        $this->actingAs($admin);

        $department->update(['name' => $department->name . ' (probe)']);
        $entry = Activity::where('log_name', 'Department')->latest('id')->first();

        $this->get('/admin/activity-log')->assertOk();
        $this->get('/admin/activity-log/' . $entry->id)->assertOk()->assertSee('(probe)');
    }
}
