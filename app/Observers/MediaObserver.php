<?php

namespace App\Observers;

use App\Models\Teacher;
use App\Support\TeacherMediaPathGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaObserver
{
    /**
     * Writes down which year folder a teacher's file belongs in, before it is
     * stored.
     *
     * This is the one place the decision is made, and it is why the rule holds
     * for every route a file can arrive by — the download command, the photo
     * field on the teacher form, the same field on My Profile, and any later
     * replacement of an existing picture. They all end at the media library, and
     * the media library ends here.
     *
     * It runs on `creating`, which is early enough: the media row is saved
     * before the file is copied, so the stamp is on the record by the time the
     * path generator is asked where the file goes.
     *
     * Stamping it, rather than working the year out from the joining date each
     * time a path is needed, is what keeps a file findable. Joining dates get
     * corrected; if the path followed the date, correcting one would leave the
     * photograph sitting in last year's folder with every reference pointing at
     * this year's, and nothing would say so — the image would simply stop
     * loading. The stamp is the stored path.
     */
    public function creating(Media $media): void
    {
        if ($media->model_type !== Teacher::class) {
            return;
        }

        if (filled($media->getCustomProperty('storage_year'))) {
            return;
        }

        $media->setCustomProperty(
            'storage_year',
            TeacherMediaPathGenerator::yearForTeacher($this->teacherFor($media)),
        );
    }

    /**
     * The teacher this media belongs to, without a query where one is not
     * needed — the media library associates the model before saving, so it is
     * usually already in hand.
     */
    protected function teacherFor(Media $media): ?Teacher
    {
        if ($media->relationLoaded('model')) {
            $model = $media->getRelation('model');

            if ($model instanceof Teacher) {
                return $model;
            }
        }

        return blank($media->model_id) ? null : Teacher::find($media->model_id);
    }
}
