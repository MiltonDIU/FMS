<?php

namespace App\Filament\Resources\ResearchProjects\Pages;

use App\Filament\Resources\ResearchProjects\ResearchProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResearchProjects extends ListRecords
{
    protected static string $resource = ResearchProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
