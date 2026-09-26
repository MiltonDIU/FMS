<?php

declare(strict_types=1);

namespace App\Support;

use App\Filament\Resources\Publications\Schemas\PublicationForm;
use App\Models\Publication;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

/**
 * Writes a publication's author list from one row of the teacher form's
 * publications repeater.
 *
 * Used both where the form saves directly and where TeacherVersionService
 * applies an approved version. There were two copies: the form's, and a
 * shorter one in the service that treated "App\Models\Teacher:1704" as a
 * plain id, dropped external authors, and synced away every author's
 * affiliation and incentive amount — approving a publications change failed
 * outright on the first and would have lost the rest.
 */
final class PublicationAuthorship
{
    /**
     * @param  array<string, mixed>  $item  first_author_id, corresponding_author_id
     *                                     and co_author_ids, each a "Model:id" key
     * @param  Teacher  $teacher  whose profile this row belongs to; they stay an
     *                            author even when the row does not name them
     */
    public static function write(Publication $publication, array $item, Teacher $teacher): void
    {
        $carried = DB::table('publication_authors')
            ->where('publication_id', $publication->id)
            ->get()
            ->keyBy(fn ($row) => $row->authorable_type . ':' . $row->authorable_id . ':' . $row->author_role);

        DB::table('publication_authors')->where('publication_id', $publication->id)->delete();

        // What an author had on this paper before — affiliation, incentive —
        // survives being written again, under the same role if it can.
        $previous = function (string $model, $id, string $role) use ($carried) {
            return $carried->get("{$model}:{$id}:{$role}")
                ?? $carried->first(fn ($row) => $row->authorable_type === $model
                    && (string) $row->authorable_id === (string) $id);
        };

        $insertAuthor = function (string $model, $id, string $role, int $sortOrder) use ($publication, $previous) {
            $was = $previous($model, $id, $role);

            DB::table('publication_authors')->insert([
                'publication_id' => $publication->id,
                'authorable_type' => $model,
                'authorable_id' => $id,
                'author_role' => $role,
                'sort_order' => $sortOrder,
                'affiliation' => $was->affiliation ?? null,
                'used_our_affiliation' => $was->used_our_affiliation ?? null,
                'incentive_amount' => $was->incentive_amount ?? 0.00,
                'created_at' => $was->created_at ?? now(),
                'updated_at' => now(),
            ]);
        };

        $insertedKeys = [];

        if (! empty($item['first_author_id'])) {
            [$model, $id] = PublicationForm::parseKey($item['first_author_id']);
            if ($model && $id) {
                $insertAuthor($model, $id, 'first', 0);
                $insertedKeys[] = "{$model}:{$id}";
            }
        }

        if (! empty($item['corresponding_author_id'])) {
            [$model, $id] = PublicationForm::parseKey($item['corresponding_author_id']);
            if ($model && $id) {
                $insertAuthor($model, $id, 'corresponding', 0);
                $insertedKeys[] = "{$model}:{$id}";
            }
        }

        if (! empty($item['co_author_ids']) && is_array($item['co_author_ids'])) {
            foreach ($item['co_author_ids'] as $index => $coAuthorKey) {
                [$model, $id] = PublicationForm::parseKey($coAuthorKey);
                if ($model && $id) {
                    $insertAuthor($model, $id, 'co_author', $index + 1);
                    $insertedKeys[] = "{$model}:{$id}";
                }
            }
        }

        $teacherKey = Teacher::class . ":{$teacher->id}";
        if (! in_array($teacherKey, $insertedKeys, true)) {
            $insertAuthor(Teacher::class, $teacher->id, 'co_author', 99);
        }
    }
}
