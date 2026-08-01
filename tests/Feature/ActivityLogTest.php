<?php

namespace Tests\Feature;

use App\Listeners\LogAuthenticationActivity;
use App\Models\Activity;
use App\Models\User;
use App\Policies\ActivityPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The activity log: what it records about accounts, and about signing in.
 *
 * Three things here are easy to get wrong and quiet when they are.
 *
 * The log is written to be read, so a credential in it is a credential handed
 * out — the password hash, the remember token and a submitted password all have
 * to stay out.
 *
 * Signing in changes no row, so the package's model trait cannot see it at all;
 * the auth events are wired separately, and because Laravel discovers listeners
 * in app/Listeners by the event they type-hint, registering them a second time
 * by hand records everything twice.
 *
 * And the client address is told apart from a console run by the raw
 * REMOTE_ADDR superglobal rather than by runningInConsole(), which reports true
 * for the test suite as well as for artisan.
 */
class ActivityLogTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);

        parent::tearDown();
    }

    protected function anyUser(): User
    {
        $user = User::query()->first();

        if (! $user) {
            $this->markTestSkipped('no user in the database');
        }

        return $user;
    }

    /** Pretend the entry is being written for a web request from this address. */
    protected function fromAddress(string $ip, string $agent = 'TestAgent/1.0'): void
    {
        $_SERVER['REMOTE_ADDR'] = $ip;

        $this->app->instance('request', Request::create('/admin/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $agent,
        ]));
    }

    public function test_a_user_edit_is_recorded_without_the_password(): void
    {
        $user = $this->anyUser();
        $before = Activity::count();

        $this->actingAs($user);
        $user->name = 'Probe ' . uniqid();
        $user->password = bcrypt('a-secret-that-must-not-appear');
        $user->remember_token = 'a-token-that-must-not-appear';
        $user->save();

        $this->assertSame($before + 1, Activity::count());

        $activity = Activity::latest('id')->first();
        $recorded = json_encode([$activity->properties, $activity->attribute_changes]);

        $this->assertStringNotContainsString('a-secret-that-must-not-appear', $recorded);
        $this->assertStringNotContainsString('a-token-that-must-not-appear', $recorded);
        $this->assertStringNotContainsString('"password"', $recorded);
        $this->assertStringNotContainsString('"remember_token"', $recorded);
    }

    public function test_an_edit_that_changed_nothing_is_not_recorded(): void
    {
        $user = $this->anyUser();
        $this->actingAs($user);

        $before = Activity::count();
        $user->save();

        $this->assertSame($before, Activity::count());
    }

    public function test_signing_in_and_out_is_recorded(): void
    {
        $user = $this->anyUser();
        $before = Activity::count();

        auth()->login($user);
        auth()->logout();

        $this->assertSame($before + 2, Activity::count());

        $events = Activity::latest('id')->limit(2)->pluck('event')->all();
        $this->assertEqualsCanonicalizing(['login', 'logout'], $events);

        $login = Activity::where('event', 'login')->latest('id')->first();
        $this->assertSame(LogAuthenticationActivity::LOG_NAME, $login->log_name);
        $this->assertSame($user->id, $login->causer_id);
    }

    /**
     * Each auth event must produce exactly one row. Laravel discovers this
     * listener automatically, so any second registration doubles everything.
     */
    public function test_each_sign_in_is_recorded_once(): void
    {
        $user = $this->anyUser();
        $before = Activity::count();

        auth()->login($user);

        $this->assertSame($before + 1, Activity::count());
    }

    public function test_a_failed_attempt_is_recorded_without_the_submitted_password(): void
    {
        $before = Activity::count();

        event(new Failed('web', null, [
            'email' => 'ghost@example.test',
            'password' => 'submitted-password-must-not-appear',
        ]));

        $this->assertSame($before + 1, Activity::count());

        $activity = Activity::latest('id')->first();

        $this->assertSame('failed', $activity->event);
        $this->assertSame('ghost@example.test', $activity->properties['attempted']);
        $this->assertFalse($activity->properties['account_exists']);
        $this->assertStringNotContainsString(
            'submitted-password-must-not-appear',
            json_encode($activity->properties),
        );
    }

    public function test_a_lockout_is_recorded(): void
    {
        $before = Activity::count();

        event(new Lockout(Request::create('/admin/login', 'POST', ['email' => 'attacker@example.test'])));

        $this->assertSame($before + 1, Activity::count());
        $this->assertSame('locked', Activity::latest('id')->first()->event);
    }

    public function test_a_web_entry_carries_the_client_address(): void
    {
        $user = $this->anyUser();
        $this->fromAddress('203.0.113.44', 'Mozilla/5.0 TestAgent/1.0');

        auth()->login($user);

        $properties = Activity::latest('id')->first()->properties;

        $this->assertSame('203.0.113.44', $properties[Activity::IP_KEY]);
        $this->assertStringContainsString('TestAgent/1.0', $properties[Activity::AGENT_KEY]);
    }

    /**
     * Without a client there is no address to record, and 127.0.0.1 — which is
     * what request()->ip() hands back on the command line — would be a lie.
     */
    public function test_a_console_entry_records_the_command_instead_of_an_address(): void
    {
        unset($_SERVER['REMOTE_ADDR']);

        $user = $this->anyUser();
        auth()->login($user);

        $properties = Activity::latest('id')->first()->properties;

        // No client means no address — the key is left out rather than stored as
        // null, so "entries with an address" is simply a check that it is there.
        $this->assertArrayNotHasKey(Activity::IP_KEY, $properties->toArray());
        $this->assertStringStartsWith('console', $properties[Activity::AGENT_KEY]);
    }

    public function test_context_does_not_overwrite_properties_a_caller_set(): void
    {
        $this->fromAddress('203.0.113.44');

        activity('probe')
            ->withProperties([Activity::IP_KEY => '198.51.100.7', 'note' => 'kept'])
            ->log('probe');

        $properties = Activity::latest('id')->first()->properties;

        $this->assertSame('198.51.100.7', $properties[Activity::IP_KEY]);
        $this->assertSame('kept', $properties['note']);
    }

    public function test_the_log_is_readable_only_with_the_permission(): void
    {
        $holder = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        if (! $holder) {
            $this->markTestSkipped('no super_admin');
        }

        $this->assertTrue($holder->can(ActivityPolicy::VIEW_ANY));
        $this->actingAs($holder)->get('/admin/activity-log')->assertStatus(200);

        $others = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->whereHas('roles')
            ->get()
            ->unique(fn (User $u) => $u->roles->pluck('name')->sort()->implode(','));

        foreach ($others as $user) {
            $this->assertFalse(
                $user->can(ActivityPolicy::VIEW_ANY),
                $user->roles->pluck('name')->implode(',') . ' can read the audit trail by default',
            );
        }
    }

    /**
     * The point of a permission rather than a named role: access can be handed
     * to somebody else from the role editor, without a deployment.
     */
    public function test_another_role_can_be_granted_access(): void
    {
        $role = Role::where('name', 'registrar')->first();
        $user = User::whereHas('roles', fn ($q) => $q->where('name', 'registrar'))->first();

        if (! $role || ! $user) {
            $this->markTestSkipped('no registrar role or user');
        }

        $this->assertFalse($user->can(ActivityPolicy::VIEW_ANY));

        $role->givePermissionTo(ActivityPolicy::VIEW_ANY, ActivityPolicy::VIEW);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($user->fresh()->can(ActivityPolicy::VIEW_ANY));
    }

    /** An entry records something that happened; nobody may rewrite it. */
    public function test_nobody_may_write_to_the_log(): void
    {
        $holder = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();
        $activity = Activity::latest('id')->first();

        if (! $holder || ! $activity) {
            $this->markTestSkipped('no super_admin or no activity recorded');
        }

        $this->assertFalse($holder->can('create', Activity::class));

        foreach (['update', 'delete', 'restore', 'forceDelete'] as $ability) {
            $this->assertFalse(
                $holder->can($ability, $activity),
                "super_admin may {$ability} an audit entry",
            );
        }
    }
}
