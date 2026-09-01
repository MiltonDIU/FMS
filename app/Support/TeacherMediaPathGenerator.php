<?php

namespace App\Support;

use App\Models\Teacher;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Files a teacher's media under the year they joined.
 *
 * The default generator puts every item in a directory named after its own id,
 * straight off the root of the disk. With one photograph per teacher that is
 * already 1,800-odd entries at the top level of storage/app/public, and the
 * directory becomes something no file manager, no `ls`, and no backup listing
 * can be read through. It only grows.
 *
 * So the path gains a year: teachers/2019/1874/photo.jpg. Roughly twenty-five
 * year folders hold the whole faculty, each small enough to open, and a picture
 * can be found by the one thing anybody knows about the teacher without looking
 * up a database id.
 *
 * The year is read from the media record rather than from the teacher, because
 * a path has to stay put. Joining dates do get corrected, and a generator that
 * recomputed the year would move every existing file out from under its own
 * record the moment somebody fixed a typo. MediaObserver stamps `storage_year`
 * into the record's custom properties when the file arrives; that stamp is the
 * stored path, and this only reads it. The fallback below is for records that
 * predate the stamp.
 */
class TeacherMediaPathGenerator implements PathGenerator
{
    /**
     * Where a teacher with no joining date on file goes.
     *
     * 183 of them, so this is a real folder and not a theoretical one. Named
     * rather than left as a bare year so nobody reads it as a date.
     */
    public const UNKNOWN_YEAR = 'unknown-year';

    /** The root every teacher file sits under, so the disk has one entry, not thousands. */
    public const ROOT = 'teachers';

    public function getPath(Media $media): string
    {
        return $this->directoryFor($media) . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->directoryFor($media) . '/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->directoryFor($media) . '/responsive-images/';
    }

    /**
     * The directory this media lives in, relative to the disk root.
     *
     * Public and static so the reorganise pass in DownloadTeacherPhotosCommand
     * can ask for the same answer instead of rebuilding the shape by hand and
     * drifting away from it.
     */
    public function directoryFor(Media $media): string
    {
        return self::ROOT . '/' . $this->yearFor($media) . '/' . $media->getKey();
    }

    /**
     * The year folder for a media record.
     *
     * The stamped value is validated rather than trusted: it ends up in a
     * filesystem path, and custom properties are a JSON column that anything
     * with database access can write. Only four digits or the known constant
     * get through, so nothing can walk out of the storage directory.
     */
    public function yearFor(Media $media): string
    {
        $stored = $media->getCustomProperty('storage_year');

        if (is_string($stored) && (self::isYear($stored) || $stored === self::UNKNOWN_YEAR)) {
            return $stored;
        }

        return self::yearForTeacherId($media->model_id);
    }

    /** The year a teacher's files belong under, from their joining date. */
    public static function yearForTeacher(?Teacher $teacher): string
    {
        $year = $teacher?->joining_date?->format('Y');

        return is_string($year) && self::isYear($year) ? $year : self::UNKNOWN_YEAR;
    }

    /** The same, when only the id is at hand. */
    public static function yearForTeacherId(int | string | null $teacherId): string
    {
        if (blank($teacherId)) {
            return self::UNKNOWN_YEAR;
        }

        return self::yearForTeacher(Teacher::find($teacherId));
    }

    protected static function isYear(string $value): bool
    {
        return strlen($value) === 4 && ctype_digit($value);
    }
}
