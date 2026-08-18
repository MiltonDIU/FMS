<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Filament\Resources\Publications\PublicationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPublication extends EditRecord
{
    protected static string $resource = PublicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
    protected array $authorData = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->authorData['first'] = $data['first_author_id'] ?? null;
        $this->authorData['corresponding'] = $data['corresponding_author_id'] ?? null;
        $this->authorData['co_authors'] = $data['co_author_ids'] ?? [];

        unset($data['first_author_id'], $data['corresponding_author_id'], $data['co_author_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        /*
         * What each authorship already carried, kept across the rebuild.
         *
         * The form knows three things — who was first, who corresponded, and who
         * else is on it — and it rewrites the whole author list from those. The
         * rows hold more than that: the affiliation the export printed against
         * each author, whether that affiliation was ours, and the incentive
         * apportioned to them. None of it appears on this form, so a rebuild
         * that starts from the form alone quietly destroys all three. Editing a
         * title to fix a typo was enough to do it.
         */
        $carried = \DB::table('publication_authors')
            ->where('publication_id', $record->id)
            ->get()
            ->keyBy(fn ($row) => $row->authorable_type . ':' . $row->authorable_id . ':' . $row->author_role);

        \DB::table('publication_authors')->where('publication_id', $record->id)->delete();

        $parseKey = function ($key) {
            if (!$key) return [null, null];
            if (str_contains($key, ':')) {
                return explode(':', $key, 2);
            }
            return [\App\Models\Teacher::class, $key];
        };

        // A person whose role the editor changed keeps what was recorded about
        // them under the old one; the affiliation on a paper does not depend on
        // whether they are called its first author or its corresponding one.
        $previous = function (string $model, $id, string $role) use ($carried) {
            return $carried->get("{$model}:{$id}:{$role}")
                ?? $carried->first(fn ($row) => $row->authorable_type === $model
                    && (string) $row->authorable_id === (string) $id);
        };

        $insert = function (string $model, $id, string $role, int $sortOrder) use ($record, $previous) {
            $was = $previous($model, $id, $role);

            \DB::table('publication_authors')->insert([
                'publication_id' => $record->id,
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

        if ($this->authorData['first']) {
            [$model, $id] = $parseKey($this->authorData['first']);
            if ($model && $id) {
                $insert($model, $id, 'first', 0);
            }
        }

        if ($this->authorData['corresponding']) {
            [$model, $id] = $parseKey($this->authorData['corresponding']);
            if ($model && $id) {
                $insert($model, $id, 'corresponding', 0);
            }
        }

        if (!empty($this->authorData['co_authors'])) {
            foreach ($this->authorData['co_authors'] as $index => $morphedKey) {
                [$model, $id] = $parseKey($morphedKey);
                if ($model && $id) {
                    $insert($model, $id, 'co_author', $index + 1);
                }
            }
        }
    }
}
