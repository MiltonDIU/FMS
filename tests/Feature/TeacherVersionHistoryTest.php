<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\Teacher;
use App\Models\TeacherVersion;
use App\Models\User;
use App\Services\TeacherVersionService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The record of what changed on a profile, and going back to it.
 *
 * An administrator's edit used to change the profile and leave nothing: no
 * one could say what a field had been or who changed it. Every direct change
 * is now filed with both states; a restore point holds the whole profile; and
 * a rollback is itself recorded and never takes papers off a profile.
 */
class TeacherVersionHistoryTest extends TestCase
{
    protected Teacher $teacher;

    protected TeacherVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->service = app(TeacherVersionService::class);
        $this->teacher = Teacher::whereNotNull('department_id')->whereHas('publications')->firstOrFail();
    }

    public function test_a_direct_change_is_recorded_with_what_it_replaced(): void
    {
        $before = $this->teacher->bio;

        $this->assertTrue($this->service->handleUpdateFromForm($this->teacher, ['bio' => 'A new biography.'], skipApproval: true));

        $version = TeacherVersion::where('teacher_id', $this->teacher->id)->latest('id')->first();

        $this->assertSame('A new biography.', $this->teacher->fresh()->bio);
        $this->assertSame('applied_directly', $version->status);
        $this->assertTrue($version->is_active, 'the live change is the active version');
        $this->assertSame(['basic_info'], $version->changed_sections);
        $this->assertSame('A new biography.', $version->data['bio']);
        $this->assertSame($before, $version->beforeStateFor('basic_info')['bio'] ?? null);

        // It held only the section it changed, so it is not a restore point.
        $this->assertFalse($version->isRestorable());
    }

    public function test_a_save_with_nothing_changed_records_nothing(): void
    {
        $count = TeacherVersion::where('teacher_id', $this->teacher->id)->count();

        $this->assertFalse($this->service->handleUpdateFromForm($this->teacher, ['bio' => $this->teacher->bio], skipApproval: true));
        $this->assertSame($count, TeacherVersion::where('teacher_id', $this->teacher->id)->count());
    }

    public function test_a_full_snapshot_is_a_restore_point(): void
    {
        $snapshot = $this->service->recordFullSnapshot($this->teacher, $this->wholeProfile());

        $this->assertTrue($snapshot->isFullSnapshot());
        $this->assertTrue($snapshot->isRestorable());
        $this->assertSame('Full snapshot', $snapshot->change_summary);
    }

    public function test_rolling_back_restores_the_profile_but_keeps_later_papers(): void
    {
        $original = $this->teacher->bio;
        $snapshot = $this->service->recordFullSnapshot($this->teacher, $this->wholeProfile());

        // After the restore point: the biography is edited, and a paper added.
        $this->service->handleUpdateFromForm($this->teacher->fresh(), ['bio' => 'Changed after the snapshot.'], skipApproval: true);

        $paper = Publication::whereDoesntHave('teachers', fn ($query) => $query->whereKey($this->teacher->id))->firstOrFail();
        $this->teacher->publications()->attach($paper->id, ['author_role' => 'co_author', 'sort_order' => 99]);
        $papers = $this->teacher->publications()->count();

        $this->service->activateVersion($snapshot->fresh());

        $teacher = $this->teacher->fresh();
        $this->assertSame($original, $teacher->bio);
        $this->assertSame($papers, $teacher->publications()->count(), 'a rollback took papers off the profile');
        $this->assertTrue($snapshot->fresh()->is_active);

        // The rollback is a change like any other, and is on the record.
        $record = TeacherVersion::where('teacher_id', $this->teacher->id)->latest('id')->first();
        $this->assertSame("Rollback to version {$snapshot->version_number}", $record->change_summary);
        $this->assertContains('basic_info', $record->changed_sections);
        $this->assertNotContains('publications', $record->changed_sections);
        $this->assertSame('Changed after the snapshot.', $record->beforeStateFor('basic_info')['bio'] ?? null);
    }

    public function test_a_version_holding_only_its_changes_cannot_be_rolled_back_to(): void
    {
        $this->service->handleUpdateFromForm($this->teacher, ['bio' => 'Partial.'], skipApproval: true);
        $partial = TeacherVersion::where('teacher_id', $this->teacher->id)->latest('id')->first();

        $this->expectException(\Exception::class);

        $this->service->activateVersion($partial);
    }

    public function test_a_teacher_sees_their_own_history_and_nobody_elses(): void
    {
        $user = User::create([
            'name' => 'History Test',
            'email' => 'history-test-' . uniqid() . '@fms.test',
            'password' => bcrypt('secret-password'),
        ]);
        $this->teacher->forceFill(['user_id' => $user->id])->saveQuietly();

        $own = TeacherVersion::create(['teacher_id' => $this->teacher->id, 'version_number' => 9001, 'data' => [], 'status' => 'applied_directly']);
        $other = TeacherVersion::create([
            'teacher_id' => Teacher::whereKeyNot($this->teacher->id)->value('id'),
            'version_number' => 9001, 'data' => [], 'status' => 'applied_directly',
        ]);

        $user = $user->fresh();

        $this->assertTrue($user->can('viewAny', TeacherVersion::class));
        $this->assertTrue($user->can('view', $own));
        $this->assertFalse($user->can('view', $other));
        $this->assertFalse($user->can('update', $own), 'the history is read-only');
    }

    /** The profile as the edit form holds it: every section. */
    protected function wholeProfile(): array
    {
        $teacher = $this->teacher->fresh();
        $data = $teacher->only(collect(TeacherVersionService::FIELD_SECTION_MAP)
            ->flatten()
            ->reject(fn (string $field): bool => in_array($field, TeacherVersionService::RELATION_NAMES, true)
                || in_array($field, TeacherVersionService::PIVOT_RELATIONS, true))
            ->all());

        foreach (TeacherVersionService::RELATION_NAMES as $relation) {
            $data[$relation] = $relation === 'publications'
                ? $teacher->publications()->pluck('publications.id')->all()
                : $teacher->$relation()->get()->map(fn ($row) => $row->getAttributes())->all();
        }

        $data['academicSuffixes'] = $teacher->academicSuffixes()->pluck('academic_suffixes.id')->all();

        return $data;
    }
}
