<?php

namespace Tests\Feature;

use App\Helpers\SeoPayload;
use App\Models\Teacher;
use App\Services\TeacherShareImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What a teacher's profile looks like when they share the link.
 *
 * A social preview is one image, a title and a line of text, so the card is
 * where the name, designation and department have to be legible — the 90-120px
 * thumbnail the legacy host serves renders as a smear in a feed.
 *
 * The contact details are deliberately absent, from the image and from the
 * structured data both. A profile page shows them to a visitor who came looking;
 * a share card is broadcast to everyone who sees the link, and the JSON-LD is
 * read by every crawler that fetches the URL.
 */
class TeacherShareCardTest extends TestCase
{
    protected function teacher(): Teacher
    {
        $teacher = Teacher::where('is_active', true)
            ->where('is_archived', false)
            ->whereNotNull('webpage')
            ->whereHas('department.faculty')
            ->with('department.faculty', 'designation', 'publications', 'teachingAreas')
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('no published teacher');
        }

        return $teacher;
    }

    public function test_the_card_is_drawn_at_the_size_networks_expect(): void
    {
        // No live fetch of the source photograph in a test run.
        Http::fake(['*' => Http::response('', 404)]);

        $teacher = $this->teacher();

        Storage::disk('public')->delete(TeacherShareImage::relativePath($teacher));

        $path = TeacherShareImage::pathFor($teacher);

        if ($path === null) {
            $this->markTestSkipped('no TrueType font available to draw with');
        }

        [$width, $height] = getimagesize(Storage::disk('public')->path($path));

        $this->assertSame(TeacherShareImage::WIDTH, $width);
        $this->assertSame(TeacherShareImage::HEIGHT, $height);
    }

    public function test_a_cached_card_is_reused_and_reissued_when_the_teacher_changes(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $teacher = $this->teacher();

        $first = TeacherShareImage::pathFor($teacher);

        if ($first === null) {
            $this->markTestSkipped('no TrueType font available to draw with');
        }

        $this->assertSame($first, TeacherShareImage::pathFor($teacher));

        // The path carries updated_at, so an edit invalidates it.
        $teacher->touch();

        $this->assertNotSame($first, TeacherShareImage::relativePath($teacher->fresh()));
    }

    public function test_the_profile_advertises_the_card_and_suggests_papers_with_abstracts(): void
    {
        $teacher = Teacher::where('is_active', true)
            ->where('is_archived', false)
            ->whereNotNull('webpage')
            ->whereHas('department.faculty')
            ->whereHas('publications', fn ($q) => $q->whereNotNull('abstract')->where('abstract', '!=', ''))
            ->with('department.faculty', 'designation', 'publications', 'socialLinks')
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('no teacher with an abstracted publication');
        }

        $payload = SeoPayload::forTeacher($teacher, $teacher->department->faculty, $teacher->department);

        $this->assertStringContainsString('share-image.png', $payload['image']);

        $papers = $payload['schema']['subjectOf'] ?? [];

        $this->assertNotEmpty($papers);
        $this->assertLessThanOrEqual(SeoPayload::SUGGESTED_PUBLICATIONS, count($papers));

        foreach ($papers as $paper) {
            $this->assertSame('ScholarlyArticle', $paper['@type']);
            $this->assertArrayHasKey('abstract', $paper, 'a suggested paper carried no abstract');
        }
    }

    public function test_no_contact_details_reach_the_structured_data(): void
    {
        $teacher = $this->teacher();

        $payload = SeoPayload::forTeacher($teacher, $teacher->department->faculty, $teacher->department);

        foreach (['email', 'telephone', 'address', 'homeLocation'] as $key) {
            $this->assertArrayNotHasKey($key, $payload['schema'], "{$key} is being published to crawlers");
        }
    }

    /**
     * Titles live in the name columns — "Professor Dr. Md. Asif Nazrul" is one
     * first_name — so taking the first letter of each field gave "PN".
     */
    public function test_initials_skip_the_titles_stored_in_the_name(): void
    {
        $cases = [
            'Professor Dr. Md. Asif Nazrul' => 'AN',
            'Mahbub Parvez' => 'MP',
            'Dr. Md. Sabur Khan' => 'SK',
            'Md.' => 'M',
        ];

        foreach ($cases as $name => $expected) {
            $teacher = new Teacher(['first_name' => $name]);
            $teacher->full_name = $name;

            $this->assertSame($expected, $teacher->initials, "initials for {$name}");
        }
    }
}
