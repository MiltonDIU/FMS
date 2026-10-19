<?php

namespace Tests\Feature;

use App\Filament\Pages\MyProfile;
use App\Models\ApprovalSetting;
use App\Models\Teacher;
use App\Models\TeacherVersion;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A teacher can keep their own profile as a restore point.
 *
 * The box was on the administrator's Edit Teacher page only, on the grounds
 * that a teacher's submissions are approved versions and so restore points
 * already. With approval switched off every teacher save applies directly and
 * records only the sections it changed, so after the first save a teacher's
 * profile gained no restore points at all.
 */
class MyProfileSnapshotTest extends TestCase
{
    protected function teacherUser(): User
    {
        $teacher = Teacher::query()->whereHas('user')->first();

        if (! $teacher) {
            $this->markTestSkipped('No teacher with an account in this database.');
        }

        $teacher->user->givePermissionTo('View:MyProfile');

        return $teacher->user;
    }

    public function test_a_teacher_can_keep_a_full_snapshot_while_nothing_needs_approval(): void
    {
        ApprovalSetting::query()->update(['requires_approval' => false]);

        $user = $this->teacherUser();
        $this->actingAs($user);

        $lastId = (int) TeacherVersion::where('teacher_id', $user->teacher->id)->max('id');

        Livewire::test(MyProfile::class)
            ->assertSee('Save a full snapshot of this profile')
            ->set('saveDeclarationAccepted', true)
            ->set('data.save_full_snapshot', true)
            ->call('save');

        $version = TeacherVersion::where('teacher_id', $user->teacher->id)
            ->where('id', '>', $lastId)
            ->latest('id')
            ->first();

        $this->assertNotNull($version, 'the save kept a version');
        $missing = array_values(array_diff(\App\Services\TeacherVersionService::snapshotRelations(), array_keys($version->data ?? [])));
        $this->assertSame([], $missing, 'every section the form holds is in the snapshot');
        $this->assertTrue($version->isRestorable(), 'the version can be rolled back to');
    }

    /**
     * Certifications come from the ERP comparison and have no place on the
     * profile form, so no snapshot taken from the form holds them. Requiring
     * them made every snapshot since they were added impossible to roll back
     * to, the administrator's included.
     */
    public function test_a_snapshot_without_certifications_is_still_a_restore_point(): void
    {
        $data = array_fill_keys(\App\Services\TeacherVersionService::snapshotRelations(), []);

        $version = new TeacherVersion(['status' => 'applied_directly', 'data' => $data]);

        $this->assertArrayNotHasKey('certifications', $data);
        $this->assertTrue($version->isRestorable());
    }

    public function test_the_box_is_not_offered_while_a_section_needs_approval(): void
    {
        ApprovalSetting::query()->update(['requires_approval' => false]);
        ApprovalSetting::query()->where('section_key', 'basic_info')->update(['requires_approval' => true, 'is_active' => true]);

        $this->actingAs($this->teacherUser());

        Livewire::test(MyProfile::class)
            ->assertDontSee('Save a full snapshot of this profile');
    }
}
