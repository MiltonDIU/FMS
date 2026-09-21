<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Teacher;
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
    /** Whether there is anything to download for this teacher. */
    public function exists(Teacher $teacher): bool
    {
        return $this->resolve($teacher) !== null;
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
