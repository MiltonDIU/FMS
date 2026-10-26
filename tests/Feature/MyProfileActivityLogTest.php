<?php

namespace Tests\Feature;

use App\Filament\Pages\MyProfile;
use App\Models\Activity;
use App\Models\ApprovalSetting;
use App\Models\Teacher;
use App\Models\TeacherVersion;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A teacher's own actions on their profile leave a record: each save, and the
 * one-time confirmation that the profile is correct.
 *
 * The confirmation used to leave nothing but verified_at — markAsVerified()
 * bypasses the versioning — so there was no saying who confirmed or from
 * where. What changed is not copied into the log; teacher_versions holds it
 * and the entry points there.
 */
class MyProfileActivityLogTest extends TestCase
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

    public function test_a_save_is_recorded_with_the_version_it_kept(): void
    {
        ApprovalSetting::query()->update(['requires_approval' => false]);

        $user = $this->teacherUser();
        $this->actingAs($user);
        $lastId = (int) Activity::max('id');

        Livewire::test(MyProfile::class)
            ->set('saveDeclarationAccepted', true)
            ->set('data.save_full_snapshot', true)
            ->call('save');

        $entry = Activity::where('id', '>', $lastId)->where('log_name', MyProfile::LOG_NAME)->first();

        $this->assertNotNull($entry, 'the save was logged');
        $this->assertSame('updated', $entry->event);
        $this->assertSame($user->id, $entry->causer_id);
        $this->assertSame($user->teacher->id, $entry->subject_id);

        $version = TeacherVersion::find($entry->properties[Activity::VERSION_KEY]);
        $this->assertNotNull($version, 'the entry points at the version the save kept');
        $this->assertSame($user->teacher->id, $version->teacher_id);
    }

    public function test_the_entry_links_to_the_version_for_someone_who_may_open_it(): void
    {
        $version = TeacherVersion::query()->first();
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        if (! $version || ! $admin) {
            $this->markTestSkipped('no version or no super_admin');
        }

        $entry = new Activity(['properties' => [Activity::VERSION_KEY => $version->id]]);

        $this->actingAs($admin);
        $this->assertStringEndsWith('/teacher-versions/' . $version->id, $entry->versionUrl());

        $this->assertNull((new Activity(['properties' => []]))->versionUrl());
    }

    public function test_a_save_that_changed_nothing_is_not_recorded(): void
    {
        ApprovalSetting::query()->update(['requires_approval' => false]);

        $this->actingAs($this->teacherUser());
        $lastId = (int) Activity::max('id');

        Livewire::test(MyProfile::class)
            ->set('saveDeclarationAccepted', true)
            ->call('save');

        $this->assertFalse(
            Activity::where('id', '>', $lastId)->where('log_name', MyProfile::LOG_NAME)->exists(),
        );
    }

    public function test_the_confirmation_is_recorded(): void
    {
        $user = $this->teacherUser();
        $user->teacher->forceFill(['verification_status' => 'unverified', 'verified_at' => null])->saveQuietly();
        $this->actingAs($user);
        $lastId = (int) Activity::max('id');

        Livewire::test(MyProfile::class)
            ->set('declarationAccepted', true)
            ->call('confirmVerification');

        $entry = Activity::where('id', '>', $lastId)
            ->where('log_name', MyProfile::LOG_NAME)
            ->where('event', 'declared')
            ->first();

        $this->assertNotNull($entry, 'the confirmation was logged');
        $this->assertSame($user->id, $entry->causer_id);
        $this->assertSame($user->teacher->id, $entry->subject_id);
    }

    public function test_an_unticked_confirmation_is_not_recorded(): void
    {
        $user = $this->teacherUser();
        $user->teacher->forceFill(['verification_status' => 'unverified', 'verified_at' => null])->saveQuietly();
        $this->actingAs($user);
        $lastId = (int) Activity::max('id');

        Livewire::test(MyProfile::class)->call('confirmVerification');

        $this->assertFalse(Activity::where('id', '>', $lastId)->where('event', 'declared')->exists());
    }
}
