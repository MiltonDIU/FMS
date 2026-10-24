<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\TeacherServicePeriod;
use App\Models\TeacherVersion;
use App\Models\User;
use App\Support\TeacherServicePeriods;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * HR reporting that a teacher has left.
 *
 * The endpoint takes people off the public site, so the first thing it must
 * get right is who may call it: a token issued for it, and nothing else — in
 * particular not the token every teacher's phone holds. The second is that a
 * departure is the same change an administrator makes on the Settings tab:
 * off the site, sign-in closed, the period of service ended, and the change
 * kept in the version history.
 */
class HrDepartureApiTest extends TestCase
{
    protected const EMPLOYEE_ID = 'HR-TEST-0001';

    protected Teacher $teacher;

    protected User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $teacher = Teacher::published()->whereNotNull('user_id')->first();

        if (! $teacher) {
            $this->markTestSkipped('No published teacher with an account.');
        }

        // An id no other profile carries, a known joining date (few published
        // profiles hold one), and one open period of service.
        $teacher->forceFill([
            'employee_id' => self::EMPLOYEE_ID,
            'joining_date' => '2015-01-10',
            'leaving_date' => null,
        ])->saveQuietly();
        TeacherServicePeriod::where('teacher_id', $teacher->id)->delete();
        TeacherServicePeriods::sync($teacher->fresh());

        $this->teacher = $teacher->fresh();

        $this->hr = User::create([
            'name' => 'HR Integration',
            'email' => 'hr-integration@example.invalid',
            'password' => Hash::make('not-used'),
            'is_active' => true,
        ]);
    }

    // ── Who may call it ─────────────────────────────────────────────────

    public function test_it_is_closed_to_a_caller_with_no_token(): void
    {
        $this->postJson('/api/v1/hr/departures', $this->departure())->assertUnauthorized();
    }

    public function test_a_teachers_app_token_is_refused(): void
    {
        /*
         * The token the app's sign-in issues lists no abilities, which Sanctum
         * stores as "*" and its own ability check reads as "everything".
         */
        $phone = $this->teacher->user->createToken('mobile')->plainTextToken;

        $this->withToken($phone)
            ->postJson('/api/v1/hr/departures', $this->departure())
            ->assertForbidden();

        $this->assertTrue((bool) $this->teacher->fresh()->is_public);
    }

    public function test_a_token_issued_for_something_else_is_refused(): void
    {
        $other = $this->hr->createToken('other', ['reports:read'])->plainTextToken;

        $this->withToken($other)
            ->postJson('/api/v1/hr/departures', $this->departure())
            ->assertForbidden();
    }

    // ── What a departure does ───────────────────────────────────────────

    public function test_a_departure_takes_the_profile_down_and_ends_the_period_of_service(): void
    {
        $phone = $this->teacher->user->createToken('mobile');

        $response = $this->asHr()->postJson('/api/v1/hr/departures', $this->departure());

        $response->assertOk()
            ->assertJsonPath('status', 'departed')
            ->assertJsonPath('teacher.employment_status', 'Resigned')
            ->assertJsonPath('teacher.leaving_date', '2026-06-30')
            ->assertJsonPath('teacher.is_public', false)
            ->assertJsonPath('service_period.ended', true)
            ->assertJsonPath('service_period.left_on', '2026-06-30');

        $teacher = $this->teacher->fresh();
        $this->assertFalse((bool) $teacher->is_public);
        $this->assertFalse((bool) $teacher->is_active);
        $this->assertFalse((bool) $teacher->login_allowed);
        $this->assertSame('resigned', $teacher->employmentStatus->slug);
        $this->assertFalse(Teacher::published()->whereKey($teacher->id)->exists());

        $period = TeacherServicePeriod::where('teacher_id', $teacher->id)->sole();
        $this->assertTrue($period->ended);
        $this->assertSame('2026-06-30', $period->left_on->toDateString());
        $this->assertSame('Resigned', $period->end_reason);

        // Kept in the history, under the HR account's name, not waiting for approval.
        $version = TeacherVersion::where('teacher_id', $teacher->id)->latest('id')->first();
        $this->assertSame('applied_directly', $version->status);
        $this->assertSame($this->hr->id, (int) $version->submitted_by);

        // The phone that was signed in is signed out.
        $this->assertNull($phone->accessToken->fresh());
    }

    public function test_sending_the_same_departure_twice_changes_nothing_the_second_time(): void
    {
        $this->asHr()->postJson('/api/v1/hr/departures', $this->departure())->assertOk();
        $versions = TeacherVersion::where('teacher_id', $this->teacher->id)->count();

        $this->asHr()->postJson('/api/v1/hr/departures', $this->departure())
            ->assertOk()
            ->assertJsonPath('status', 'unchanged');

        $this->assertSame($versions, TeacherVersion::where('teacher_id', $this->teacher->id)->count());
        $this->assertSame(1, TeacherServicePeriod::where('teacher_id', $this->teacher->id)->count());
    }

    public function test_a_retirement_also_archives_the_profile(): void
    {
        $this->asHr()->postJson('/api/v1/hr/departures', $this->departure(['reason' => 'retired']))->assertOk();

        $this->assertTrue((bool) $this->teacher->fresh()->is_archived);
    }

    // ── What it refuses ─────────────────────────────────────────────────

    public function test_an_unknown_employee_id_is_a_404(): void
    {
        $this->asHr()
            ->postJson('/api/v1/hr/departures', $this->departure(['employee_id' => 'NOBODY-HAS-THIS']))
            ->assertNotFound();
    }

    public function test_two_profiles_under_one_employee_id_are_not_guessed_between(): void
    {
        Teacher::whereKeyNot($this->teacher->id)->first()
            ->forceFill(['employee_id' => self::EMPLOYEE_ID])->saveQuietly();

        $this->asHr()->postJson('/api/v1/hr/departures', $this->departure())->assertStatus(409);

        $this->assertTrue((bool) $this->teacher->fresh()->is_public);
    }

    public function test_a_leaving_date_before_the_teacher_joined_is_refused(): void
    {
        $before = $this->teacher->joining_date->copy()->subDay()->toDateString();

        $this->asHr()
            ->postJson('/api/v1/hr/departures', $this->departure(['leaving_date' => $before]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('leaving_date');

        $this->assertTrue((bool) $this->teacher->fresh()->is_public);
    }

    public function test_a_leaving_date_still_to_come_is_refused(): void
    {
        $this->asHr()
            ->postJson('/api/v1/hr/departures', $this->departure(['leaving_date' => now()->addWeek()->toDateString()]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('leaving_date');
    }

    public function test_only_the_statuses_that_end_an_appointment_are_accepted(): void
    {
        foreach (['archived', 'on-leave', 'active'] as $reason) {
            $this->asHr()
                ->postJson('/api/v1/hr/departures', $this->departure(['reason' => $reason]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('reason');
        }
    }

    // ── Issuing the token ───────────────────────────────────────────────

    public function test_the_command_issues_a_token_for_an_hr_account_and_refuses_a_teachers(): void
    {
        $this->artisan('teachers:issue-hr-token', ['email' => $this->teacher->user->email])->assertFailed();

        $this->artisan('teachers:issue-hr-token', ['email' => $this->hr->email])->assertSuccessful();
        $this->assertSame(['hr:departures'], $this->hr->tokens()->sole()->abilities);

        $this->artisan('teachers:issue-hr-token', ['email' => $this->hr->email, '--revoke' => true])->assertSuccessful();
        $this->assertSame(0, $this->hr->tokens()->count());
    }

    // ─────────────────────────────────────────────────────────────────────

    protected function asHr(): static
    {
        return $this->withToken($this->hr->createToken('hr-departures', ['hr:departures'])->plainTextToken);
    }

    /** @param  array<string, string>  $overrides */
    protected function departure(array $overrides = []): array
    {
        return $overrides + [
            'employee_id' => self::EMPLOYEE_ID,
            'reason' => 'resigned',
            'leaving_date' => '2026-06-30',
        ];
    }
}
