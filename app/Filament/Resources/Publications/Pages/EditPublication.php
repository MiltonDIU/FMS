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
        
        // Clear existing authors BEFORE re-attaching
        \DB::table('publication_authors')->where('publication_id', $record->id)->delete();

        $parseKey = function ($key) {
            if (!$key) return [null, null];
            if (str_contains($key, ':')) {
                return explode(':', $key, 2);
            }
            return [\App\Models\Teacher::class, $key];
        };

        if ($this->authorData['first']) {
            [$model, $id] = $parseKey($this->authorData['first']);
            if ($model && $id) {
                \DB::table('publication_authors')->insert([
                    'publication_id' => $record->id,
                    'authorable_type' => $model,
                    'authorable_id' => $id,
                    'author_role' => 'first',
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if ($this->authorData['corresponding']) {
            [$model, $id] = $parseKey($this->authorData['corresponding']);
            if ($model && $id) {
                \DB::table('publication_authors')->insert([
                    'publication_id' => $record->id,
                    'authorable_type' => $model,
                    'authorable_id' => $id,
                    'author_role' => 'corresponding',
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (!empty($this->authorData['co_authors'])) {
            foreach ($this->authorData['co_authors'] as $index => $morphedKey) {
                [$model, $id] = $parseKey($morphedKey);
                if ($model && $id) {
                    \DB::table('publication_authors')->insert([
                        'publication_id' => $record->id,
                        'authorable_type' => $model,
                        'authorable_id' => $id,
                        'author_role' => 'co_author',
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
