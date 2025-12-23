<?php

namespace App\Filament\Resources\ResearchCollaborations\Pages;

use App\Filament\Resources\ResearchCollaborations\ResearchCollaborationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditResearchCollaboration extends EditRecord
{
    protected static string $resource = ResearchCollaborationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
