<?php

namespace Tests\Feature;

use App\Filament\Resources\Publications\PublicationResource;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\Publication;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Being a teacher narrows the lists only for someone who is nothing else.
 *
 * The Registrar holds a teacher profile, as most staff with another role do.
 * Given the registrar role, he saw one teacher in the Teachers list, himself,
 * and only his own papers, because the lists asked whether he was a teacher
 * before asking what else he was.
 */
class TeacherWithAnotherRoleTest extends TestCase
{
    /** A user with a teacher profile and no administrative post, which would scope them anyway. */
    protected function teacherWithoutAPost(): User
    {
        $teacher = Teacher::query()
            ->whereHas('user', fn ($q) => $q->whereNotIn('id', DB::table('administrative_role_user')
                ->select('user_id')
                ->where('is_active', true)
                ->whereNull('end_date')
                ->whereNull('deleted_at')))
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('No teacher without an administrative post in this database.');
        }

        $teacher->user->syncRoles(['teacher']);

        return $teacher->user->fresh();
    }

    public function test_a_teacher_who_is_also_registrar_sees_every_teacher_and_paper(): void
    {
        $user = $this->teacherWithoutAPost();
        $user->assignRole('registrar');
        $this->actingAs($user->fresh());

        $this->assertSame(Teacher::count(), TeacherResource::getEloquentQuery()->count());
        $this->assertSame(Publication::count(), PublicationResource::getEloquentQuery()->count());
    }

    public function test_someone_who_is_only_a_teacher_still_sees_only_their_own_profile(): void
    {
        $user = $this->teacherWithoutAPost();
        $this->actingAs($user);

        $this->assertTrue($user->holdsOnlyTeacherRole());
        $this->assertSame([$user->teacher->id], TeacherResource::getEloquentQuery()->pluck('id')->all());
    }
}
