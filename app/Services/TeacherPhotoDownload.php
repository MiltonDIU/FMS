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

    /** Fetched from the old site and saved onto the profile. */
    public const SOURCE_RESTORED = 'restored';

    /** Fetched from the old site, but saving it onto the profile failed. */
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
     * from where the picture came from — and keeps it, so the teacher's profile
     * has a photograph from then on instead of the download being the only
     * copy anybody ever sees.
     *
     * @return array{ok: true, path: string, filename: string, size: int, source: string, temporary: bool, warning: ?string}
     *         |array{ok: false, reason: string, detail: ?string}
     */
    public function obtain(Teacher $teacher): array
    {
        if ($local = $this->resolve($teacher)) {
            return $local + [
                'ok' => true,
                'source' => self::SOURCE_STORAGE,
                'temporary' => false,
                'warning' => null,
            ];
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
     * Fetches the picture, puts it on the teacher's profile, and serves it.
     *
     * Saving it is the point. A photograph fetched only to be handed to the
     * browser leaves the profile exactly as empty as it was, so the next person
     * to look at that teacher — on the website, in a CV, on the card in the
     * directory — still sees the initials block, and the next person to press
     * this button fetches the same picture off the old site all over again. One
     * press now fixes the profile for everybody.
     *
     * It goes in the same way `teachers:download-photos` puts one there: same
     * collection, same filename rule, same custom properties. The collection is
     * singleFile, so this replaces the record whose file had gone missing
     * rather than leaving two.
     *
     * If the write fails the download still happens, from the copy in the
     * staging directory. Whoever pressed the button asked for a picture; a
     * storage problem is worth telling them about, not worth refusing them
     * over.
     *
     * @return array{ok: true, path: string, filename: string, size: int, source: string, temporary: bool, warning: ?string}
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

        $staged = $directory . DIRECTORY_SEPARATOR . 'teacher-' . $teacher->id . '-' . Str::random(8) . '.' . $extension;
        File::put($staged, $body);

        $failure = $this->store($teacher, $staged, $extension, $url);

        if ($failure === null) {
            /*
             * Served from where it now lives rather than from the staged copy,
             * so what the browser gets is the file the profile got.
             *
             * The staged copy is kept until this point on purpose: addMedia is
             * told to preserve it, so that if the record cannot be read back
             * there is still something to hand over rather than a fetch thrown
             * away.
             */
            $stored = $this->resolve($teacher->refresh());

            if ($stored !== null) {
                File::delete($staged);

                return $stored + [
                    'ok' => true,
                    'source' => self::SOURCE_RESTORED,
                    'temporary' => false,
                    'warning' => null,
                ];
            }

            $failure = 'it was saved but could not be read back';
        }

        return [
            'ok' => true,
            'path' => $staged,
            'filename' => $this->filename($teacher, $extension),
            'size' => strlen($body),
            'source' => self::SOURCE_LEGACY,
            // Deleted once it has been sent; nothing kept it.
            'temporary' => true,
            'warning' => $failure,
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
     * upload. MediaObserver stamps the joining year that decides the folder.
     *
     * @return string|null  The reason it did not work, or null when it did.
     */
    private function store(Teacher $teacher, string $path, string $extension, string $url): ?string
    {
        try {
            $teacher->addMedia($path)
                ->preservingOriginal()
                ->usingFileName($this->storedFileName($teacher, $extension))
                ->usingName($teacher->full_name)
                ->withCustomProperties([
                    'source_url' => $url,
                    'legacy_filename' => $teacher->getRawOriginal('photo'),
                    'fetched_at' => now()->toIso8601String(),
                    // So a photograph restored from a download button can be
                    // told apart from one the import brought over.
                    'restored_by' => 'download-action',
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
