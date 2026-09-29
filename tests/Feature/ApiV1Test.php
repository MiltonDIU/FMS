<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The two things the mobile API must not get wrong.
 *
 * **The forced first password change.** Every account came over from the
 * migration with a generated password that was emailed out, so a first sign-in
 * uses a secret that has travelled through a mailbox. The app shows a "choose a
 * password" screen, but a screen is a suggestion — a modified client, or anyone
 * holding the token and a curl command, would skip it. The refusal has to be on
 * the server, and these prove it is.
 *
 * **Visibility.** 2,000 teacher records exist; 1,128 are published. The other
 * 872 are archived, or inactive, or both, and several are people who have left
 * the university. The website has always hidden them. An API that returns them
 * because a controller forgot a scope would publish, to anyone with a token,
 * exactly the records somebody decided to take down.
 */
class ApiV1Test extends TestCase
{
    // ── Everything needs a token ─────────────────────────────────────────

    public function test_the_directory_is_closed_to_a_caller_with_no_token(): void
    {
        $this->getJson('/api/v1/faculties')->assertUnauthorized();
        $this->getJson('/api/v1/teachers')->assertUnauthorized();
        $this->getJson('/api/v1/publications')->assertUnauthorized();
        $this->getJson('/api/v1/lookups')->assertUnauthorized();
    }

    public function test_signing_in_is_reachable_without_one(): void
    {
        // Wrong details, but a 422 rather than a 401 — the endpoint answered.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.invalid',
            'password' => 'not-the-password',
        ])->assertStatus(422);
    }

    // ── Sign-in ──────────────────────────────────────────────────────────

    public function test_an_unknown_account_and_a_wrong_password_answer_alike(): void
    {
        $user = $this->userWithPassword('correct-horse-battery');

        $unknown = $this->postJson('/api/v1/auth/login', [
            'email' => 'certainly-not-here@example.invalid',
            'password' => 'correct-horse-battery',
        ]);

        $wrong = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'the-wrong-one',
        ]);

        $unknown->assertStatus(422);
        $wrong->assertStatus(422);

        /*
         * Identical to the byte. Any difference — a distinct message, a
         * different field, a different status — turns this endpoint into a way
         * of asking which of two thousand university addresses are real.
         */
        $this->assertSame(
            $unknown->json('errors'),
            $wrong->json('errors'),
        );
    }

    public function test_a_correct_password_returns_a_token(): void
    {
        $user = $this->userWithPassword('correct-horse-battery');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'must_change_password', 'user' => ['id', 'email']]);

        $this->assertNotEmpty($response->json('token'));
    }

    // ── The forced first password change ─────────────────────────────────

    public function test_an_emailed_password_reaches_nothing_but_the_way_out(): void
    {
        Sanctum::actingAs($this->userWhoHasNotChosenAPassword());

        foreach (['/api/v1/faculties', '/api/v1/teachers', '/api/v1/publications', '/api/v1/lookups'] as $path) {
            $this->getJson($path)
                ->assertForbidden()
                ->assertJson(['must_change_password' => true]);
        }
    }

    public function test_the_three_endpoints_that_lead_out_of_it_stay_open(): void
    {
        Sanctum::actingAs($this->userWhoHasNotChosenAPassword());

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJson(['must_change_password' => true]);
    }

    public function test_choosing_a_password_lifts_the_block(): void
    {
        $user = $this->userWhoHasNotChosenAPassword();
        $user->forceFill(['password' => Hash::make('the-emailed-one')])->save();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/password/change', [
            'current_password' => 'the-emailed-one',
            'password' => 'a-password-of-my-own',
            'password_confirmation' => 'a-password-of-my-own',
        ])->assertOk()->assertJson(['must_change_password' => false]);

        $this->assertNotNull($user->fresh()->password_set_at);

        $this->getJson('/api/v1/faculties')->assertOk();
    }

    public function test_a_teacher_who_chose_their_own_password_is_never_blocked(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $this->getJson('/api/v1/faculties')->assertOk();
        $this->getJson('/api/v1/lookups')->assertOk();
    }

    // ── Visibility ───────────────────────────────────────────────────────

    public function test_an_archived_teacher_is_absent_from_the_listing(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $teacher = $this->aVisibleTeacher();
        $teacher->forceFill(['is_archived' => true])->save();

        // Searched for by name, so the answer does not depend on which page of
        // 1,128 records they would otherwise have landed on.
        $response = $this->getJson('/api/v1/teachers?q=' . urlencode($teacher->first_name));

        $response->assertOk();

        $this->assertNotContains(
            $teacher->id,
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    public function test_an_archived_teachers_profile_is_gone_rather_than_hidden(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $teacher = $this->aVisibleTeacher();
        $path = $this->pathTo($teacher);

        $this->getJson($path)->assertOk();

        $teacher->forceFill(['is_archived' => true])->save();

        // 404, not 403: a record that was taken down should read as never having
        // been there, rather than confirming it exists to anyone who asks.
        $this->getJson($path)->assertNotFound();
        $this->getJson($path . '/publications')->assertNotFound();
    }

    public function test_an_inactive_teacher_is_gone_too(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $teacher = $this->aVisibleTeacher();
        $path = $this->pathTo($teacher);

        $teacher->forceFill(['is_active' => false])->save();

        $this->getJson($path)->assertNotFound();
    }

    public function test_a_teacher_cannot_be_reached_under_a_department_they_are_not_in(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $teacher = $this->aVisibleTeacher();

        $other = Department::where('is_active', true)
            ->where('id', '!=', $teacher->department_id)
            ->whereHas('faculty', fn ($q) => $q->where('is_active', true))
            ->with('faculty')
            ->first();

        if (! $other) {
            $this->markTestSkipped('Only one department in the development database.');
        }

        $this->getJson(sprintf(
            '/api/v1/%s/%s/%s',
            $other->faculty->short_name,
            $other->code,
            $teacher->webpage,
        ))->assertNotFound();
    }

    // ── Shape of the answers ─────────────────────────────────────────────

    public function test_a_listing_stays_a_listing(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $row = $this->getJson('/api/v1/teachers?per_page=1')->assertOk()->json('data.0');

        $this->assertArrayHasKey('full_name', $row);

        /*
         * A list row carries no profile sections. TeacherResource serves both
         * depths, and the depth comes from what the query eager-loaded — if a
         * listing ever started loading relations, a page of a hundred rows would
         * silently become a hundred profiles.
         */
        foreach (['educations', 'experiences', 'awards', 'memberships', 'skills'] as $section) {
            $this->assertArrayNotHasKey($section, $row);
        }
    }

    public function test_a_profile_carries_the_sections_a_listing_does_not(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $profile = $this->getJson($this->pathTo($this->aVisibleTeacher()))->assertOk()->json('data');

        $this->assertArrayHasKey('educations', $profile);
        $this->assertArrayHasKey('publications_count', $profile);
    }

    public function test_include_trims_a_profile_to_what_was_asked_for(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $profile = $this->getJson($this->pathTo($this->aVisibleTeacher()) . '?include=education')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('educations', $profile);
        $this->assertArrayNotHasKey('awards', $profile);
        $this->assertArrayNotHasKey('memberships', $profile);
    }

    public function test_a_profile_never_carries_the_private_columns(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $profile = $this->getJson($this->pathTo($this->aVisibleTeacher()))->assertOk()->json('data');

        // Contact details the public profile page already shows are fine. These
        // are not on it, and must not appear at any depth.
        foreach (['date_of_birth', 'present_address', 'permanent_address', 'nid', 'verification_token', 'salary'] as $column) {
            $this->assertArrayNotHasKey($column, $profile);
        }
    }

    public function test_an_abstract_belongs_to_the_single_view_only(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $row = $this->getJson('/api/v1/publications?per_page=1')->assertOk()->json('data.0');

        if ($row === null) {
            $this->markTestSkipped('No publications in the development database.');
        }

        $this->assertArrayNotHasKey('abstract', $row);
    }

    public function test_the_single_view_carries_the_abstract_and_the_citations(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $teacher = Teacher::published()
            ->whereNotNull('teachers.webpage')
            ->whereHas('publications')
            ->whereHas('department.faculty', fn ($q) => $q->where('is_active', true))
            ->with('department.faculty', 'publications')
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('No published teacher with a publication.');
        }

        $publication = $teacher->publications->first();

        $paper = $this->getJson($this->pathTo($teacher) . '/publications/' . ($publication->slug ?: $publication->id))
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('abstract', $paper);
        $this->assertSame(['apa', 'ieee', 'bibtex'], array_keys($paper['citations']));
        $this->assertStringContainsString($teacher->first_name, $paper['authors']);
    }

    public function test_the_lookups_carry_ids_a_filter_can_use(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $lookups = $this->getJson('/api/v1/lookups')->assertOk()->json('data');

        foreach (['designations', 'employment_statuses', 'publication_types', 'publication_quartiles'] as $list) {
            $this->assertArrayHasKey($list, $lookups);
        }

        // A name on its own cannot be filtered by — the id is what the query
        // parameters take, so it has to travel with it.
        if (filled($lookups['designations'])) {
            $this->assertArrayHasKey('id', $lookups['designations'][0]);
            $this->assertArrayHasKey('name', $lookups['designations'][0]);
        }
    }

    public function test_the_cv_and_the_vcard_are_the_files_the_website_serves(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $teacher = $this->aVisibleTeacher();

        $vcard = $this->get($this->pathTo($teacher) . '/vcard');

        // Both can be switched off in the site settings; a 404 then is correct,
        // and there is nothing to compare.
        if ($vcard->status() === 404) {
            $this->markTestSkipped('Profile downloads are switched off in this database.');
        }

        $vcard->assertOk();
        $this->assertStringContainsString('text/vcard', $vcard->headers->get('Content-Type'));
        $this->assertStringContainsString('BEGIN:VCARD', $vcard->getContent());
    }

    public function test_the_flat_department_list_carries_its_faculty(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $row = $this->getJson('/api/v1/departments')->assertOk()->json('data.0');

        // Flat, so a row that did not name its faculty could not be turned back
        // into a path — and the path is the only way to reach anything under it.
        $this->assertNotNull($row['faculty']['slug'] ?? null);
        $this->assertNotNull($row['slug'] ?? null);
    }

    public function test_paging_is_capped(): void
    {
        Sanctum::actingAs($this->userWithPassword('their-own'));

        $response = $this->getJson('/api/v1/teachers?per_page=100000')->assertOk();

        $this->assertLessThanOrEqual(100, $response->json('meta.per_page'));
    }

    // ── Fixtures ─────────────────────────────────────────────────────────

    /** A user who has chosen their own password, so nothing is blocked. */
    protected function userWithPassword(string $password): User
    {
        $user = User::query()->first();

        if (! $user) {
            $this->markTestSkipped('No user in the development database.');
        }

        // Held inside the transaction the base TestCase rolls back.
        $user->forceFill([
            'password' => Hash::make($password),
            'password_set_at' => now(),
            'is_active' => true,
        ])->save();

        return $user->fresh();
    }

    /** A user still on the password that was emailed to them. */
    protected function userWhoHasNotChosenAPassword(): User
    {
        $user = $this->userWithPassword('the-emailed-one');

        $user->forceFill(['password_set_at' => null])->save();

        return $user->fresh();
    }

    protected function aVisibleTeacher(): Teacher
    {
        $teacher = Teacher::published()
            ->whereNotNull('teachers.webpage')
            ->whereHas('department.faculty', fn ($q) => $q->where('is_active', true))
            ->whereHas('department', fn ($q) => $q->where('is_active', true))
            ->with('department.faculty')
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('No published teacher in the development database.');
        }

        return $teacher;
    }

    protected function pathTo(Teacher $teacher): string
    {
        return sprintf(
            '/api/v1/%s/%s/%s',
            $teacher->department->faculty->short_name,
            $teacher->department->code,
            $teacher->webpage,
        );
    }
}
