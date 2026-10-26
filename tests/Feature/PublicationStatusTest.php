<?php

namespace Tests\Feature;

use App\Filament\Resources\Publications\Pages\CreatePublication;
use App\Filament\Resources\Publications\Pages\EditPublication;
use App\Models\ApprovalSetting;
use App\Models\Publication;
use App\Models\PublicationLinkage;
use App\Models\PublicationType;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A publication's status is not a teacher's to set, and only approved papers
 * reach the public site.
 *
 * A teacher creating a paper in the panel could pick "Approved" themselves and
 * name any account on the system as its creator. And the status was read
 * nowhere public, so a draft or pending paper was on the website regardless.
 */
class PublicationStatusTest extends TestCase
{
    /** A teacher holding the teacher role and nothing else, with a published profile. */
    protected function teacherOnly(): User
    {
        $teacher = Teacher::query()->published()
            ->whereNotNull('webpage')
            ->whereHas('user', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'teacher'))
                ->whereDoesntHave('roles', fn ($r) => $r->where('name', '!=', 'teacher')))
            ->whereHas('department', fn ($q) => $q->where('is_active', true)
                ->whereHas('faculty', fn ($f) => $f->where('is_active', true)))
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('no teacher-only account');
        }

        $teacher->user->givePermissionTo(['ViewAny:Publication', 'View:Publication', 'Create:Publication', 'Update:Publication']);

        return $teacher->user;
    }

    protected function admin(): User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first()
            ?? $this->markTestSkipped('no super_admin');
    }

    protected function paperData(string $title): array
    {
        return [
            'publication_type_id' => PublicationType::query()->value('id'),
            'publication_linkage_id' => PublicationLinkage::query()->value('id'),
            'title' => $title,
        ];
    }

    public function test_a_teacher_does_not_see_the_status(): void
    {
        $this->actingAs($this->teacherOnly());

        Livewire::test(CreatePublication::class)->assertFormFieldHidden('status');
    }

    public function test_an_admin_still_sees_the_status(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreatePublication::class)->assertFormFieldVisible('status');
    }

    /** Filled from the session, so nobody picks it — not a teacher, not an admin. */
    public function test_nobody_gets_a_created_by_field(): void
    {
        foreach ([$this->teacherOnly(), $this->admin()] as $user) {
            $this->actingAs($user);

            Livewire::test(CreatePublication::class)->assertFormFieldDoesNotExist('created_by');
        }
    }

    public function test_an_admins_paper_is_theirs_too(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $title = 'Creator probe ' . uniqid();

        Livewire::test(CreatePublication::class)
            ->fillForm($this->paperData($title) + ['status' => 'approved'])
            ->set('data.created_by', $this->teacherOnly()->id)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($admin->id, Publication::where('title', $title)->value('created_by'));
    }

    /**
     * Not every user is a teacher. One with no teacher profile — super_admin,
     * admin, research_team — is recorded as the creator of a lookup and of a
     * paper alike, and still sees the status.
     */
    public function test_a_user_without_a_teacher_profile(): void
    {
        $staff = User::whereDoesntHave('teacher')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin', 'research_team']))
            ->first() ?? $this->markTestSkipped('no staff account without a teacher profile');
        $this->actingAs($staff);

        $this->assertFalse(\App\Filament\Resources\Publications\Schemas\PublicationForm::isTeacherOnly());

        foreach ([\App\Models\Major::class, \App\Models\Position::class, \App\Models\Organization::class] as $model) {
            $record = $model::create(['name' => 'Creator probe ' . uniqid()]);
            $this->assertSame($staff->id, $record->created_by, class_basename($model) . ' records the admin');
            $this->assertSame($staff->name, $record->creator?->name);
        }

        $title = 'Staff probe ' . uniqid();

        Livewire::test(CreatePublication::class)
            ->assertFormFieldVisible('status')
            ->fillForm($this->paperData($title) + ['status' => 'draft'])
            ->call('create')
            ->assertHasNoFormErrors();

        $paper = Publication::where('title', $title)->firstOrFail();
        $this->assertSame($staff->id, $paper->created_by);
        $this->assertSame('draft', $paper->status, 'their chosen status is kept');
    }

    public function test_the_lookup_screens_still_open_without_the_field(): void
    {
        $this->actingAs($this->admin());

        foreach (['majors', 'positions', 'organizations'] as $path) {
            $this->get('/admin/' . $path)->assertOk();
        }
    }

    /** Major, Position and Organization record the user who added them, as a paper does. */
    public function test_lookup_records_take_the_signed_in_user(): void
    {
        $user = $this->teacherOnly();
        $this->actingAs($user);

        foreach ([\App\Models\Major::class, \App\Models\Position::class, \App\Models\Organization::class] as $model) {
            $record = $model::create(['name' => 'Creator probe ' . uniqid(), 'is_active' => false]);

            $this->assertSame($user->id, $record->created_by, class_basename($model));
            $this->assertInstanceOf(User::class, $record->creator);
        }

        $this->actingAs($this->admin());
        $explicit = \App\Models\Major::create(['name' => 'Creator probe ' . uniqid(), 'created_by' => $user->id]);
        $this->assertSame($user->id, $explicit->created_by, 'a value set in code is kept');
    }

    /**
     * A name a teacher adds waits for approval, and is approved when somebody
     * else asks for it too. Comparing users now, it must still tell the two
     * apart — and an import with nobody signed in records the teacher's account.
     */
    public function test_auto_approval_still_tells_the_adder_from_anybody_else(): void
    {
        $first = $this->teacherOnly();
        $second = User::whereHas('teacher')->whereKeyNot($first->id)
            ->whereHas('roles', fn ($r) => $r->where('name', 'teacher'))
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', '!=', 'teacher'))
            ->first() ?? $this->markTestSkipped('needs two teacher-only accounts');

        foreach ([\App\Models\Major::class, \App\Models\Position::class] as $model) {
            $name = 'Approval probe ' . uniqid();

            $this->actingAs($first);
            $added = $model::findOrCreateWithAutoApproval($name, $first->teacher->id);
            $this->assertFalse($added->is_active, class_basename($model) . ' from a teacher waits');
            $this->assertSame($first->id, $added->created_by);

            $model::findOrCreateWithAutoApproval($name, $first->teacher->id);
            $this->assertFalse($added->fresh()->is_active, 'asking again yourself approves nothing');

            $this->actingAs($second);
            $model::findOrCreateWithAutoApproval($name, $second->teacher->id);
            $this->assertTrue($added->fresh()->is_active, 'a second teacher asking approves it');
        }

        auth()->logout();
        $imported = \App\Models\Major::findOrCreateWithAutoApproval('Import probe ' . uniqid(), $first->teacher->id);
        $this->assertSame($first->id, $imported->created_by, 'an import records the teacher\'s account');
    }

    /** After the migration the key points at users, on whatever database it ran. */
    public function test_the_lookup_keys_point_at_users(): void
    {
        foreach (['majors', 'positions', 'organizations'] as $table) {
            $references = \Illuminate\Support\Facades\DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', \Illuminate\Support\Facades\DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', 'created_by')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->pluck('REFERENCED_TABLE_NAME')
                ->all();

            $this->assertSame(['users'], $references, $table);
        }
    }

    public function test_a_teachers_paper_follows_the_approval_setting_and_is_theirs(): void
    {
        $user = $this->teacherOnly();
        $this->actingAs($user);

        foreach ([false => Publication::STATUS_APPROVED, true => 'pending'] as $requires => $expected) {
            ApprovalSetting::where('section_key', 'publications')->update(['requires_approval' => $requires, 'is_active' => true]);
            $title = 'Status probe ' . uniqid();

            Livewire::test(CreatePublication::class)
                ->fillForm($this->paperData($title))
                // A value sent without the form is not taken either.
                ->set('data.status', 'approved')
                ->set('data.created_by', $this->admin()->id)
                ->call('create')
                ->assertHasNoFormErrors();

            $paper = Publication::where('title', $title)->firstOrFail();

            $this->assertSame($expected, $paper->status, 'approval ' . ($requires ? 'on' : 'off'));
            $this->assertSame($user->id, $paper->created_by);
        }
    }

    public function test_a_teachers_save_keeps_the_status(): void
    {
        $user = $this->teacherOnly();
        $paper = $user->teacher->publications()->first() ?? $this->markTestSkipped('teacher has no paper');
        $paper->forceFill(['status' => 'pending'])->saveQuietly();
        $this->actingAs($user);

        Livewire::test(EditPublication::class, ['record' => $paper->getRouteKey()])
            ->set('data.status', 'approved')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('pending', $paper->fresh()->status);
    }

    public function test_only_approved_papers_reach_the_public_profile(): void
    {
        $user = $this->teacherOnly();
        $teacher = $user->teacher;
        $paper = $teacher->publications()->first() ?? $this->markTestSkipped('teacher has no paper');
        $profile = \App\Helpers\Seo::publicTeacherUrl($teacher) ?? $this->markTestSkipped('profile not reachable');
        $paperUrl = route('publication.show', [
            'faculty_short_name' => strtolower($teacher->department->faculty->short_name),
            'department_code' => strtolower($teacher->department->code),
            'teacher_webpage' => $teacher->webpage,
            'publication_slug' => \App\Helpers\Seo::publicationSlug($paper),
        ]);

        $this->get(parse_url($profile, PHP_URL_PATH))->assertOk()->assertSee($paper->title);
        $this->get(parse_url($paperUrl, PHP_URL_PATH))->assertOk();

        $paper->forceFill(['status' => 'pending'])->saveQuietly();

        $this->get(parse_url($profile, PHP_URL_PATH))->assertOk()->assertDontSee($paper->title);
        $this->get(parse_url($paperUrl, PHP_URL_PATH))->assertNotFound();
        $this->assertFalse(Publication::approved()->whereKey($paper->id)->exists());
    }
}
