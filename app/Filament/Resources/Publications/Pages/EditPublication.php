<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Filament\Resources\Publications\PublicationResource;
use App\Filament\Resources\Publications\Schemas\PublicationForm;
use App\Support\PublicationAuthorship;
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
        $this->authorData = [
            'first_author_id' => $data['first_author_id'] ?? null,
            'corresponding_author_id' => $data['corresponding_author_id'] ?? null,
            'co_author_ids' => $data['co_author_ids'] ?? [],
        ];

        unset($data['first_author_id'], $data['corresponding_author_id'], $data['co_author_ids']);

        // Who created a paper does not change when it is edited.
        unset($data['created_by']);

        // Hidden from a teacher, and kept as it is on their saves.
        if (PublicationForm::isTeacherOnly()) {
            unset($data['status']);
        }

        return $data;
    }

    /**
     * The form knows three things — who was first, who corresponded, and who
     * else is on it — and the author list is rebuilt from those. Each row holds
     * more: the affiliation printed against the author, whether it was ours,
     * the incentive apportioned to them, and whether they agreed to be on the
     * paper. PublicationAuthorship carries all of it across the rebuild; one
     * that started from the form alone used to destroy the first three on a
     * typo fix to the title.
     */
    protected function afterSave(): void
    {
        PublicationAuthorship::sync($this->getRecord(), $this->authorData, actor: auth()->user());
    }
}
