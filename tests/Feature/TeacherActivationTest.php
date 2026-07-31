<?php

namespace Tests\Feature;

use App\Filament\Resources\Teachers\Support\TeacherEmailComposer;
use App\Jobs\SendCustomTemplatedEmailJob;
use App\Jobs\SendTeacherActivationEmailJob;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmailTemplate;
use App\Models\Teacher;
use App\Models\User;
use App\Services\TeacherActivationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the one route that hands out a session without a password.
 *
 * Every teacher account arrived from the migration with an unusable password, so
 * an emailed link is the only way in — which makes that link worth as much as a
 * password while it is live. These tests pin the properties that keep it safe:
 * it expires, it works once, and it refuses an account that would not be allowed
 * to sign in normally.
 *
 * The base TestCase wraps each test in a transaction and rolls it back, so the
 * records created here never reach the real database.
 */
class TeacherActivationTest extends TestCase
{
    protected TeacherActivationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TeacherActivationService::class);
        Queue::fake();
    }

    /**
     * A teacher who still needs to activate: real user, no password of their
     * own, address unconfirmed.
     */
    protected function makePendingTeacher(array $teacherAttributes = [], array $userAttributes = []): Teacher
    {
        $department = Department::query()->whereHas('faculty')->first();
        $designation = Designation::query()->first();

        $this->assertNotNull($department, 'the database needs at least one department');
        $this->assertNotNull($designation, 'the database needs at least one designation');

        $user = User::forceCreate(array_merge([
            'name' => 'Activation Probe',
            'email' => 'probe-' . Str::random(12) . '@example.test',
            'password' => bcrypt(Str::random(32)),
            'password_set_at' => null,
            'email_verified_at' => null,
            'is_active' => true,
        ], $userAttributes));

        return Teacher::forceCreate(array_merge([
            'user_id' => $user->id,
            'first_name' => 'Activation',
            'last_name' => 'Probe',
            'webpage' => 'activation-probe-' . Str::random(8),
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'is_active' => true,
            'is_archived' => false,
            'login_allowed' => true,
            'verification_status' => 'pending_verification',
        ], $teacherAttributes));
    }

    protected function tokenFor(Teacher $teacher, int $days = 7): string
    {
        $this->service->issueLink($teacher, $days);

        return $teacher->fresh()->verification_token;
    }

    // ---------------------------------------------------------------- link ---

    public function test_a_valid_link_signs_the_teacher_in_and_sends_them_to_set_a_password(): void
    {
        $teacher = $this->makePendingTeacher();
        $token = $this->tokenFor($teacher);

        $this->get(route('teacher.activate', ['token' => $token]))
            ->assertRedirect(route('teacher.password.create'));

        $this->assertAuthenticatedAs($teacher->user->fresh());
    }

    /**
     * Clicking proves the teacher reads that mailbox, which is what
     * email_verified_at records — it has been null on every migrated account.
     */
    public function test_redeeming_marks_the_address_verified(): void
    {
        $teacher = $this->makePendingTeacher();
        $this->assertNull($teacher->user->email_verified_at);

        $this->get(route('teacher.activate', ['token' => $this->tokenFor($teacher)]));

        $this->assertNotNull($teacher->user->fresh()->email_verified_at);
    }

    /**
     * Otherwise the link is a permanent password sitting in an inbox, usable by
     * anyone the message is forwarded to.
     */
    public function test_a_link_cannot_be_used_twice(): void
    {
        $teacher = $this->makePendingTeacher();
        $token = $this->tokenFor($teacher);

        $this->get(route('teacher.activate', ['token' => $token]));
        $this->post(route('filament.admin.auth.logout'));

        $this->get(route('teacher.activate', ['token' => $token]))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }

    public function test_an_expired_link_is_refused(): void
    {
        $teacher = $this->makePendingTeacher();
        $token = $this->tokenFor($teacher);

        $teacher->forceFill(['verification_token_expires_at' => Carbon::now()->subMinute()])->save();

        $this->get(route('teacher.activate', ['token' => $token]))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }

    public function test_an_unknown_token_is_refused(): void
    {
        $this->get(route('teacher.activate', ['token' => str_repeat('z', 64)]))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }

    /**
     * The link must be a way through the access rules, not around them.
     */
    public function test_a_link_will_not_sign_in_an_account_that_cannot_use_the_panel(): void
    {
        $teacher = $this->makePendingTeacher(userAttributes: ['is_active' => false]);
        $token = $this->tokenFor($teacher);

        $this->get(route('teacher.activate', ['token' => $token]))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }

    /**
     * Issuing a new link retires the previous one, so an older email in an inbox
     * stops working the moment a newer one goes out.
     */
    public function test_issuing_a_new_link_invalidates_the_previous_one(): void
    {
        $teacher = $this->makePendingTeacher();
        $first = $this->tokenFor($teacher);
        $second = $this->tokenFor($teacher);

        $this->assertNotSame($first, $second);

        $this->get(route('teacher.activate', ['token' => $first]))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }

    // ------------------------------------------------------------ password ---

    /**
     * Enforced server-side: a prompt alone would let someone navigate away and
     * stay signed in without a password of their own.
     */
    public function test_an_activated_teacher_is_held_on_the_password_page(): void
    {
        $teacher = $this->makePendingTeacher();
        $this->get(route('teacher.activate', ['token' => $this->tokenFor($teacher)]));

        $this->get('/admin')->assertRedirect(route('teacher.password.create'));
    }

    public function test_setting_a_password_releases_them(): void
    {
        $teacher = $this->makePendingTeacher();
        $this->get(route('teacher.activate', ['token' => $this->tokenFor($teacher)]));

        $this->post(route('teacher.password.store'), [
            'password' => 'Str0ng-Probe-Passw0rd',
            'password_confirmation' => 'Str0ng-Probe-Passw0rd',
        ])->assertRedirect(route('filament.admin.pages.dashboard'));

        $user = $teacher->user->fresh();
        $this->assertNotNull($user->password_set_at);

        $this->get('/admin')->assertOk();
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $teacher = $this->makePendingTeacher();
        $this->get(route('teacher.activate', ['token' => $this->tokenFor($teacher)]));

        $this->post(route('teacher.password.store'), [
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])->assertSessionHasErrors('password');

        $this->assertNull($teacher->user->fresh()->password_set_at);
    }

    /**
     * An administrator predates this flow and has a null password_set_at too;
     * the middleware must not lock them out of their own panel.
     */
    public function test_a_non_teacher_is_not_held_on_the_password_page(): void
    {
        $admin = User::role('super_admin')->first();

        if (! $admin) {
            $this->markTestSkipped('no super_admin in the database');
        }

        $admin->forceFill(['password_set_at' => null])->save();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    // -------------------------------------------------------------- sending ---

    public function test_an_ordinary_template_goes_out_on_the_general_path(): void
    {
        $teacher = $this->makePendingTeacher();
        $template = EmailTemplate::where('key', 'profile_verification_request')->firstOrFail();

        TeacherEmailComposer::send([$teacher], [
            'subject' => $template->subject,
            'body' => $template->body,
        ]);

        Queue::assertPushed(SendCustomTemplatedEmailJob::class, 1);
        Queue::assertNotPushed(SendTeacherActivationEmailJob::class);
    }

    /**
     * Keyed on the placeholder rather than the template key, so wording an
     * administrator wrote themselves still produces a working link instead of
     * the literal text {activation_link}.
     */
    public function test_a_message_asking_for_an_activation_link_gets_one(): void
    {
        $teacher = $this->makePendingTeacher();

        TeacherEmailComposer::send([$teacher], [
            'subject' => 'Set up your account',
            'body' => 'Hello {teacher_name}, use {activation_link} within {link_validity_days} days.',
            'link_validity_days' => 12,
        ]);

        Queue::assertNotPushed(SendCustomTemplatedEmailJob::class);
        Queue::assertPushed(SendTeacherActivationEmailJob::class, function ($job) use ($teacher) {
            return $job->teacher->is($teacher)
                && $job->validityDays === 12
                && str_contains($job->activationLink, '/teacher/activate/');
        });

        $this->assertTrue(
            $teacher->fresh()->verification_token_expires_at->between(
                Carbon::now()->addDays(11),
                Carbon::now()->addDays(13),
            ),
        );
    }

    public function test_a_teacher_who_already_finished_onboarding_is_skipped(): void
    {
        $teacher = $this->makePendingTeacher(userAttributes: [
            'password_set_at' => Carbon::now(),
            'email_verified_at' => Carbon::now(),
        ]);

        TeacherEmailComposer::send([$teacher], [
            'subject' => 'Set up your account',
            'body' => 'Use {activation_link}.',
            'link_validity_days' => 7,
        ]);

        Queue::assertNothingPushed();
    }

    public function test_the_pending_list_excludes_teachers_who_cannot_sign_in(): void
    {
        $allowed = $this->makePendingTeacher();
        $blocked = $this->makePendingTeacher(['login_allowed' => false]);
        $archived = $this->makePendingTeacher(['is_archived' => true]);

        $pending = $this->service->pendingQuery()->pluck('id');

        $this->assertTrue($pending->contains($allowed->id));
        $this->assertFalse($pending->contains($blocked->id));
        $this->assertFalse($pending->contains($archived->id));
    }
}
