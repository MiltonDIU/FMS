<?php

namespace App\Filament\Resources\IntegrationMappings\Pages;

use App\Filament\Resources\IntegrationMappings\IntegrationMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIntegrationMapping extends EditRecord
{
    protected static string $resource = IntegrationMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
