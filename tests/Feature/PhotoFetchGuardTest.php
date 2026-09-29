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
    /**
     * A teacher with no uploaded photograph, so the column is what gets read.
     *
     * One with an upload answers from the media library and never looks at
     * the column — which is right, but proves nothing about the guard.
     */
    protected function teacher(): Teacher
    {
        $teacher = Teacher::where('is_active', true)
            ->where('is_archived', false)
            ->whereNotNull('webpage')
            ->whereDoesntHave('media', fn ($query) => $query->where('collection_name', 'avatar'))
            ->with('department.faculty', 'designation')
            ->first();

        if (! $teacher) {
            $this->markTestSkipped('no published teacher');
        }

        return $teacher;
    }

    /**
     * Put a value in the photo column as though the import had stored it.
     *
     * The accessors read the stored column (getRawOriginal), so an unsaved
     * assignment is invisible to them and every assertion would pass on null.
     */
    protected function withPhoto(Teacher $teacher, string $photo): Teacher
    {
        $teacher->photo = $photo;
        $teacher->syncOriginalAttribute('photo');

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
        $teacher = $this->withPhoto($this->teacher(), $url);

        // Offered to the browser, so it is the guard that refuses it here.
        $this->assertSame($url, $teacher->photo_url);
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
        $teacher = $this->withPhoto($this->teacher(), $url);

        $this->assertNotNull($teacher->photo_url);
    }

    public function test_an_ordinary_photograph_still_reaches_the_server(): void
    {
        // A bare filename is the legacy import's, and only the photo download
        // command asks for it — by its own guarded method, since photo_url no
        // longer turns it into an address on the old host.
        $teacher = $this->withPhoto($this->teacher(), 'a-teacher-photo.jpg');

        $this->assertNotNull($teacher->serverFetchableLegacyPhotoUrl());
        $this->assertStringStartsWith(Teacher::PHOTO_BASE_URL, $teacher->serverFetchableLegacyPhotoUrl());
    }

    public function test_the_share_card_reads_our_own_photograph_from_the_disk(): void
    {
        $teacher = Teacher::where('is_active', true)
            ->where('is_archived', false)
            ->whereNotNull('webpage')
            ->whereHas('media', fn ($query) => $query->where('collection_name', 'avatar'))
            ->with('department.faculty', 'designation')
            ->get()
            ->first(fn (Teacher $candidate) => $candidate->localPhotoPath() !== null);

        if (! $teacher) {
            $this->markTestSkipped('no teacher with a photograph on disk');
        }

        Http::fake(['*' => Http::response('not an image', 200)]);

        Storage::disk('public')->delete(TeacherShareImage::relativePath($teacher));
        TeacherShareImage::pathFor($teacher);

        // Its URL points back at this application; requesting it would have
        // the server wait on itself.
        Http::assertNothingSent();
    }

    public function test_the_share_card_never_requests_a_refused_address(): void
    {
        Http::fake(['*' => Http::response('not an image', 200)]);

        $teacher = $this->withPhoto($this->teacher(), 'http://169.254.169.254/latest/meta-data/');

        Storage::disk('public')->delete(TeacherShareImage::relativePath($teacher));
        TeacherShareImage::pathFor($teacher);

        Http::assertNothingSent();
    }
}
