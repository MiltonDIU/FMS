<?php

namespace App\Filament\Resources\ResearchCollaborations\Pages;

use App\Filament\Resources\ResearchCollaborations\ResearchCollaborationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResearchCollaborations extends ListRecords
{
    protected static string $resource = ResearchCollaborationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
