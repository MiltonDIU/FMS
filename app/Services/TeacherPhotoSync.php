<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\OutboundUrl;
use App\Models\Teacher;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Puts a teacher's photograph back on their profile.
 *
 * The problem this solves is a profile with no picture on it while the
 * database still remembers there is one. That is the state a server is left in
 * when it holds the media rows and not the files: the website, the CV and every
 * directory card fall back to the initials block, and nothing on the page says
 * why or where the photograph went.
 *
 * It goes and gets it from the same place teachers:download-photos got it —
 * the old faculty site — and stores it, so the picture appears everywhere a
 * picture of that teacher appears. Nothing is handed to the browser: the point
 * is the profile, not a file on somebody's desktop.
 *
 * One teacher at a time, from their row. The whole faculty at once is what
 * `teachers:download-photos --repair` is for, and it is much the faster way to
 * do that.
 */
class TeacherPhotoSync
{
    /** The profile already has the photograph; nothing to do. */
    public const ALREADY_PRESENT = 'already_present';

    /** Fetched from the old site and saved onto the profile. */
    public const RESTORED = 'restored';

    /** There is no photograph anywhere — not here, not on the old site. */
    public const NOTHING_TO_SYNC = 'nothing_to_sync';

    /** The stored address was refused by the outbound guard. */
    public const REFUSED = 'refused';

    /** The old site could not give it to us, or it could not be stored. */
    public const FAILED = 'failed';

    /**
     * Mirrors teachers:download-photos, so a picture this refuses is one that
     * command would have refused too.
     */
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private const MAX_BYTES = 8 * 1024 * 1024;

    /** Seconds to wait on the old faculty site before giving up. */
    private const TIMEOUT = 15;

    /** Where the fetched bytes are put before the media library takes them. */
    private const STAGING_DIRECTORY = 'app/private/teacher-photo-sync';

    /**
     * Brings this teacher's photograph onto their profile.
     *
     * @return array{status: string, bytes?: int, path?: string, detail?: string}
     */
    public function sync(Teacher $teacher): array
    {
        if ($this->storedFile($teacher) !== null) {
            return ['status' => self::ALREADY_PRESENT];
        }

        $url = $this->sourceUrl($teacher);

        if ($url === null) {
            return ['status' => self::NOTHING_TO_SYNC];
        }

        /*
         * The same outbound guard the command uses. The address comes out of an
         * import of another system's data rather than off a form here, and this
         * fetch happens from inside the network, where a private address is
         * reachable and a visitor's browser is not.
         */
        if (OutboundUrl::rejectionReason($url) !== null) {
            return ['status' => self::REFUSED, 'detail' => $url];
        }

        return $this->fetchAndStore($teacher, $url);
    }

    /**
     * The photograph this teacher already has, as a file on this machine.
     *
     * Null covers both "no media row" and "a media row whose file is gone" —
     * for syncing they are the same thing, something to go and fetch.
     */
    public function storedFile(Teacher $teacher): ?string
    {
        if (! $teacher->exists) {
            return null;
        }

        $media = $teacher->getFirstMedia('avatar');

        if (! $media instanceof Media) {
            return null;
        }

        $path = $media->getPath();

        return is_file($path) ? $path : null;
    }

    /**
     * Where the old faculty site keeps this teacher's photograph.
     *
     * The media row is asked first. teachers:download-photos writes source_url
     * into custom_properties when it brings a picture over, and then clears the
     * teachers.photo column — which is why that column is empty on all 2,118
     * rows and why Teacher::legacyPhotoUrl() on its own would answer "no
     * picture" for every teacher in the system. What the command recorded is
     * the only surviving trace of where these came from.
     *
     * The column is still consulted afterwards, for a teacher who has a legacy
     * filename but was never fetched.
     */
    public function sourceUrl(Teacher $teacher): ?string
    {
        $media = $teacher->getFirstMedia('avatar');

        if ($media !== null) {
            $recorded = trim((string) $media->getCustomProperty('source_url', ''));

            if ($recorded !== '' && Str::startsWith($recorded, ['http://', 'https://'])) {
                return $recorded;
            }

            $filename = trim((string) $media->getCustomProperty('legacy_filename', ''));

            if ($filename !== '') {
                return Teacher::PHOTO_BASE_URL . rawurlencode($filename);
            }
        }

        return $teacher->legacyPhotoUrl();
    }

    /**
     * @return array{status: string, bytes?: int, path?: string, detail?: string}
     */
    private function fetchAndStore(Teacher $teacher, string $url): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->withOptions(['stream' => false])->get($url);
        } catch (\Throwable $e) {
            return $this->failed(Str::limit($e->getMessage(), 80));
        }

        if (! $response->successful()) {
            return $this->failed('the old site answered HTTP ' . $response->status());
        }

        $body = $response->body();

        if ($body === '') {
            return $this->failed('the old site returned an empty response');
        }

        if (strlen($body) > self::MAX_BYTES) {
            return $this->failed('the file is larger than ' . (self::MAX_BYTES / 1024 / 1024) . ' MB');
        }

        /*
         * What the bytes are, not what the URL ends in. A photograph that host
         * no longer has answers 200 with an HTML error page, and stored
         * unchecked it would sit in the avatar collection as a broken image
         * nobody could explain.
         */
        $extension = $this->extensionOf($body);

        if ($extension === null) {
            return $this->failed('what came back is not an image');
        }

        $directory = storage_path(self::STAGING_DIRECTORY);
        File::ensureDirectoryExists($directory);

        $staged = $directory . DIRECTORY_SEPARATOR . 'teacher-' . $teacher->id . '-' . Str::random(8) . '.' . $extension;
        File::put($staged, $body);

        $failure = $this->store($teacher, $staged, $extension, $url);

        // addMedia moves the file, but a failed conversion can leave it behind.
        File::delete($staged);

        if ($failure !== null) {
            return $this->failed($failure);
        }

        $stored = $this->storedFile($teacher->refresh());

        if ($stored === null) {
            return $this->failed('it was saved but the file cannot be found afterwards');
        }

        return [
            'status' => self::RESTORED,
            'bytes' => strlen($body),
            'path' => $stored,
        ];
    }

    /**
     * Puts the fetched photograph on the teacher's profile.
     *
     * Mirrors teachers:download-photos so a picture saved here is
     * indistinguishable from one that command brought over: the same filename
     * rule (the teacher's own webpage handle, which is what identifies them
     * everywhere else), and the same custom properties, so where it came from
     * stays recorded and a later run can tell a fetched photograph from an
     * upload. MediaObserver stamps the joining year that decides the folder,
     * and the collection is singleFile, so this replaces the record whose file
     * had gone missing rather than leaving two.
     *
     * @return string|null  The reason it did not work, or null when it did.
     */
    private function store(Teacher $teacher, string $path, string $extension, string $url): ?string
    {
        try {
            $teacher->addMedia($path)
                ->usingFileName($this->storedFileName($teacher, $extension))
                ->usingName($teacher->full_name)
                ->withCustomProperties([
                    'source_url' => $url,
                    'legacy_filename' => $teacher->getRawOriginal('photo'),
                    'fetched_at' => now()->toIso8601String(),
                    // So a photograph restored from the teachers screen can be
                    // told apart from one the import brought over.
                    'restored_by' => 'photo-sync-action',
                ])
                ->toMediaCollection('avatar', 'public');
        } catch (\Throwable $e) {
            return Str::limit($e->getMessage(), 80);
        }

        /*
         * The column has to go, or none of this shows: getPhotoAttribute
         * returns the column when it is filled and only falls through to the
         * media library when it is not. Empty on every row today, so this is
         * belt and braces — but the command clears it for the same reason and
         * the two should not disagree.
         */
        if (filled($teacher->getRawOriginal('photo'))) {
            $teacher->forceFill(['photo' => null])->saveQuietly();
        }

        return null;
    }

    /**
     * The name the file is stored under: the teacher's own webpage handle,
     * exactly as teachers:download-photos names it.
     *
     * Never trusted straight into a path — it is imported data, and a handle
     * carrying a slash or dots would write outside the directory.
     */
    private function storedFileName(Teacher $teacher, string $extension): string
    {
        $handle = trim((string) $teacher->webpage);
        $handle = $handle === '' ? '' : Str::slug($handle, '-', null);

        if ($handle === '') {
            $handle = 'teacher-' . $teacher->id;
        }

        return $handle . '.' . $extension;
    }

    /**
     * @return array{status: string, detail: string}
     */
    private function failed(string $detail): array
    {
        return ['status' => self::FAILED, 'detail' => $detail];
    }

    /** The extension the bytes themselves call for, or null if they are not an image. */
    private function extensionOf(string $body): ?string
    {
        $info = @getimagesizefromstring($body);

        if ($info === false) {
            return null;
        }

        return self::IMAGE_TYPES[$info['mime'] ?? ''] ?? null;
    }

    /** Human-readable size, for the notification. */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }
}
