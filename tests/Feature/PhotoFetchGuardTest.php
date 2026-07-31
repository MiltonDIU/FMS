<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Services\TeacherShareImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Two things fetch a teacher's photograph from inside the network: the
 * share-card generator, and dompdf rendering a CV with remote images enabled.
 *
 * The photo column is written by the legacy import from another system's data,
 * and it may hold an absolute URL. A browser fetching a bad one costs a broken
 * image; the server fetching one reaches addresses a visitor cannot — a database
 * host, or a cloud metadata endpoint holding instance credentials.
 *
 * So the server-side path is guarded and the <img> path is not, and these hold
 * that line.
 */
class PhotoFetchGuardTest extends TestCase
{
    protected function teacher(): Teacher
    {
        $teacher = Teacher::where('is_active', true)
            ->where('is_archived', false)
            ->whereNotNull('webpage')
            ->with('department.faculty', 'designation')
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('no published teacher');
        }

        return $teacher;
    }

    public static function internalAddresses(): array
    {
        return [
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'loopback' => ['http://127.0.0.1:3306/'],
            'private range' => ['http://10.0.0.5/internal'],
            'link local' => ['http://[::1]/'],
        ];
    }

    /**
     * @dataProvider internalAddresses
     */
    public function test_an_internal_address_is_refused_to_the_server(string $url): void
    {
        $teacher = $this->teacher();
        $teacher->photo = $url;

        $this->assertNull(
            $teacher->serverFetchablePhotoUrl(),
            "{$url} was offered to a server-side fetch",
        );
    }

    /**
     * @dataProvider internalAddresses
     */
    public function test_the_browser_path_is_left_alone(string $url): void
    {
        // photo_url feeds <img src>, which the visitor's browser resolves from
        // outside the network. Guarding it would cost a DNS lookup on every card
        // of every directory page and buy nothing.
        $teacher = $this->teacher();
        $teacher->photo = $url;

        $this->assertNotNull($teacher->photo_url);
    }

    public function test_an_ordinary_photograph_still_reaches_the_server(): void
    {
        $teacher = $this->teacher();
        $teacher->photo = 'a-teacher-photo.jpg';

        $this->assertNotNull($teacher->serverFetchablePhotoUrl());
        $this->assertStringStartsWith(Teacher::PHOTO_BASE_URL, $teacher->serverFetchablePhotoUrl());
    }

    public function test_the_share_card_never_requests_a_refused_address(): void
    {
        Http::fake(['*' => Http::response('not an image', 200)]);

        $teacher = $this->teacher();
        $teacher->photo = 'http://169.254.169.254/latest/meta-data/';

        Storage::disk('public')->delete(TeacherShareImage::relativePath($teacher));
        TeacherShareImage::pathFor($teacher);

        Http::assertNothingSent();
    }
}
