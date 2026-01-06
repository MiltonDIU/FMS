<?php

namespace App\Filament\Resources\PublicationTypes\Pages;

use App\Filament\Resources\PublicationTypes\PublicationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPublicationType extends EditRecord
{
    protected static string $resource = PublicationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
