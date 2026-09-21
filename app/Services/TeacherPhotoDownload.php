<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Teacher;
use App\Helpers\OutboundUrl;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Hands one teacher's photograph back as a file to save.
 *
 * Until now the only way to get a photograph out of the system was to open the
 * profile and right-click the picture — which gives you whatever the <img> was
 * showing, a resized copy, under a filename like `01HQ8X...jpg` that says
 * nothing about whose face it is.
 *
 * This gives the master file under a name somebody can file: the photograph as
 * it was uploaded, uncropped, called after the teacher and their employee ID.
 */
class TeacherPhotoDownload
{
    /** Where the file handed to the browser came from. */
    public const SOURCE_STORAGE = 'storage';

    public const SOURCE_LEGACY = 'legacy';

    /** Why there is nothing to hand over. */
    public const REASON_NONE = 'none';

    public const REASON_REFUSED = 'refused';

    public const REASON_FETCH_FAILED = 'fetch_failed';

    /**
     * Mirrors teachers:download-photos, so a picture this refuses is a picture
     * that command would have refused too.
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

    private const STAGING_DIRECTORY = 'app/private/teacher-photo-downloads';

    /**
     * The photograph, from wherever it can be had.
     *
     * Two places, in order. Our own storage first, because that is the copy we
     * hold and it costs nothing. Failing that, the same address on the old
     * faculty site that teachers:download-photos fetched from — which the media
     * row still remembers, in source_url, for every one of the 1,986
     * photographs it brought over.
     *
     * That second path is the whole point of this method. A server whose
     * storage has not been populated has the media rows and none of the files,
     * and before this it could only shrug. Now it goes and gets the picture
     * from where the picture came from.
     *
     * @return array{ok: true, path: string, filename: string, size: int, source: string, temporary: bool}
     *         |array{ok: false, reason: string, detail: ?string}
     */
    public function obtain(Teacher $teacher): array
    {
        if ($local = $this->resolve($teacher)) {
            return $local + ['ok' => true, 'source' => self::SOURCE_STORAGE, 'temporary' => false];
        }

        $url = $this->legacySourceUrl($teacher);

        if ($url === null) {
            return ['ok' => false, 'reason' => self::REASON_NONE, 'detail' => null];
        }

        /*
         * The same outbound guard the command uses. The address comes out of an
         * import of another system's data rather than off a form here, and this
         * fetch happens from inside the network, where a private address is
         * reachable and a visitor's browser is not.
         */
        if (OutboundUrl::rejectionReason($url) !== null) {
            return ['ok' => false, 'reason' => self::REASON_REFUSED, 'detail' => $url];
        }

        return $this->fetch($teacher, $url);
    }

    /**
     * Whether this teacher has a photograph on record.
     *
     * A question about the database, never about the disk: a file that has gone
     * missing is something to report when somebody asks for it, not a reason to
     * quietly pretend the teacher has no picture.
     */
    public function exists(Teacher $teacher): bool
    {
        return $teacher->exists
            && ($teacher->getFirstMedia('avatar') instanceof Media || $this->legacySourceUrl($teacher) !== null);
    }

    /**
     * Where the old faculty site keeps this teacher's photograph.
     *
     * The media row is asked first. teachers:download-photos writes source_url
     * into custom_properties when it brings a picture over, and then clears the
     * teachers.photo column — which is why the column is empty on all 2,118
     * rows and why Teacher::legacyPhotoUrl() alone would answer "no picture"
     * for every teacher in the system. What the command recorded is the only
     * surviving record of where these came from.
     *
     * The column is still consulted afterwards, for a teacher who has a legacy
     * filename but was never fetched.
     */
    public function legacySourceUrl(Teacher $teacher): ?string
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
     * Fetches the picture and stages it for the browser.
     *
     * Nothing is written into the media library. Bringing a missing photograph
     * back into storage is a repair, it is what
     * `teachers:download-photos --repair` exists for, and doing it as a side
     * effect of somebody pressing a download button would mean a read turning
     * into a write nobody asked for.
     *
     * @return array{ok: true, path: string, filename: string, size: int, source: string, temporary: bool}
     *         |array{ok: false, reason: string, detail: ?string}
     */
    private function fetch(Teacher $teacher, string $url): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->withOptions(['stream' => false])->get($url);
        } catch (\Throwable $e) {
            return $this->failedFetch(Str::limit($e->getMessage(), 80));
        }

        if (! $response->successful()) {
            return $this->failedFetch('the old site answered HTTP ' . $response->status());
        }

        $body = $response->body();

        if ($body === '') {
            return $this->failedFetch('the old site returned an empty response');
        }

        if (strlen($body) > self::MAX_BYTES) {
            return $this->failedFetch('the file is larger than ' . (self::MAX_BYTES / 1024 / 1024) . ' MB');
        }

        /*
         * What the bytes are, not what the URL ends in. A photograph that host
         * no longer has answers 200 with an HTML error page, and handed to the
         * browser as a .jpg that is a file nobody can open.
         */
        $extension = $this->extensionOf($body);

        if ($extension === null) {
            return $this->failedFetch('what came back is not an image');
        }

        $directory = storage_path(self::STAGING_DIRECTORY);
        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . 'teacher-' . $teacher->id . '-' . Str::random(8) . '.' . $extension;
        File::put($path, $body);

        return [
            'ok' => true,
            'path' => $path,
            'filename' => $this->filename($teacher, $extension),
            'size' => strlen($body),
            'source' => self::SOURCE_LEGACY,
            // Deleted once it has been sent; this is a copy, not a new record.
            'temporary' => true,
        ];
    }

    /**
     * @return array{ok: false, reason: string, detail: string}
     */
    private function failedFetch(string $detail): array
    {
        return ['ok' => false, 'reason' => self::REASON_FETCH_FAILED, 'detail' => $detail];
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

    /**
     * The file on disk and the name the browser should save it under.
     *
     * The original rather than a conversion: this is the copy somebody asked
     * for because they mean to use it elsewhere — an ID card, a programme, a
     * handover — and the resized versions exist for pages, not for that.
     *
     * Null when the media row points at a file that is not there. 1,986 of the
     * 2,118 teachers have a photograph, and a row surviving its file is the
     * kind of thing that turns a download into a 500.
     *
     * @return array{path: string, filename: string, size: int}|null
     */
    public function resolve(Teacher $teacher): ?array
    {
        if (! $teacher->exists) {
            return null;
        }

        $media = $teacher->getFirstMedia('avatar');

        if (! $media instanceof Media) {
            return null;
        }

        $path = $media->getPath();

        if (! is_file($path)) {
            return null;
        }

        return [
            'path' => $path,
            'filename' => $this->filename($teacher, pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg'),
            'size' => (int) ($media->size ?: filesize($path) ?: 0),
        ];
    }

    /**
     * What the saved file is called.
     *
     * Name and employee ID together, because each answers a question the other
     * does not: the name is what a person recognises in a folder, and the ID is
     * what matches the row in the HR system when two members of staff are both
     * called Md. Rafiqul Islam — and several are.
     */
    protected function filename(Teacher $teacher, string $extension): string
    {
        $employeeId = $this->sanitise((string) ($teacher->employee_id ?? ''));
        $name = $this->sanitise($teacher->full_name);

        $base = match (true) {
            $name !== '' && $employeeId !== '' => $name . ' (' . $employeeId . ')',
            $name !== '' => $name,
            $employeeId !== '' => $employeeId,
            // full_name already falls back through the user account and the
            // employee ID before it gives up, so reaching here means the record
            // carries no readable identity at all. The internal ID is the one
            // thing every teacher has.
            default => 'teacher-' . $teacher->id,
        };

        return $base . '.' . $extension;
    }

    /**
     * Strips anything a filesystem would object to, keeping letters from any
     * script — a good share of these names are written in Bangla.
     */
    protected function sanitise(string $value): string
    {
        $value = preg_replace('/[^\p{L}\p{N} ()._-]+/u', '', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /** Human-readable size, for the tooltip. */
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
