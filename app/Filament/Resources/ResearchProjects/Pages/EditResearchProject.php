<?php

namespace App\Filament\Resources\ResearchProjects\Pages;

use App\Filament\Resources\ResearchProjects\ResearchProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditResearchProject extends EditRecord
{
    protected static string $resource = ResearchProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
