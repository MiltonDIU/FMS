<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\Setting;
use App\Models\Teacher;
use Tests\TestCase;

/**
 * A name on a publication's byline links to the teacher's profile only when
 * that profile would open.
 *
 * The link used to be built from the address alone, so a teacher switched off
 * Publicly Visible, deactivated, or unverified while unverified profiles are
 * hidden was still a link — to a 404.
 */
class PublicationBylineLinkTest extends TestCase
{
    /** A paper with a published teacher on it, and that teacher. */
    protected function paperWithTeacher(): array
    {
        $teacher = Teacher::query()->published()
            ->whereNotNull('webpage')
            ->whereHas('publications')
            ->whereHas('department', fn ($q) => $q->where('is_active', true)
                ->whereHas('faculty', fn ($f) => $f->where('is_active', true)))
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('no published teacher with a publication');
        }

        return [$teacher->publications()->first(), $teacher];
    }

    protected function urlFor(Publication $publication, Teacher $teacher): ?string
    {
        $entry = $publication->fresh()->byline()->first(fn (array $a) => $a['teacher']?->id === $teacher->id);

        $this->assertNotNull($entry, 'the teacher is on the byline');

        return $entry['url'];
    }

    public function test_a_published_teacher_is_a_link_that_opens(): void
    {
        [$publication, $teacher] = $this->paperWithTeacher();

        $url = $this->urlFor($publication, $teacher);

        $this->assertNotNull($url);
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk();
    }

    public function test_a_teacher_not_publicly_visible_is_text(): void
    {
        [$publication, $teacher] = $this->paperWithTeacher();
        $teacher->forceFill(['is_public' => false])->saveQuietly();

        $this->assertNull($this->urlFor($publication, $teacher));
    }

    public function test_an_unverified_teacher_is_text_while_unverified_profiles_are_hidden(): void
    {
        [$publication, $teacher] = $this->paperWithTeacher();
        $teacher->forceFill(['verification_status' => 'unverified'])->saveQuietly();

        Setting::set(Teacher::HIDE_UNVERIFIED_SETTING, false);
        $this->assertNotNull($this->urlFor($publication, $teacher), 'shown while unverified profiles are shown');

        Setting::set(Teacher::HIDE_UNVERIFIED_SETTING, true);
        $this->assertNull($this->urlFor($publication, $teacher));
    }

    /** isPublished() and scopePublished() must not drift apart. */
    public function test_the_instance_check_agrees_with_the_scope(): void
    {
        foreach ([false, true] as $hide) {
            Setting::set(Teacher::HIDE_UNVERIFIED_SETTING, $hide);

            $published = Teacher::query()->published()->pluck('id')->flip();

            Teacher::query()->limit(500)->get()->each(fn (Teacher $t) => $this->assertSame(
                isset($published[$t->id]),
                $t->isPublished(),
                "teacher #{$t->id} with unverified hidden " . ($hide ? 'on' : 'off'),
            ));
        }
    }
}
