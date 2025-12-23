<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Filament\Resources\Publications\PublicationResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePublication extends CreateRecord
{
    protected static string $resource = PublicationResource::class;
    protected array $authorData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->authorData['first'] = $data['first_author_id'] ?? null;
        $this->authorData['corresponding'] = $data['corresponding_author_id'] ?? null;
        $this->authorData['co_authors'] = $data['co_author_ids'] ?? [];

        unset($data['first_author_id'], $data['corresponding_author_id'], $data['co_author_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($this->authorData['first']) {
            $record->teachers()->attach($this->authorData['first'], ['author_role' => 'first', 'sort_order' => 0]);
        }

        if ($this->authorData['corresponding']) {
            $record->teachers()->attach($this->authorData['corresponding'], ['author_role' => 'corresponding', 'sort_order' => 0]);
        }

        if (!empty($this->authorData['co_authors'])) {
            foreach ($this->authorData['co_authors'] as $index => $teacherId) {
                $record->teachers()->attach($teacherId, ['author_role' => 'co_author', 'sort_order' => $index + 1]);
            }
        }
    }
}
