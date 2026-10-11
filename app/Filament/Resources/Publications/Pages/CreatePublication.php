<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Filament\Resources\Publications\PublicationResource;
use App\Support\PublicationAuthorship;
use Filament\Resources\Pages\CreateRecord;

class CreatePublication extends CreateRecord
{
    protected static string $resource = PublicationResource::class;
    protected array $authorData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->authorData = [
            'first_author_id' => $data['first_author_id'] ?? null,
            'corresponding_author_id' => $data['corresponding_author_id'] ?? null,
            'co_author_ids' => $data['co_author_ids'] ?? [],
        ];

        unset($data['first_author_id'], $data['corresponding_author_id'], $data['co_author_ids']);

        return $data;
    }

    /**
     * Through PublicationAuthorship, like every other form that names authors,
     * so a teacher creating a paper here cannot put a colleague on it without
     * that colleague agreeing.
     */
    protected function afterCreate(): void
    {
        PublicationAuthorship::sync($this->getRecord(), $this->authorData, actor: auth()->user());
    }
}
